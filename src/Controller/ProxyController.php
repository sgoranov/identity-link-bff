<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\OidcUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProxyController extends AbstractController
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly array $serviceBaseUrls,
        private readonly bool $tlsVerify,
    )
    {
    }

    #[Route(
        '/proxy/{service}/{path}',
        name: 'proxy',
        requirements: ['path' => '.+'],
        methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
    )]
    public function __invoke(Request $request, string $service, string $path): Response
    {
        $user = $this->getUser();
        if (!$user instanceof OidcUser) {
            return new JsonResponse(['error' => 'Unauthenticated user.'], Response::HTTP_UNAUTHORIZED);
        }

        $accessToken = $user->getAccessToken();
        if ($accessToken === null) {
            return new JsonResponse(['error' => 'Missing access token.'], Response::HTTP_FORBIDDEN);
        }

        $baseUrl = $this->serviceBaseUrls[$service] ?? null;
        if ($baseUrl === null) {
            return new JsonResponse(['error' => 'Unknown service.'], Response::HTTP_NOT_FOUND);
        }

        $queryString = $request->getQueryString();
        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/')
            . ($queryString ? '?' . $queryString : '');

        $headers = [];
        $forwardableHeaders = ['content-type', 'accept', 'accept-language'];
        foreach ($forwardableHeaders as $name) {
            if ($request->headers->has($name)) {
                $headers[$name] = $request->headers->get($name);
            }
        }

        $headers['Authorization'] = 'Bearer ' . $accessToken;

        try {
            $backendResponse = $this->httpClient->request($request->getMethod(), $url, [
                'headers' => $headers,
                'body' => $request->getContent(),
                'buffer' => false, // tells Symfony NOT to save the file to disk/memory.
                'verify_peer' => $this->tlsVerify,
            ]);

            return new StreamedResponse(function () use ($backendResponse): void {
                foreach ($this->httpClient->stream($backendResponse) as $chunk) {
                    echo $chunk->getContent();
                    flush();

                    if (connection_aborted()) {
                        $backendResponse->cancel();
                        break;
                    }
                }
            }, $backendResponse->getStatusCode(), $this->filterHeaders($backendResponse->getHeaders(false)));

        } catch (TransportExceptionInterface $exception) {
            return new JsonResponse(['error' => 'Backend unavailable.'], Response::HTTP_BAD_GATEWAY);
        }
    }

    private function filterHeaders(array $headers): array
    {
        $exclude = [
            'transfer-encoding',
            'content-length',
            'content-encoding',
            'host',
            'connection'
        ];

        $filtered = [];
        foreach ($headers as $key => $values) {
            if (!in_array(strtolower($key), $exclude)) {
                $filtered[$key] = $values;
            }
        }

        $filtered['cache-control'] = ['no-cache', 'must-revalidate'];
        $filtered['x-content-type-options'] = ['nosniff'];
        $filtered['X-Accel-Buffering'] = ['no'];

        return $filtered;
    }
}

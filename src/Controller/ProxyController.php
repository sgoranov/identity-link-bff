<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\OidcUser;
use Psr\Log\LoggerInterface;
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
    private const MAX_LOGGED_BODY_LENGTH = 2000;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private readonly array $serviceBaseUrls,
        private readonly bool $verifyPeer,
        private readonly int $verifyHost,
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
                'verify_host' => $this->verifyHost,
                'verify_peer' => $this->verifyPeer,
            ]);

            $status = $backendResponse->getStatusCode();
            if ($status >= 400) {
                $body = $backendResponse->getContent(false);
                $context = [
                    'status' => $status,
                    'method' => $request->getMethod(),
                    'url' => $url,
                    'body' => substr($body, 0, self::MAX_LOGGED_BODY_LENGTH),
                    'body_truncated' => strlen($body) > self::MAX_LOGGED_BODY_LENGTH,
                ];

                match (true) {
                    $status >= 500 => $this->logger->error('Backend returned server error', $context),
                    default => $this->logger->warning('Backend returned client error', $context),
                };

                return new Response(
                    $body,
                    $status,
                    $this->filterHeaders($backendResponse->getHeaders(false))
                );
            }

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
            $this->logger->error('Proxy transport error', [
                'url' => $url,
                'service' => $service,
                'exception' => $exception,
                'message' => $exception->getMessage(),
            ]);

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

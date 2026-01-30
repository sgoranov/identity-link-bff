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
        $headers = $request->headers->all();
        $headers['Authorization'] = 'Bearer ' . $accessToken;

        try {
            $backendResponse = $this->httpClient->request($request->getMethod(), $url, [
                'headers' => $headers,
                'body' => $request->getContent(),
            ]);
        } catch (TransportExceptionInterface $exception) {
            return new JsonResponse(['error' => 'Backend unavailable.'], Response::HTTP_BAD_GATEWAY);
        }

        $responseHeaders = $backendResponse->getHeaders(false);
        $response = $this->createStreamedResponse($backendResponse);

        foreach ($responseHeaders as $name => $values) {
            foreach ($values as $value) {
                $response->headers->set($name, $value, false);
            }
        }

        return $response;
    }

    private function createStreamedResponse($backendResponse): StreamedResponse
    {
        return new StreamedResponse(function () use ($backendResponse): void {
            foreach ($this->httpClient->stream($backendResponse) as $chunk) {
                echo $chunk->getContent();
                flush();
            }
        }, $backendResponse->getStatusCode());
    }
}

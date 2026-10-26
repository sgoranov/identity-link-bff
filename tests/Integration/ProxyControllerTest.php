<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\Security\OidcUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ProxyControllerTest extends WebTestCase
{
    public function testProxyForwardsAuthenticatedRequestSuccessfully(): void
    {
        $client = static::createClient();
        $user = new OidcUser(
            'test_user',
            'Test User Name',
            $this->getJwt('test_user'),
            'mock_refresh_token',
            [],
        );
        $client->loginUser($user, 'main');

        self::getContainer()->set(
            HttpClientInterface::class,
            $this->createMockHttpClient()
        );

        $client->request('GET', '/proxy/my-service/v1/users?page=2');
        $response = $client->getResponse();

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('content-type'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('cache-control'));
    }

    public function testProxyForwardsTheRequestId(): void
    {
        $headers = $this->sendThroughProxy(['HTTP_X_REQUEST_ID' => '0123456789abcdef0123456789abcdef']);

        $this->assertSame(['x-request-id: 0123456789abcdef0123456789abcdef'], $headers['x-request-id']);
    }

    public function testProxySendsNoRequestIdWhenTheRequestHasNone(): void
    {
        $headers = $this->sendThroughProxy([]);

        $this->assertArrayNotHasKey('x-request-id', $headers);
    }

    public function testProxyBlocksUnauthenticatedRequests(): void
    {
        $client = static::createClient();
        $client->catchExceptions(true);

        $client->request('GET', '/proxy/my-service/v1/users');
        $response = $client->getResponse();

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    /**
     * @return array<string, list<string>> the headers sent to the backend, keyed by lowercase name
     */
    private function sendThroughProxy(array $server): array
    {
        $client = static::createClient();
        $client->loginUser(new OidcUser(
            'test_user',
            'Test User Name',
            $this->getJwt('test_user'),
            'mock_refresh_token',
            [],
        ), 'main');

        $backendResponse = new MockResponse('{}', ['response_headers' => ['Content-Type' => 'application/json']]);
        self::getContainer()->set(HttpClientInterface::class, new MockHttpClient($backendResponse));

        $client->request('GET', '/proxy/my-service/v1/users', server: $server);

        $this->assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        return $backendResponse->getRequestOptions()['normalized_headers'];
    }

    private function getJwt(string $user): string
    {
        $jwtHeader = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $jwtPayload = base64_encode(json_encode([
            'sub' => $user,
            'exp' => time() + 3600
        ]));
        $jwtSignature = base64_encode('mock_signature_hash');
        return sprintf('%s.%s.%s', $jwtHeader, $jwtPayload, $jwtSignature);
    }

    private function createMockHttpClient(int $statusCode = Response::HTTP_OK): MockHttpClient
    {
        return new MockHttpClient(
            fn () => new MockResponse(
                '{}',
                [
                    'status_code' => $statusCode,
                    'response_headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ]
            )
        );
    }
}

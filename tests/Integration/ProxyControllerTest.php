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

    public function testProxyBlocksUnauthenticatedRequests(): void
    {
        $client = static::createClient();
        $client->catchExceptions(true);

        $client->request('GET', '/proxy/my-service/v1/users');
        $response = $client->getResponse();

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
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

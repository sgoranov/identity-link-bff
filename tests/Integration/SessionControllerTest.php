<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\Security\OidcUser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class SessionControllerTest extends WebTestCase
{
    public function testReturnsTheAuthenticatedOidcSessionDetails(): void
    {
        $client = static::createClient();
        $client->loginUser(new OidcUser(
            'user-id',
            'Jane Doe',
            $this->getJwt('user-id'),
            'refresh-token',
            ['users.read', 'clients.query'],
        ), 'main');

        $client->request('GET', '/session');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('content-type'));
        self::assertSame([
            'id' => 'user-id',
            'name' => 'Jane Doe',
            'roles' => ['users.read', 'clients.query'],
            'access_token_present' => true,
            'refresh_token_present' => true,
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testRejectsAnUnauthenticatedRequest(): void
    {
        $client = static::createClient();

        $client->request('GET', '/session');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    private function getJwt(string $userIdentifier): string
    {
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = base64_encode(json_encode([
            'sub' => $userIdentifier,
            'exp' => time() + 3600,
        ], JSON_THROW_ON_ERROR));

        return sprintf('%s.%s.%s', $header, $payload, base64_encode('test-signature'));
    }
}

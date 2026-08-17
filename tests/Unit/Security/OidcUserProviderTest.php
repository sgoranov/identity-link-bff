<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\OidcUser;
use App\Security\OidcUserProvider;
use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class OidcUserProviderTest extends TestCase
{
    public function testLoadOidcUserRequiresAnActiveSession(): void
    {
        $provider = new OidcUserProvider(new RequestStack());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An active session is required for OIDC authentication.');

        $provider->loadOidcUser('user-id');
    }

    public function testEnsureUserExistsRequiresAnActiveSession(): void
    {
        $provider = new OidcUserProvider(new RequestStack());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An active session is required for OIDC authentication.');

        $provider->ensureUserExists(
            'user-id',
            new OidcUserData([]),
            $this->createTokens(),
        );
    }

    public function testStoresOidcDataInTheActiveSessionAndLoadsTheUser(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $provider = new OidcUserProvider($requestStack);
        $provider->ensureUserExists(
            'user-id',
            new OidcUserData([
                'given_name' => 'Jane',
                'family_name' => 'Doe',
            ]),
            $this->createTokens(),
        );

        $user = $provider->loadOidcUser('user-id');

        self::assertInstanceOf(OidcUser::class, $user);
        self::assertSame('user-id', $user->getUserIdentifier());
        self::assertSame('Jane Doe', $user->getName());
        self::assertSame('access-token', $user->getAccessToken());
        self::assertSame('refresh-token', $user->getRefreshToken());
    }

    private function createTokens(): OidcTokens
    {
        return new OidcTokens((object) [
            'access_token' => 'access-token',
            'id_token' => 'id-token',
            'refresh_token' => 'refresh-token',
        ]);
    }
}

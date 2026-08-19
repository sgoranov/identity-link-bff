<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\OidcUser;
use App\Security\OidcUserProvider;
use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class OidcUserProviderTest extends TestCase
{
    public function testLoadOidcUserRequiresAnActiveSession(): void
    {
        $provider = new OidcUserProvider(new RequestStack(), new NullLogger());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An active session is required for OIDC authentication.');

        $provider->loadOidcUser('user-id');
    }

    public function testEnsureUserExistsRequiresAnActiveSession(): void
    {
        $provider = new OidcUserProvider(new RequestStack(), new NullLogger());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('An active session is required for OIDC authentication.');

        $provider->ensureUserExists(
            'user-id',
            new OidcUserData([]),
            $this->createTokens(),
        );
    }

    public function testLoadOidcUserRejectsMissingRequiredSessionData(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $provider = new OidcUserProvider($requestStack, new NullLogger());

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Required OIDC session value "_oidc_user_name" is missing or invalid.');

        $provider->loadOidcUser('user-id');
    }

    public function testEnsureUserExistsRejectsAMissingRefreshToken(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $provider = new OidcUserProvider($requestStack, new NullLogger());
        $tokens = new OidcTokens((object) [
            'access_token' => 'access-token',
            'id_token' => 'id-token',
        ]);

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The OIDC provider did not return a refresh token.');

        $provider->ensureUserExists(
            'user-id',
            new OidcUserData(['name' => 'Jane Doe']),
            $tokens,
        );
    }

    public function testStoresOidcDataInTheActiveSessionAndLoadsTheUser(): void
    {
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('debug')
            ->with('OIDC user authenticated with scopes.', [
                'scopes' => ['users.read', 'clients.query', '2fa.manage'],
            ]);

        $provider = new OidcUserProvider($requestStack, $logger);
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
        self::assertSame(
            ['users.read', 'clients.query', '2fa.manage'],
            $user->getRoles(),
        );
    }

    private function createTokens(): OidcTokens
    {
        return new OidcTokens((object) [
            'access_token' => 'access-token',
            'id_token' => 'id-token',
            'refresh_token' => 'refresh-token',
            'scope' => 'openid users.read profile clients.query 2fa.manage identity-link.all users.read',
        ]);
    }
}

<?php
declare(strict_types=1);

namespace App\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OidcUserProvider implements OidcUserProviderInterface
{
    private const SESSION_ACCESS_TOKEN_KEY = '_oidc_access_token';
    private const SESSION_REFRESH_TOKEN_KEY = '_oidc_refresh_token';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new \LogicException('Not used.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === OidcUser::class;
    }

    public function ensureUserExists(string $userIdentifier, OidcUserData $userData, OidcTokens $tokens): void
    {
        $session = $this->getSession();
        if ($session === null) {
            return;
        }

        $session->set(self::SESSION_ACCESS_TOKEN_KEY, $tokens->getAccessToken());
        $session->set(self::SESSION_REFRESH_TOKEN_KEY, $tokens->getRefreshToken());
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        $accessToken = null;
        $refreshToken = null;
        $session = $this->getSession();
        if ($session !== null) {
            $accessToken = $session->get(self::SESSION_ACCESS_TOKEN_KEY);
            $refreshToken = $session->get(self::SESSION_REFRESH_TOKEN_KEY);
        }

        return new OidcUser($userIdentifier, $accessToken, $refreshToken);
    }

    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}

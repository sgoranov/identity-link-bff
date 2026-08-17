<?php
declare(strict_types=1);

namespace App\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class OidcUserProvider implements OidcUserProviderInterface
{
    private const SESSION_ACCESS_TOKEN_KEY = '_oidc_access_token';
    private const SESSION_REFRESH_TOKEN_KEY = '_oidc_refresh_token';
    private const SESSION_NAME_KEY = '_oidc_user_name';

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        throw new \LogicException('Not used.');
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof OidcUser) {
            return $user;
        }

        $accessToken = $user->getAccessToken();

        if ($accessToken === null || $this->isTokenExpired($accessToken)) {
            // Throwing this exception tells Symfony: "This user is no longer valid"
            // Symfony will then clear the session and redirect to the login entry point.
            throw new UserNotFoundException('OIDC Access Token has expired.');
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === OidcUser::class;
    }

    public function ensureUserExists(string $userIdentifier, OidcUserData $userData, OidcTokens $tokens): void
    {
        $session = $this->getSession();

        $session->set(self::SESSION_ACCESS_TOKEN_KEY, $tokens->getAccessToken());
        $session->set(self::SESSION_REFRESH_TOKEN_KEY, $tokens->getRefreshToken());
        $session->set(self::SESSION_NAME_KEY, $this->resolveName($userData));
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        $session = $this->getSession();
        $accessToken = $session->get(self::SESSION_ACCESS_TOKEN_KEY);
        $refreshToken = $session->get(self::SESSION_REFRESH_TOKEN_KEY);
        $name = $session->get(self::SESSION_NAME_KEY);

        return new OidcUser($userIdentifier, is_string($name) && $name !== '' ? $name : null, $accessToken, $refreshToken);
    }

    private function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            throw new \LogicException('An active session is required for OIDC authentication.');
        }

        return $request->getSession();
    }

    private function isTokenExpired(string $token): bool
    {
        try {
            // JWTs are 3 parts: Header.Payload.Signature
            $parts = explode('.', $token);
            if (count($parts) !== 3) return true;

            $payload = json_decode(base64_decode($parts[1]), true);

            // Check the 'exp' claim (Unix timestamp)
            // We subtract 10 seconds as a "buffer" for clock skew
            return isset($payload['exp']) && $payload['exp'] < (time() - 10);
        } catch (\Exception $e) {
            return true;
        }
    }

    private function resolveName(OidcUserData $userData): ?string
    {
        $givenName = trim($userData->getGivenName());
        $familyName = trim($userData->getFamilyName());
        $givenFamily = trim($givenName . ' ' . $familyName);
        if ($givenFamily !== '') {
            return $givenFamily;
        }

        $fullName = trim($userData->getFullName());
        if ($fullName !== '') {
            return $fullName;
        }

        $displayName = trim($userData->getDisplayName());
        if ($displayName !== '') {
            return $displayName;
        }

        $email = trim($userData->getEmail());
        return $email !== '' ? $email : null;
    }
}

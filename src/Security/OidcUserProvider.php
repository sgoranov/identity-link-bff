<?php
declare(strict_types=1);

namespace App\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;

class OidcUserProvider implements OidcUserProviderInterface
{
    private const SESSION_ACCESS_TOKEN_KEY = '_oidc_access_token';
    private const SESSION_REFRESH_TOKEN_KEY = '_oidc_refresh_token';
    private const SESSION_NAME_KEY = '_oidc_user_name';
    private const SESSION_ROLES_KEY = '_oidc_user_roles';
    private const SCOPE_PREFIXES = ['users.', 'clients.', '2fa.'];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
    ) {
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

        if ($this->isTokenExpired($accessToken)) {
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
        $refreshToken = $tokens->getRefreshToken();
        $name = $this->resolveName($userData);

        if ($refreshToken === null || $refreshToken === '') {
            throw new \UnexpectedValueException('The OIDC provider did not return a refresh token.');
        }
        if ($name === null) {
            throw new \UnexpectedValueException('The OIDC provider did not return a usable user name.');
        }
        $roles = $this->resolveRoles($tokens);

        $session->set(self::SESSION_ACCESS_TOKEN_KEY, $tokens->getAccessToken());
        $session->set(self::SESSION_REFRESH_TOKEN_KEY, $refreshToken);
        $session->set(self::SESSION_NAME_KEY, $name);
        $session->set(self::SESSION_ROLES_KEY, $roles);

        $this->logger->debug('OIDC user authenticated with scopes.', [
            'scopes' => $roles,
        ]);
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        $session = $this->getSession();

        return new OidcUser(
            $userIdentifier,
            $this->getRequiredSessionString($session, self::SESSION_NAME_KEY),
            $this->getRequiredSessionString($session, self::SESSION_ACCESS_TOKEN_KEY),
            $this->getRequiredSessionString($session, self::SESSION_REFRESH_TOKEN_KEY),
            $this->getRequiredSessionRoles($session),
        );
    }

    private function getSession(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || !$request->hasSession()) {
            throw new \LogicException('An active session is required for OIDC authentication.');
        }

        return $request->getSession();
    }

    private function getRequiredSessionString(SessionInterface $session, string $key): string
    {
        $value = $session->get($key);
        if (!is_string($value) || $value === '') {
            throw new \UnexpectedValueException(sprintf('Required OIDC session value "%s" is missing or invalid.', $key));
        }

        return $value;
    }

    /** @return string[] */
    private function getRequiredSessionRoles(SessionInterface $session): array
    {
        $roles = $session->get(self::SESSION_ROLES_KEY);
        if (!is_array($roles) || array_filter($roles, static fn (mixed $role): bool => !is_string($role))) {
            throw new \UnexpectedValueException('Required OIDC session roles are missing or invalid.');
        }

        return array_values($roles);
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

    private function resolveRoles(OidcTokens $tokens): array
    {
        $scopes = array_filter(
            $tokens->getScope() ?? [],
            static function (mixed $scope): bool {
                if (!is_string($scope)) {
                    return false;
                }

                foreach (self::SCOPE_PREFIXES as $prefix) {
                    if (str_starts_with($scope, $prefix)) {
                        return true;
                    }
                }

                return false;
            },
        );

        return array_values(array_unique($scopes));
    }
}

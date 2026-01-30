<?php
declare(strict_types=1);

namespace App\Security;

use Drenso\OidcBundle\Model\OidcTokens;
use Drenso\OidcBundle\Model\OidcUserData;
use Drenso\OidcBundle\Security\UserProvider\OidcUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class OidcUserProvider implements OidcUserProviderInterface
{
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
        // TODO: Implement ensureUserExists() method.
    }

    public function loadOidcUser(string $userIdentifier): UserInterface
    {
        return new OidcUser($userIdentifier);
    }
}

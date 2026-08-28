<?php
declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class OidcScopeVoter extends Voter
{
    private const SCOPE_PREFIXES = ['users.', 'clients.', '2fa.'];

    protected function supports(string $attribute, mixed $subject): bool
    {
        foreach (self::SCOPE_PREFIXES as $prefix) {
            if (str_starts_with($attribute, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof OidcUser
            && in_array($attribute, $user->getRoles(), true);
    }
}

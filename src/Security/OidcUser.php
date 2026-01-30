<?php
declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

readonly class OidcUser implements UserInterface
{
    public function __construct(private string $userIdentifier)
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}

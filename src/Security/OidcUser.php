<?php
declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

readonly class OidcUser implements UserInterface
{
    public function __construct(
        private string $userIdentifier,
        private ?string $name = null,
        private ?string $accessToken = null,
        private ?string $refreshToken = null,
    )
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function eraseCredentials(): void
    {
    }
}

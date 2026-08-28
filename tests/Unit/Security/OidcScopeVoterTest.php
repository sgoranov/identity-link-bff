<?php
declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\OidcScopeVoter;
use App\Security\OidcUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class OidcScopeVoterTest extends TestCase
{
    public function testGrantsAnAssignedSupportedScope(): void
    {
        $voter = new OidcScopeVoter();
        $token = $this->createToken(new OidcUser(
            'user-id',
            name: 'Test User',
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            roles: ['users.read'],
        ));

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($token, null, ['users.read']));
    }

    public function testDeniesAnUnassignedSupportedScope(): void
    {
        $voter = new OidcScopeVoter();
        $token = $this->createToken(new OidcUser(
            'user-id',
            name: 'Test User',
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            roles: ['users.read'],
        ));

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($token, null, ['clients.query']));
    }

    public function testAbstainsForAnUnsupportedScope(): void
    {
        $voter = new OidcScopeVoter();
        $token = $this->createToken(new OidcUser(
            'user-id',
            name: 'Test User',
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            roles: ['identity-link.all'],
        ));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($token, null, ['identity-link.all']));
    }

    private function createToken(OidcUser $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}

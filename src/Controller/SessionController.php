<?php
declare(strict_types=1);

namespace App\Controller;

use App\Security\OidcUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SessionController extends AbstractController
{
    #[Route('/session', name: 'session', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof OidcUser) {
            return new JsonResponse(['error' => 'Unauthenticated user.'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'id' => $user->getUserIdentifier(),
            'access_token_present' => $user->getAccessToken() !== null,
            'refresh_token_present' => $user->getRefreshToken() !== null,
        ]);
    }
}

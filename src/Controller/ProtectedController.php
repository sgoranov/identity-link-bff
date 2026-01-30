<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class ProtectedController extends AbstractController
{
    #[Route('/protected', name: 'protected')]
    public function index(): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'message' => 'This is a protected page!',
        ]);
    }
}

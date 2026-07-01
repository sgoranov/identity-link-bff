<?php
declare(strict_types=1);

namespace App\Controller;

use Drenso\OidcBundle\OidcClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(OidcClientInterface $oidcClient, LoggerInterface $logger): Response
    {
        try {
            return $oidcClient->generateAuthorizationRedirect(scopes: ['openid', 'profile', 'email']);
        } catch (\Throwable $e) {
            $logger->error('OIDC login redirection failed: ' . $e->getMessage(), [
                'exception' => $e
            ]);

            return new Response(
                'Internal Server Error: OIDC Authentication Failed.',
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    #[Route('/post-login', name: 'post_login', methods: ['GET'])]
    public function postLogin(): RedirectResponse
    {
        return new RedirectResponse($this->getParameter('post_login_redirect_url'));
    }
}

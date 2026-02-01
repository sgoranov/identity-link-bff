<?php
declare(strict_types=1);

namespace App\Controller;

use Drenso\OidcBundle\OidcClientInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(OidcClientInterface $oidcClient): RedirectResponse
    {
        return $oidcClient->generateAuthorizationRedirect();
    }

    #[Route('/post-login', name: 'post_login', methods: ['GET'])]
    public function postLogin(): RedirectResponse
    {
        return new RedirectResponse($this->getParameter('post_login_redirect_url'));
    }
}

<?php
declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

readonly class OidcAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public function start(Request $request, AuthenticationException $authException = null): RedirectResponse|JsonResponse
    {
        $route = $request->attributes->get('_route');
        if ($request->isXmlHttpRequest() ||  in_array($route, ['proxy', 'session'], true)) {
            $loginUrl = $this->urlGenerator->generate('login', [], UrlGeneratorInterface::ABSOLUTE_URL);
            return new JsonResponse([
                'error' => 'unauthorized',
                'location' => $loginUrl
            ], 401);
        }

        return new RedirectResponse($this->urlGenerator->generate('login'));
    }
}
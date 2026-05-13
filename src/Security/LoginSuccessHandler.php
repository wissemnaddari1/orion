<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            $route = 'client_dashboard';
        } else {
            $role = $user->getRole();
            $route = match ($role) {
                UserRole::ADMIN => 'admin_dashboard',
                UserRole::WORKER => 'worker_dashboard',
                default => 'client_dashboard',
            };
        }

        return new Response('', Response::HTTP_FOUND, [
            'Location' => $this->urlGenerator->generate($route),
        ]);
    }
}

<?php

namespace App\Security;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private Security $security,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): RedirectResponse
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $role = $user->getRole();

            $route = match ($role) {
                UserRole::ADMIN => 'admin_dashboard',
                UserRole::WORKER => 'worker_dashboard',
                UserRole::CLIENT => 'client_dashboard',
                default => 'app_login',
            };

            return new RedirectResponse($this->urlGenerator->generate($route));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}

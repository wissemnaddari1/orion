<?php

namespace App\Controller;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends BaseController
{
    /**
     * GET /login — render Twig form.
     *
     * Stateless auth:
     * - Frontend posts credentials to POST /api/login
     * - Token is returned in JSON (and may be stored client-side)
     */
    #[Route('/login', name: 'app_login', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('pages/auth/login.html.twig');
    }

    #[Route('/register', name: 'app_register')]
    public function register(): Response
    {
        return $this->render('pages/auth/register.html.twig', [
            'controller_name' => 'AuthController',
        ]);
    }
}

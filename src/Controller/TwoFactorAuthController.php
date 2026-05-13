<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stateless 2FA page.
 *
 * Verification is performed by POST /api/auth/2fa/verify from the frontend JS.
 */
final class TwoFactorAuthController extends BaseController
{
    /**
     * GET /2fa — Show 2FA verification form.
     */
    #[Route('/2fa', name: 'app_2fa', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render('security/2fa.html.twig', [
            'email' => (string) $request->query->get('email', ''),
            'is_locked' => false,
            'attempts_remaining' => 5,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class PasswordResetController extends BaseController
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordResetService $passwordResetService,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * GET /forgot-password — show form.
     * POST /forgot-password — submit email; always show success (no account enumeration).
     */
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $success = false;
        $email = '';

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            if ($email !== '') {
                $user = $this->userRepository->findOneBy(['email' => $email]);
                if ($user instanceof User) {
                    $rawToken = $this->passwordResetService->createRequest($user);
                    $this->passwordResetService->sendResetEmail($user, $rawToken);
                }
            }

            // Always show success to avoid account enumeration
            $success = true;
        }

        return $this->render('pages/auth/forgot_password.html.twig', [
            'success' => $success,
            'email' => $email,
        ]);
    }

    /**
     * GET /reset-password/{token} — validate token (hash + expiry), show form only if valid.
     * POST /reset-password/{token} — set new password, mark token used, redirect to login with success.
     */
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $token = trim($token);
        if ($token === '') {
            return $this->redirectToRoute('app_forgot_password');
        }

        $resetToken = $this->passwordResetService->findValidToken($token);
        if ($resetToken === null) {
            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(\App\Form\ResetPasswordType::class, null);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->passwordResetService->consumeToken($resetToken);
            $plainPassword = $form->get('plainPassword')->getData();
            if (\is_string($plainPassword)) {
                $user->setPasswordHash($this->passwordHasher->hashPassword($user, $plainPassword));
            }
            $user->resetLoginAttempts();
            $this->entityManager->flush();

            return $this->redirectToRoute('app_login');
        }

        return $this->render('pages/auth/reset_password.html.twig', [
            'form' => $form->createView(),
            'token' => $token,
        ]);
    }
}

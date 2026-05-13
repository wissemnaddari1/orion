<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\BaseController;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/banned', name: 'app_banned_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class BannedController extends BaseController
{
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user->isBanned()) {
            return $this->redirectToRoute('app_home');
        }

        $bannedAt = $user->getBannedAt();
        $endsAt = $user->getBanEndsAt();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $remaining = null;
        if ($endsAt !== null && $endsAt > $now) {
            $remaining = $now->diff($endsAt, true);
        }

        return $this->render('pages/banned/show.html.twig', [
            'user' => $user,
            'remaining' => $remaining,
            'is_permanent' => $endsAt === null,
        ]);
    }
}

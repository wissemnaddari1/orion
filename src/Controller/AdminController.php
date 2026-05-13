<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ServiceRequestRepository;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
final class AdminController extends BaseController
{
    /**
     * Finds and displays service requests for a specific user ID.
     */
    #[Route('/user/{id}/services', name: 'admin_user_services')]
    public function userServices(User $user, ServiceRequestRepository $srRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $services = $srRepo->findBy(['client' => $user]);

        return $this->render('admin/user_services.html.twig', [
            'user' => $user,
            'services' => $services,
        ]);
    }
}

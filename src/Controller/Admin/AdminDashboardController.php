<?php

namespace App\Controller\Admin;

use App\Controller\BaseController;
use App\Service\OfferAnalyticsService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminDashboardController extends BaseController
{
    #[Route('/admin', name: 'admin_dashboard')]
    public function index(): Response
    {
        return $this->redirectToRoute('admin_users_index');
    }
}

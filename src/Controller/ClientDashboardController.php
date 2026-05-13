<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Repository\ContractRepository;
use App\Repository\ServiceRequestRepository;
use App\Service\ClientSidebarService;
use App\Controller\BaseController;
use App\Repository\ServiceRequirementRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientDashboardController extends BaseController
{
    public function __construct(
        private ClientSidebarService $clientSidebar,
    ) {
    }

    #[Route('/client/dashboard', name: 'client_dashboard')]
    public function client(
        Request $request,
        ContractRepository $contractRepository,
        ServiceRequestRepository $srRepo
    ): Response {
        $user = $this->getUser();

        // ── Original stats (cached count to avoid repeated queries) ─────────────
        $activeContracts = (int) $contractRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.client = :client')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('client', $user)
            ->setParameter('statuses', [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS])
            ->getQuery()
            ->getSingleScalarResult();
        $totalRequests = $srRepo->countByClient($user);

        // ── Recent requests (with category joined to avoid N+1) ───────────────
        $recentRequests = $srRepo->findBy(
            ['client' => $user],
            ['createdAt' => 'DESC'],
            5
        );

        // ── Spending by category (with LIMIT for ORDER BY) ──────────────────────
        $spendingByCategory = $srRepo->getSpendingByCategory($user);

        // ── Activity + budget totals in one go (no full entity load) ───────────
        $activityAndBudget = $srRepo->getActivityAndBudgetTotalsByClient($user);
        $activityMap = $activityAndBudget['activity'];
        $activityData = array_map(
            fn($d, $t) => ['day' => $d, 'total' => $t],
            array_keys($activityMap),
            array_values($activityMap)
        );
        $totalBudgetMin = $activityAndBudget['budget_min'];
        $totalBudgetMax = $activityAndBudget['budget_max'];

        return $this->render('pages/client/dashboard.html.twig', [
            'user'             => $user,
            'stats'            => [
                'active_requests'  => $totalRequests,
                'active_contracts' => $activeContracts,
                'pending_offers'   => 0,
                'wallet_balance'   => $user->getAccountBalance(),
                'wallet_currency'  => $user->getWalletCurrency()->value,
            ],
            'sidebar_items'    => $this->clientSidebar->getItems($request),
            'topbar_title'     => 'Client Dashboard',
            'recent_requests'  => $recentRequests,
            'chart_activity'   => json_encode($activityData),
            'chart_spending'   => json_encode($spendingByCategory),
            'total_budget_min' => $totalBudgetMin,
            'total_budget_max' => $totalBudgetMax,
        ]);
    }
}

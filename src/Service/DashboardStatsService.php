<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\UserRepository;

class DashboardStatsService
{
    public function __construct(
        private UserRepository $userRepository
    ) {}

    /**
     * Get admin dashboard statistics
     */
    public function getAdminStats(): array
    {
        $totalUsers = $this->userRepository->countAllUsers();
        $activeUsers = $this->userRepository->countByStatusValue(UserStatus::ACTIVE);
        $pendingUsers = $this->userRepository->countByStatusValue(UserStatus::PENDING);

        $clients = $this->userRepository->countByRoleValue(UserRole::CLIENT);
        $workers = $this->userRepository->countByRoleValue(UserRole::WORKER);
        $admins = $this->userRepository->countByRoleValue(UserRole::ADMIN);

        $pendingCertificates = $this->userRepository->countPendingCertificates();

        return [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'pending_users' => $pendingUsers,
            'clients' => $clients,
            'workers' => $workers,
            'admins' => $admins,
            'pending_certificates' => $pendingCertificates,
            'growth_rate' => $this->calculateGrowthRate(),
        ];
    }

    /**
     * Get client dashboard statistics
     */
    public function getClientStats(User $user): array
    {
        // Placeholder stats - in a real app, you'd query actual service/contract data
        return [
            'active_requests' => 0,
            'matched_services' => 0,
            'active_contracts' => 0,
            'completed_contracts' => 0,
            'total_spent' => '0.00',
            'pending_offers' => 0,
            'wallet_balance' => $user->getAccountBalance(),
            'wallet_currency' => $user->getWalletCurrency()->value,
        ];
    }

    /**
     * Get freelancer/worker dashboard statistics
     */
    public function getWorkerStats(User $user): array
    {
        // Placeholder stats - in a real app, you'd query actual contract/offer data
        return [
            'new_matches' => 0,
            'active_offers' => 0,
            'active_contracts' => 0,
            'completed_contracts' => 0,
            'total_earnings' => '0.00',
            'pending_payments' => '0.00',
            'rating' => $user->getRatingAvg() ?? '0.00',
            'total_reviews' => $user->getTotalReviews(),
            'wallet_balance' => $user->getAccountBalance(),
            'wallet_currency' => $user->getWalletCurrency()->value,
        ];
    }

    /**
     * Get recent users for admin dashboard
     */
    public function getRecentUsers(int $limit = 5): array
    {
        return $this->userRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Get users with pending certificates for admin
     */
    public function getPendingCertificates(int $limit = 10): array
    {
        return $this->userRepository->findBy(
            ['certificateStatus' => 'pending'],
            ['certificateUploadedAt' => 'DESC'],
            $limit
        );
    }

    /**
     * Calculate simple growth rate (last 30 days)
     */
    private function calculateGrowthRate(): float
    {
        $thirtyDaysAgo = new \DateTimeImmutable('-30 days');
        
        $qb = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :date')
            ->setParameter('date', $thirtyDaysAgo);
        
        $recentCount = (int) $qb->getQuery()->getSingleScalarResult();
        $totalCount = $this->userRepository->countAllUsers();
        
        if ($totalCount === 0) {
            return 0.0;
        }
        
        return round(($recentCount / $totalCount) * 100, 1);
    }

    /**
     * Get user distribution by role
     */
    public function getUserDistribution(): array
    {
        $total = $this->userRepository->countAllUsers();
        
        if ($total === 0) {
            return [
                'clients' => 0,
                'workers' => 0,
                'admins' => 0,
            ];
        }

        return [
            'clients' => round(($this->userRepository->countByRoleValue(UserRole::CLIENT) / $total) * 100, 1),
            'workers' => round(($this->userRepository->countByRoleValue(UserRole::WORKER) / $total) * 100, 1),
            'admins' => round(($this->userRepository->countByRoleValue(UserRole::ADMIN) / $total) * 100, 1),
        ];
    }
}

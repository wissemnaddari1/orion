<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Form\CertificateFormType;
use App\Form\RegistrationType;
use App\Repository\UserRepository;
use App\Service\CertificateUploadService;
use App\Service\CertificateVerificationService;
use App\Service\ClientSidebarService;
use App\Service\DashboardStatsService;
use App\Service\EmailVerificationService;
use App\Service\OfferAnalyticsService;
use App\Repository\ServiceRequestRepository;
use App\Repository\ServiceRequirementRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Central Application Controller
 * 
 * Handles: Authentication, Registration, Dashboards, Freelancer Certificate Management
 */
class AppController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private DashboardStatsService $dashboardStats,
        private CertificateUploadService $certificateUpload,
        private CertificateVerificationService $certificateVerification,
        private EmailVerificationService $emailVerification,
        private UserRepository $userRepository,
        private ClientSidebarService $clientSidebar,
    ) {}

    // ==========================================
    // AUTHENTICATION ROUTES
    // ==========================================
    // Login page is handled by AuthController::index (app_login).

    /**
     * GET or POST /logout — clear JWT cookie and localStorage token, redirect to /login.
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(Request $request): Response
    {
        $cookie = Cookie::create('AUTH_TOKEN')
            ->withValue('')
            ->withExpires(1)
            ->withPath('/')
            ->withSecure($request->isSecure())
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);

        if ($request->isXmlHttpRequest() || str_contains($request->headers->get('Accept', ''), 'application/json')) {
            $response = new JsonResponse([
                'message' => 'Logged out.',
                'clearToken' => true
            ], Response::HTTP_OK);
            $response->headers->setCookie($cookie);
            return $response;
        }

        $response = new Response('<!DOCTYPE html><html><head><script>localStorage.removeItem("token");window.location.href="' . $this->generateUrl('app_login') . '";</script></head><body>Logging out...</body></html>');
        $response->headers->setCookie($cookie);
        return $response;
    }

    // ==========================================
    // REGISTRATION ROUTES
    // ==========================================

    #[Route('/register', name: 'app_register')]
    public function register(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToDashboard();
        }

        return $this->render('pages/auth/register.html.twig');
    }

    #[Route('/register/client', name: 'app_register_client', methods: ['GET', 'POST'])]
    public function registerClient(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToDashboard();
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->processRegistration($user, $form->get('plainPassword')->getData(), UserRole::CLIENT);
            
            // Send verification email
            $this->emailVerification->sendVerificationCode($user);
            return $this->redirectToRoute('app_verify_email', [
                'user' => $user->getId(),
            ]);
        }

        return $this->render('pages/auth/register_client.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/register/freelancer', name: 'app_register_freelancer', methods: ['GET', 'POST'])]
    public function registerFreelancer(Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToDashboard();
        }

        $user = new User();
        $registrationForm = $this->createForm(RegistrationType::class, $user);
        
        // Certificate upload form (explicit name "certificate" for correct binding when in same HTML form)
        $certificateForm = $this->createForm(CertificateFormType::class);

        $registrationForm->handleRequest($request);
        $certificateForm->handleRequest($request);

        if ($registrationForm->isSubmitted() && $registrationForm->isValid() && 
            $certificateForm->isSubmitted() && $certificateForm->isValid()) {
            
            // Process user registration (role CLIENT initially, will be upgraded to WORKER after approval)
            $this->processRegistration($user, $registrationForm->get('plainPassword')->getData(), UserRole::CLIENT);
            
            // Handle certificate upload
            /** @var UploadedFile $certificateFile */
            $certificateFile = $certificateForm->get('certificateFile')->getData();
            if ($certificateFile) {
                $certificatePath = $this->certificateUpload->upload($certificateFile);
                $user->setCertificatePath($certificatePath);
                $user->setCertificateStatus('pending');
                $user->recordCertificateUploadedAt(new \DateTime());
                
                $this->entityManager->flush();
                
                // Run AI verification
                $this->certificateVerification->verifyAndUpdate($user);
            }
            
            // Send verification email
            $this->emailVerification->sendVerificationCode($user);
            return $this->redirectToRoute('app_verify_email', [
                'user' => $user->getId(),
            ]);
        }

        return $this->render('pages/auth/register_freelancer.html.twig', [
            'registrationForm' => $registrationForm->createView(),
            'certificateForm' => $certificateForm->createView(),
        ]);
    }

    // ==========================================
    // DASHBOARD ROUTES
    // ==========================================

    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToDashboard();
        }
        
        return $this->render('pages/landing.html.twig');
    }

    #[Route('/admin/dashboard', name: 'admin_dashboard')]
    public function adminDashboard(
        ServiceRequestRepository $srRepo,
        ServiceRequirementRepository $reqRepo,
        OfferAnalyticsService $analyticsService // <--- Added your service here
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // ── Existing stats ────────────────────────────────────────────────────────
        $stats               = $this->dashboardStats->getAdminStats();
        $recentUsers         = $this->dashboardStats->getRecentUsers(5);
        $pendingCertificates = $this->dashboardStats->getPendingCertificates(5);
        $distribution        = $this->dashboardStats->getUserDistribution();

        // ── 1. Requests by category ───────────────────────────────────────────────
        $requestsByCategory = $srRepo->countByCategory();

        // ── 2. Requests over time (last 14 days) ──────────────────────────────────
        $since = new \DateTimeImmutable('-365 days');
        $dailyCounts = $srRepo->getDailyRequestCountsSince($since);

        $requestsOverTime = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = (new \DateTime("-{$i} days"))->format('Y-m-d');
            $requestsOverTime[$day] = 0;
        }
        foreach ($dailyCounts as $row) {
            $day = (string) ($row['day'] ?? '');
            if (isset($requestsOverTime[$day])) {
                $requestsOverTime[$day] = (int) ($row['total'] ?? 0);
            }
        }
        $requestsOverTime = array_map(
            fn($day, $total) => ['day' => $day, 'total' => $total],
            array_keys($requestsOverTime),
            array_values($requestsOverTime)
        );

        // ── 3. Budget distribution ────────────────────────────────────────────────
        $budgetRanges = $srRepo->getBudgetRangeDistribution();

        // ── 4. Level distribution ─────────────────────────────────────────────────
        $levelDistribution = $srRepo->countByStatus();

        // ── 5. Requirements by priority ───────────────────────────────────────────
        $requirementsByPriority = $reqRepo->countByPriority();

        // ── Render everything ─────────────────────────────────────────────────────
        return $this->render('pages/admin/dashboard.html.twig', [
            'stats'                         => $stats,
            'recent_users'                  => $recentUsers,
            'pending_certificates'          => $pendingCertificates,
            'distribution'                  => $distribution,
            'sidebar_items'                 => $this->getAdminSidebarItems('dashboard'),
            'topbar_title'                  => 'Admin Dashboard',
            'offer_stats'                   => $analyticsService->getConversionStats(), // <--- Added your stat here
            'chart_requests_by_category'    => json_encode($requestsByCategory),
            'chart_requests_over_time'      => json_encode($requestsOverTime),
            'chart_budget_ranges'           => json_encode($budgetRanges),
            'chart_level_distribution'      => json_encode($levelDistribution),
            'chart_requirements_priority'   => json_encode($requirementsByPriority),
        ]);
    }

    #[Route('/client/dashboard', name: 'client_dashboard')]
    public function clientDashboard(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        /** @var User $user */
        $user = $this->getUser();
        $stats = $this->dashboardStats->getClientStats($user);

        return $this->render('pages/client/dashboard.html.twig', [
            'stats' => $stats,
            'user' => $user,
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => 'Client Dashboard',
        ]);
    }

    #[Route('/worker/dashboard', name: 'worker_dashboard')]
    public function workerDashboard(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');

        /** @var User $user */
        $user = $this->getUser();
        $stats = $this->dashboardStats->getWorkerStats($user);

        return $this->render('pages/worker/dashboard.html.twig', [
            'stats' => $stats,
            'sidebar_items' => $this->getWorkerSidebarItems('dashboard'),
            'topbar_title' => 'Freelancer Dashboard',
        ]);
    }

    /**
     * Switch active workspace state (client vs freelancer). Persists in session.
     * Only allowed if user has the corresponding role. Redirects to dashboard or referrer.
     */
    #[Route('/switch-state', name: 'app_switch_state', methods: ['GET'])]
    public function switchState(Request $request): Response
    {
        $state = $request->query->get('state');
        $session = $request->getSession();

        if ($state === 'client' && $this->isGranted('ROLE_CLIENT')) {
            $session->set('orion_active_state', 'client');
            return $this->redirect($request->headers->get('Referer', $this->generateUrl('client_dashboard')));
        }
        if ($state === 'freelancer' && $this->isGranted('ROLE_WORKER')) {
            $session->set('orion_active_state', 'freelancer');
            return $this->redirect($request->headers->get('Referer', $this->generateUrl('worker_dashboard')));
        }

        return $this->redirect($request->headers->get('Referer', $this->generateUrl('app_home')));
    }

    // ==========================================
    // CLIENT: APPLY AS FREELANCER (Certificate Upload)
    // ==========================================

    #[Route('/client/apply-freelancer', name: 'client_apply_freelancer', methods: ['GET', 'POST'])]
    public function applyFreelancer(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');

        /** @var User $user */
        $user = $this->getUser();

        // Check if user already has a certificate application
        if ($user->hasCertificate()) {
            if ($user->isCertificatePending()) {
                $this->addFlash('info', 'You already have a pending certificate application.');
                return $this->redirectToRoute('client_dashboard');
            }
            if ($user->isCertificateApproved()) {
                $this->addFlash('info', 'Your certificate has already been approved.');
                return $this->redirectToRoute('client_dashboard');
            }
        }

        $form = $this->createFormBuilder()
            ->add('certificateFile', FileType::class, [
                'label' => 'Professional Certificate',
                'required' => true,
                'attr' => [
                    'class' => 'w-full text-sm text-slate-900 border border-slate-300 rounded-lg cursor-pointer bg-slate-50 focus:outline-none dark:text-slate-400 dark:bg-slate-900 dark:border-slate-700',
                    'accept' => '.pdf,.jpg,.jpeg,.png,.webp',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please upload your professional certificate.']),
                    new File([
                        'maxSize' => '5M',
                        // mimeTypes omitted: require PHP fileinfo extension; server still validates extension in CertificateUploadService
                    ]),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $certificateFile */
            $certificateFile = $form->get('certificateFile')->getData();
            
            if ($certificateFile) {
                $certificatePath = $this->certificateUpload->upload($certificateFile);
                $user->setCertificatePath($certificatePath);
                $user->setCertificateStatus('pending');
                $user->recordCertificateUploadedAt(new \DateTime());
                
                $this->entityManager->flush();
                
                // Run AI verification
                $this->certificateVerification->verifyAndUpdate($user);
                
                $this->addFlash('success', 'Your certificate has been submitted successfully. It is now pending review.');
                return $this->redirectToRoute('client_dashboard');
            }
        }

        return $this->render('pages/client/apply_freelancer.html.twig', [
            'form' => $form->createView(),
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => 'Apply as Freelancer',
            'user_name' => $user && method_exists($user, 'getFirstName') ? $user->getFirstName() : 'User',
        ]);
    }

    // ==========================================
    // ADMIN: CERTIFICATE REVIEW
    // ==========================================

    #[Route('/admin/certificates', name: 'admin_certificates_index')]
    public function adminCertificatesIndex(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $status = $request->query->get('status');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('u.certificatePath IS NOT NULL');
        
        if ($status) {
            $qb->andWhere('u.certificateStatus = :status')
               ->setParameter('status', $status);
        }
        
        $users = $qb->orderBy('u.certificateUploadedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->render('pages/admin/certificates/index.html.twig', [
            'users' => $users,
            'current_status' => $status,
            'sidebar_items' => $this->getAdminSidebarItems('certificates'),
            'topbar_title' => 'Certificate Applications',
        ]);
    }

    #[Route('/admin/certificates/{id}', name: 'admin_certificates_show', methods: ['GET'])]
    public function adminCertificateShow(User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$user->hasCertificate()) {
            $this->addFlash('error', 'This user has not uploaded a certificate.');
            return $this->redirectToRoute('admin_certificates_index');
        }

        return $this->render('pages/admin/certificates/show.html.twig', [
            'user' => $user,
            'sidebar_items' => $this->getAdminSidebarItems('certificates'),
            'topbar_title' => 'Review Certificate',
        ]);
    }

    #[Route('/admin/certificates/{id}/approve', name: 'admin_certificates_approve', methods: ['POST'])]
    public function adminCertificateApprove(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user->setCertificateStatus('approved');
        $user->recordCertificateApprovedAt(new \DateTime());
        $user->setCertificateReviewNote($request->request->get('note'));
        $user->setRole(UserRole::WORKER);
        $this->entityManager->flush();
        $this->addFlash('success', 'Certificate approved! User is now a freelancer.');

        return $this->redirectToRoute('admin_certificates_index');
    }

    #[Route('/admin/certificates/{id}/reject', name: 'admin_certificates_reject', methods: ['POST'])]
    public function adminCertificateReject(Request $request, User $user): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user->setCertificateStatus('rejected');
        $user->setCertificateReviewNote($request->request->get('note'));
        $this->entityManager->flush();
        $this->addFlash('warning', 'Certificate rejected.');

        return $this->redirectToRoute('admin_certificates_index');
    }

    // ==========================================
    // PRIVATE HELPER METHODS
    // ==========================================

    private function redirectToDashboard(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $role = $user->getRole();

        return match($role) {
            UserRole::ADMIN => $this->redirectToRoute('admin_dashboard'),
            UserRole::WORKER => $this->redirectToRoute('worker_dashboard'),
            default => $this->redirectToRoute('client_dashboard'),
        };
    }

    private function processRegistration(User $user, string $plainPassword, UserRole $role): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPasswordHash($hashedPassword);
        $user->setRole($role);
        $user->setStatus(UserStatus::PENDING); // Pending until email verified
        $user->setEmailVerified(false); // Not verified yet
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    // ==========================================
    // EMAIL VERIFICATION
    // ==========================================

    #[Route('/verify-email-notice', name: 'app_verify_email_notice')]
    public function verifyEmailNotice(): Response
    {
        return $this->render('pages/auth/verify_email_notice.html.twig');
    }

    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET', 'POST'])]
    public function verifyEmail(Request $request): Response
    {
        $userId = $request->query->getInt('user', 0);
        if ($request->isMethod('POST')) {
            $userId = max($userId, (int) $request->request->get('user', 0));
        }
        if (!$userId) {
            return $this->redirectToRoute('app_register');
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->redirectToRoute('app_register');
        }

        if ($user->isEmailVerified()) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');

            if ($this->emailVerification->verifyCode($user, $code)) {
                // Update user status to ACTIVE after email verification
                $user->setStatus(UserStatus::ACTIVE);
                $this->entityManager->flush();

                return $this->redirectToRoute('app_login');
            }
            $this->addFlash('error', 'Invalid or expired verification code.');
        }

        return $this->render('pages/auth/verify_email.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/resend-verification', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): Response
    {
        $userId = $request->request->getInt('user', 0);
        if (!$userId) {
            return $this->redirectToRoute('app_register');
        }

        $user = $this->userRepository->find($userId);
        if (!$user) {
            return $this->redirectToRoute('app_register');
        }

        if ($user->isEmailVerified()) {
            return $this->redirectToRoute('app_login');
        }

        $this->emailVerification->sendVerificationCode($user);
        return $this->redirectToRoute('app_verify_email', [
            'user' => $user->getId(),
        ]);
    }

    private function getAdminSidebarItems(string $active): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('admin_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'Users',
                'url' => $this->generateUrl('admin_users_index'),
                'active' => $active === 'users',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            ],
            [
                'label' => 'Offers Management',
                'url' => $this->generateUrl('admin_offers_index'),
                'active' => $active === 'offers',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>',
            ],
            [
                'label' => 'Service Management',
                'url' => $this->generateUrl('admin_services_index'),
                'active' => $active === 'admin_services',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('admin_contracts_index'),
                'active' => $active === 'contracts',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Tickets',
                'url' => $this->generateUrl('admin_ticket_list'),
                'active' => $active === 'tickets',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-6 4h10M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H7l-4 4v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'label' => 'Worker Categories',
                'url' => $this->generateUrl('admin_categories_index'),
                'active' => $active === 'worker_categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            ],
            [
                'label' => 'Ticket Categories',
                'url' => $this->generateUrl('admin_category_ticket_index'),
                'active' => $active === 'ticket_categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            ],
            [
                'label' => 'Certificates',
                'url' => $this->generateUrl('admin_certificates_index'),
                'active' => $active === 'certificates',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Face Auth Logs',
                'url' => $this->generateUrl('admin_face_logs'),
                'active' => $active === 'face_logs',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
            ],
        ];
    }

    private function getClientSidebarItems(string $active): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('client_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('client_contracts_list'),
                'active' => $active === 'contracts',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Offers',
                'url' => $this->generateUrl('client_offers_index'),
                'active' => $active === 'offers',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Categories',
                'url' => $this->generateUrl('client_categories_index'),
                'active' => $active === 'categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            ],
            [
                'label' => 'Apply as Freelancer',
                'url' => $this->generateUrl('client_apply_freelancer'),
                'active' => $active === 'apply',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>',
            ],
        ];
    }

    private function getWorkerSidebarItems(string $active): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('worker_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'Service Requests',
                'url' => $this->generateUrl('worker_service_requests'),
                'active' => $active === 'service_requests',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>',
            ],
            [
                'label' => 'My Offers',
                'url' => $this->generateUrl('worker_offers_list'),
                'active' => $active === 'offers',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('worker_contracts_list'),
                'active' => $active === 'contracts',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Worker Profile',
                'url' => $this->generateUrl('worker_profiles_index'),
                'active' => $active === 'worker_profile',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>',
            ],
        ];
    }
}

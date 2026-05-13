<?php

namespace App\Controller;

use App\Entity\ServiceRequest;
use App\Entity\WorkerCategory;
use App\Repository\ServiceRequestRepository;
use App\Service\ServiceRequestScoringAi;
use App\Service\ServiceRequestMatchmakingService;
use App\Service\AiMatchmakingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use App\Service\GithubProjectAnalyzer;

class ServiceRequestController extends AbstractController
{
    private function getSidebar(string $active): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('client_dashboard'),
                'active' => $active === 'dashboard',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
            ],
            [
                'label' => 'Services',
                'url' => $this->generateUrl('request_list'), 
                'active' => $active === 'services',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('client_contracts_list'),
                'active' => $active === 'contracts',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
            ],
            [
                'label' => 'Categories',
                'url' => $this->generateUrl('client_categories_index'),
                'active' => $active === 'categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
            ],
        ];
    }
#[Route('/request/new', name: 'request_new', methods: ['GET', 'POST'])]
public function create(
    Request $request,
    EntityManagerInterface $em,
    ServiceRequestMatchmakingService $matchmakingService
): Response
{
    // Fetch categories for dropdown (capped to 200)
    $categories = $em->getRepository(WorkerCategory::class)->createQueryBuilder('c')
        ->orderBy('c.display_order', 'ASC')->addOrderBy('c.name', 'ASC')
        ->setMaxResults(200)->getQuery()->getResult();

    if ($request->isMethod('POST')) {
        $title = $request->request->get('title');
        $description = $request->request->get('description');
        $budgetMin = $request->request->get('budget_min');
        $budgetMax = $request->request->get('budget_max');
        $duration = $request->request->get('duration');
        $categoryId = $request->request->get('category_id'); // From your <select>

        $errors = [];

        // 1. Validation for Title
        if (empty(trim($title)) || strlen($title) < 5 || strlen($title) > 100) {
            $errors[] = 'The title must be at least 5 characters long and no more than 100.';
        }

        if (strlen($description) < 20) {
            $errors[] = 'Please provide a more detailed description (min 20 characters).';
        }

        // 2. Validation for Budget
        if ($budgetMin >= $budgetMax) {
            $errors[] = 'Min budget must be less than Max budget.';
        }

        // 3. Validation for Category (Mandatory)
        $category = $em->getRepository(WorkerCategory::class)->find($categoryId);
        if (!$category) {
            $errors[] = 'Please select a valid worker category.';
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            
            return $this->render('service_request/new.html.twig', [
                'typed_title' => $title,
                'typed_description' => $description,
                'categories' => $categories // Pass them back so the dropdown doesn't disappear
            ]);
        }

        // --- SAVE TO DATABASE ---
        $sr = new ServiceRequest();
        $sr->setTitle($title);
        $sr->setDescription($description);
        $sr->setBudgetMin($budgetMin);
        $sr->setBudgetMax($budgetMax);
        $sr->setDuration((int)$duration);
        $sr->setStatus('OPEN');
        $sr->setLevel($request->request->get('level')); // new

        // Setting the Mandatory Foreign Keys
        $sr->setClient($this->getUser()); // Links the logged-in user
        $sr->setCategory($category);     // Links the selected category

        $em->persist($sr);
        $em->flush();

        // Auto-run matchmaking after creation so matched workers receive offers/notifications immediately.
        $matchmakingService->runMatchmaking($sr);
        
        return $this->redirectToRoute('request_list');
    }

    return $this->render('service_request/new.html.twig', [
        'categories' => $categories,'sidebar_items' => $this->getSidebar('services'),
    ]);
}

#[Route('/requests', name: 'request_list')]
public function list(ServiceRequestRepository $repo): Response
{
    return $this->render('service_request/list.html.twig', [
            'requests' => $repo->findForClientList($this->getUser(), 200),
            'sidebar_items' => $this->getSidebar('services'), // Added sidebar
        ]);
}

#[Route('/requests/{id}/start', name: 'request_start_service', methods: ['POST'])]
public function startService(
    ServiceRequest $serviceRequest,
    ServiceRequestMatchmakingService $matchmakingService
): Response {
    // Allow only the owner client or an admin to start matchmaking
    if ($serviceRequest->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
        throw $this->createAccessDeniedException('You do not have permission to start this service.');
    }

    // Run AI matchmaking (creates pending offers for top freelancers).
    // This is a fire-and-forget action; we don't rely on the session/flash.
    $matchmakingService->runMatchmaking($serviceRequest);

    // Simply redirect back to the list (sessions might be disabled in JWT mode).
    return $this->redirectToRoute('request_list');
}


#[Route('/requests/edit/{id}', name: 'request_edit')]
public function edit(ServiceRequest $serviceRequest, Request $request, EntityManagerInterface $em): Response
{
    // 1. SECURITY CHECK
    if ($serviceRequest->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
        throw $this->createAccessDeniedException('You do not have permission to edit this request.');
    }

    $categories = $em->getRepository(WorkerCategory::class)->createQueryBuilder('c')
        ->orderBy('c.display_order', 'ASC')->addOrderBy('c.name', 'ASC')
        ->setMaxResults(200)->getQuery()->getResult();

    if ($request->isMethod('POST')) {
        // --- CONTROLE DE SAISIE START ---
        $title = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));
        $budgetMin = (float)$request->request->get('budget_min', 0);
        $budgetMax = (float)$request->request->get('budget_max', 0);
        $duration = (int)$request->request->get('duration', 0);
        $categoryId = $request->request->get('category_id');
        $status = $request->request->get('status'); // ENTRY, EXPERT, etc.
        $level = $request->request->get('level');

        $errors = [];

        if (strlen($title) < 5) {
            $errors[] = "Le titre doit faire au moins 5 caractères.";
        }
        if (strlen($description) < 20) {
            $errors[] = "La description est trop courte (min 20 caractères).";
        }
        if ($budgetMin <= 0) {
            $errors[] = "Le budget minimum doit être positif.";
        }
        if ($budgetMax <= $budgetMin) {
            $errors[] = "Le budget maximum doit être supérieur au minimum.";
        }
        if ($duration <= 0) {
            $errors[] = "La durée doit être d'au moins 1 jour.";
        }

        // If errors exist, stop and show them
        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            // Return to view - flash messages will show if your layout handles them
            return $this->render('service_request/edit.html.twig', [
                'serviceRequest' => $serviceRequest,
                'categories' => $categories,
                'sidebar_items' => $this->getSidebar('services'),
            ]);
        }
        // --- CONTROLE DE SAISIE END ---

        // 2. SUCCESS: UPDATE ENTITY (getReference when only setting association)
        if ($categoryId) {
            $serviceRequest->setCategory($em->getReference(WorkerCategory::class, (int) $categoryId));
        }

        $serviceRequest->setStatus('OPEN');

        $serviceRequest->setTitle($title);
        $serviceRequest->setDescription($description);
        $serviceRequest->setBudgetMin($budgetMin);
        $serviceRequest->setBudgetMax($budgetMax);

        $serviceRequest->setDuration($duration);
        $serviceRequest->setLevel($level);


        $em->flush();

        return $this->redirectToRoute('request_list');
    }

    return $this->render('service_request/edit.html.twig', [
        'serviceRequest' => $serviceRequest,
        'categories' => $categories,
        'sidebar_items' => $this->getSidebar('services'),
    ]);
}
#[Route('/request/delete/{id}', name: 'request_delete', methods: ['POST'])]
public function delete(ServiceRequest $sr, EntityManagerInterface $em): Response
{
    if ($sr->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
        throw $this->createAccessDeniedException('You do not have permission to delete this request.');
    }
    $em->remove($sr);
    $em->flush();

    return $this->redirectToRoute('request_list');
}
// Keep this one!
#[Route('/request/{id}', name: 'request_show', methods: ['GET'])]
public function requestDetail(
    ServiceRequest $serviceRequest,
    AiMatchmakingService $aiMatchmakingService,
    ServiceRequestMatchmakingService $matchmakingService
): Response
{
    // Security Check (important!)
    if ($serviceRequest->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
        throw $this->createAccessDeniedException('You do not have permission to view this request.');
    }

    $recommendations = $aiMatchmakingService->getRecommendationsForServiceRequest(
        $serviceRequest,
        $this->getUser()?->getId(),
        ['stage' => 'service_discovery']
    );

    // Backfill old requests that were created before auto-matchmaking existed.
    if ($serviceRequest->getOffers()->count() === 0) {
        $matchmakingService->runMatchmaking($serviceRequest);
    }

    return $this->render('service_request/show.html.twig', [
        'request' => $serviceRequest, // This matches your Twig!
        'ai_recommendations' => $recommendations,
        'sidebar_items' => $this->getSidebar('services'),
    ]);
}

    #[Route('/search', name: 'request_search')]
    public function search(Request $request, ServiceRequestRepository $repo): Response
    {
        $query = $request->query->get('q', '');
        $requests = $repo->findByTitleOnly($query);
        return $this->render('service_request/_list_rows.html.twig', [
            'requests' => $requests,
        ]);
    }



        // ── ROUTE 2 — Call Grok API (AJAX POST) ───────────────────────────────────
        #[Route('/requests/{id}/ai-score', name: 'request_ai_score', methods: ['POST'])]
        public function aiScore(ServiceRequest $serviceRequest, ServiceRequestScoringAi $serviceRequestScoringAi): JsonResponse
        {
            // Security check
            if ($serviceRequest->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                return $this->json(['success' => false, 'error' => 'Access denied.'], 403);
            }

            $result = $serviceRequestScoringAi->scoreServiceRequest($serviceRequest);

            return $this->json($result);
        }


        // ── ROUTE 3 — Apply AI suggestion to DB (AJAX POST) ───────────────────────
        #[Route('/requests/{id}/apply-score', name: 'request_apply_score', methods: ['POST'])]
        public function applyScore(
            ServiceRequest        $serviceRequest,
            Request               $request,
            EntityManagerInterface $em
        ): JsonResponse {
            // Security check
            if ($serviceRequest->getClient() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
                return $this->json(['success' => false, 'error' => 'Access denied.'], 403);
            }

            $data = json_decode($request->getContent(), true);

            $priceMin = $data['price_min'] ?? null;
            $priceMax = $data['price_max'] ?? null;
            $duration = $data['duration'] ?? null;

            // Validate
            if ($priceMin === null || $priceMax === null || $duration === null) {
                return $this->json(['success' => false, 'error' => 'Missing fields.'], 400);
            }
            if ((float)$priceMin >= (float)$priceMax) {
                return $this->json(['success' => false, 'error' => 'price_min must be less than price_max.'], 400);
            }
            if ((int)$duration <= 0) {
                return $this->json(['success' => false, 'error' => 'Duration must be at least 1 day.'], 400);
            }

            // Apply to entity and save
            $serviceRequest->setBudgetMin((string) $priceMin);
            $serviceRequest->setBudgetMax((string) $priceMax);
            $serviceRequest->setDuration((int) $duration);

            $em->flush();

            return $this->json([
                'success'    => true,
                'price_min'  => $serviceRequest->getBudgetMin(),
                'price_max'  => $serviceRequest->getBudgetMax(),
                'duration'   => $serviceRequest->getDuration(),
            ]);
        }
        #[Route('/service-request/github-prefill', name: 'github_prefill', methods: ['GET'])]
    public function githubPrefill(Request $request, GithubProjectAnalyzer $analyzer): JsonResponse
    {
        try {
            $result = $analyzer->analyze($request->query->get('url', ''));
            return $this->json($result);
        } catch (\Throwable $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}

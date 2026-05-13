<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use App\Entity\ServiceRequest;
use App\Entity\ServiceRequirement;
use App\Entity\User;
use App\Entity\WorkerCategory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Metadata\Version\Requirement;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as HttpFoundationRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Enum\UserRole;
use App\Repository\WorkerCategoryRepository;
use App\Repository\ServiceRequestRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ServiceAdminController extends AbstractController
{
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
                'label' => 'User Management',
                'url' => $this->generateUrl('admin_users_index'),
                'active' => $active === 'users',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
            ],
            [
                'label' => 'Offers Management',
                'url' => $this->generateUrl('admin_offers_index'),
                'active' => $active === 'offers',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Service Management',
                'url' => $this->generateUrl('admin_services_index'),
                'active' => $active === 'services',
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
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>',
            ],
            [
                'label' => 'Worker Categories',
                'url' => $this->generateUrl('admin_categories_index'),
                'active' => $active === 'worker_categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>',
            ],
            [
                'label' => 'Ticket Categories',
                'url' => $this->generateUrl('admin_category_ticket_index'),
                'active' => $active === 'ticket_categories',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>',
            ],
            [
                'label' => 'Certificates',
                'url' => $this->generateUrl('admin_certificates_index'),
                'active' => $active === 'certificates',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>',
            ],
            [
                'label' => 'Face Auth Logs',
                'url' => $this->generateUrl('admin_face_logs'),
                'active' => $active === 'face_auth',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>',
            ],
            [
                'label' => 'System Settings',
                'url' => $this->generateUrl('admin_dashboard'),
                'active' => $active === 'settings',
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
            ],
        ];
    }

    #[Route('/admin/services', name: 'admin_services_index')]
    public function index(Request $request, ServiceRequestRepository $repo, WorkerCategoryRepository $categoryRepo): Response
    {
        $search = $request->query->get('q');
        $sort = $request->query->get('sort');
        $catRaw = $request->query->get('category');
        $categoryId = ($catRaw !== null && $catRaw !== '') ? (int) $catRaw : null;
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 20;

        $result = $repo->findForAdminIndex($search, $sort, $categoryId, $page, $limit);

        $totalRequests = $repo->count([]);
        $categories = $categoryRepo->findAllForDropdown();

        return $this->render('service_admin/index.html.twig', [
            'services'      => $result['items'],
            'page'          => $page,
            'total_pages'   => $result['totalPages'],
            'total'         => $result['total'],
            'categories'    => $categories,
            'totalRequests' => $totalRequests,
            'searchTerm'    => $search,
            'stats'         => $repo->getAdminStats(),
            'sidebar_items' => $this->getAdminSidebarItems('services'),
        ]);
    }
    #[Route('/admin/services/view/{id}', name: 'admin_service_view')]
    public function view(ServiceRequest $service): Response
    {
        return $this->render('service_admin/view.html.twig', [
            'service' => $service,
            'sidebar_items' => $this->getAdminSidebarItems('services'),
        ]);
    }

    #[Route('/admin/services/edit/{id}', name: 'admin_service_edit', methods: ['GET', 'POST'])]
    public function edit(
        ServiceRequest $service, 
        Request $request, 
        EntityManagerInterface $em,
        WorkerCategoryRepository $categoryRepo
    ): Response {
        // 1. Fetch categories for the dropdown
        $categories = $categoryRepo->findAllForDropdown();

        if ($request->isMethod('POST')) {
            // --- CONTROLE DE SAISIE (TERMINAL STYLE) ---
            $title = trim($request->request->get('title', ''));
            $description = trim($request->request->get('description', ''));
            $budgetMin = (float)$request->request->get('budget_min', 0);
            $budgetMax = (float)$request->request->get('budget_max', 0);
            $duration = (int)$request->request->get('duration', 0);
            $categoryId = $request->request->get('category'); // Match your select name
            $level = $request->request->get('level');
            $status = 'OPEN'; // Always set to OPEN

            $errors = [];

            if (strlen($title) < 5) {
                $errors[] = "CRITICAL_ERROR: TITLE_TOO_SHORT (MIN_5)";
            }
            if (strlen($description) < 20) {
                $errors[] = "CRITICAL_ERROR: DESCRIPTION_UNMET_REQUIREMENTS (MIN_20)";
            }
            if ($budgetMin <= 0) {
                $errors[] = "Le budget minimum doit être un montant positif.";
            }
            if ($budgetMax <= $budgetMin) {
                $errors[] = "Le budget maximum doit être strictement supérieur au minimum.";
            }
            if ($duration <= 0) {
                $errors[] = "TEMPORAL_ERROR: DURATION_MIN_1_DAY";
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                
                return $this->render('service_admin/adminedit.html.twig', [
                    'service' => $service,
                    'categories' => $categories,
                    'sidebar_items' => $this->getAdminSidebarItems('services'),
                    'old_data' => $request->request->all(), // For sticky fields
                ]);
            }

            // 2. SUCCESS: UPDATE ENTITY (getReference when only setting association)
            if ($categoryId) {
                $service->setCategory($em->getReference(WorkerCategory::class, (int) $categoryId));
            }

            $service->setTitle($title);
            $service->setDescription($description);
            $service->setBudgetMin($budgetMin);
            $service->setBudgetMax($budgetMax);
            $service->setLevel($level);
            $service->setStatus('OPEN'); // always OPEN on update
            
            $service->setDuration($duration);

            $em->flush();

            return $this->redirectToRoute('admin_services_index');
        }

        return $this->render('service_admin/adminedit.html.twig', [
            'service' => $service,
            'categories' => $categories,
            'sidebar_items' => $this->getAdminSidebarItems('services'),
        ]);
    }
        #[Route('/admin/services/delete/{id}', name: 'admin_service_delete', methods: ['POST'])]
    public function delete(ServiceRequest $service, EntityManagerInterface $em): Response
    {
        $em->remove($service);
        $em->flush();

        return $this->redirectToRoute('admin_services_index');
    }
        #[Route('/admin/services/{id}/requirements', name: 'admin_service_requirements')]
    public function viewRequirements(ServiceRequest $service, EntityManagerInterface $em): Response
    {
    // We look for requirements where the 'serviceRequest' property matches the $service object
    // You can also sort them by 'id' or 'position'
    $requirements = $em->getRepository(ServiceRequirement::class)->findBy(
        ['service' => $service], 
        ['id' => 'ASC']
    );

    return $this->render('service_admin/requirements.html.twig', [
        'service' => $service,
        'requirements' => $requirements,
        'sidebar_items' => $this->getAdminSidebarItems('services'),
    ]);
    }
    #[Route('/admin/requirements/delete/{id}', name: 'admin_requirement_delete', methods: ['POST'])]
    public function deleteRequirement(
        ServiceRequirement $requirement, 
        EntityManagerInterface $em, 
        Request $request
    ): Response {
        // Get the Service ID before we delete the requirement so we can redirect back to the same page
        $serviceId = $requirement->getService()->getId();

        // Optional: Add CSRF check for security
        if ($this->isCsrfTokenValid('delete' . $requirement->getId(), $request->request->get('_token'))) {
            $em->remove($requirement);
            $em->flush();
        }

        // Redirect back to the "Cubes" page for this specific service
        return $this->redirectToRoute('admin_service_requirements', ['id' => $serviceId]);
    }

    #[Route('/admin/services/create', name: 'admin_service_create', methods: ['GET', 'POST'])]
    public function create(HttpFoundationRequest $request, EntityManagerInterface $em): Response
    {
        // Fetch categories for the dropdown (capped to 200)
            $categories = $em->getRepository(WorkerCategory::class)->createQueryBuilder('c')
                ->orderBy('c.display_order', 'ASC')->addOrderBy('c.name', 'ASC')
                ->setMaxResults(200)->getQuery()->getResult();

            // Fetch only clients (capped to 200 most recent)
            $users = $em->getRepository(User::class)->createQueryBuilder('u')
                ->where('u.role LIKE :role')
                ->setParameter('role', '%client%')
                ->orderBy('u.firstName', 'ASC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult();

        if ($request->isMethod('POST')) {
            $errors = [];
            
            // 1. Capture Data
            $clientId = $request->request->get('client_id');
            $categoryId = $request->request->get('category_id');
            $title = trim($request->request->get('title'));
            $description = trim($request->request->get('description'));
            $budgetMin = (int)$request->request->get('budget_min');
            $budgetMax = (int)$request->request->get('budget_max');
            $duration = (int)$request->request->get('duration');
            $level = $request->request->get('level');

            // 2. Validation Logic (Contrôle de Saisie)
            if (empty($clientId)) $errors[] = "You must select a client.";
            if (empty($categoryId)) $errors[] = "You must select a category.";
            if (strlen($title) < 5) $errors[] = "The title is too short (min 5 chars).";
            if (strlen($description) < 20) $errors[] = "Please provide a more detailed description (min 20 chars).";
            
            // Budget Logic check
            if ($budgetMin <= 0) $errors[] = "Minimum budget must be greater than 0.";
            if ($budgetMax <= $budgetMin) $errors[] = "Maximum budget must be higher than Minimum budget.";
            
            if ($duration <= 0 || $duration > 365) $errors[] = "Duration must be between 1 and 365 days.";

            // 3. Handle Errors
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                // Return to form with the data so the admin doesn't have to re-type everything
                return $this->render('service_admin/create.html.twig', [
                    'users' => $users,
                    'categories' => $categories,
                    'sidebar_items' => $this->getAdminSidebarItems('services'),
                    'old_data' => $request->request->all() 
                ]);
            }
            $allowedLevels = ['Entry', 'Intermediate', 'Expert', 'Specialist'];
            if (!in_array($level, $allowedLevels)) {
                $level = 'Entry'; // Default fallback
            }

            // 4. Save if valid (getReference avoids full entity load when only setting associations)
            $client = $em->getReference(User::class, (int) $clientId);
            $category = $em->getReference(WorkerCategory::class, (int) $categoryId);

            $service = new ServiceRequest();
            $service->setClient($client);
            $service->setCategory($category);
            $service->setTitle($title);
            $service->setDescription($description);
            $service->setBudgetMin($budgetMin);
            $service->setBudgetMax($budgetMax);
            $service->setDuration($duration);
            $service->setLevel($level); 
            $service->setStatus('OPEN'); // always OPEN on creation

            $em->persist($service);
            $em->flush();

            return $this->redirectToRoute('admin_services_index');
        }

        return $this->render('service_admin/create.html.twig', [
            'users' => $users,
            'categories' => $categories,
            'sidebar_items' => $this->getAdminSidebarItems('services'),
        ]);
    }
    #[Route('/admin/requirements/edit/{id}', name: 'admin_requirement_edit', methods: ['GET', 'POST'])]
    public function editRequirement(ServiceRequirement $requirement, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];
            $title = trim($request->request->get('title'));
            $details = trim($request->request->get('details'));
            
            // --- CONTROLE DE SAISIE ---
            if (strlen($title) < 3) $errors[] = "TITLE_TOO_SHORT_MIN_3";
            if (strlen($details) < 10) $errors[] = "DETAILS_INSUFFICIENT_MIN_10";

            if (count($errors) > 0) {
                foreach ($errors as $error) { $this->addFlash('error', $error); }
            } else {
                $requirement->setTitle($title);
                $requirement->setDetails($details);
                $requirement->setAnswerFormat($request->request->get('answer_format'));
                $requirement->setPriorityLevel((int)$request->request->get('priority_level'));
                $requirement->setIsMandatory($request->request->has('is_mandatory'));

                $em->flush();
                return $this->redirectToRoute('admin_service_requirements', ['id' => $requirement->getService()->getId()]);
            }
        }

        return $this->render('service_admin/edit_requirement.html.twig', [
            'requirement' => $requirement,
            'sidebar_items' => $this->getAdminSidebarItems('services'),
        ]);
    }
    #[Route('/admin/services/{id}/requirements/create', name: 'admin_requirement_create', methods: ['GET', 'POST'])]
    public function createRequirement(ServiceRequest $service, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $errors = [];
        
            // 1. Capture and Clean Data
            $title = trim($request->request->get('title', ''));
            $details = trim($request->request->get('details', ''));

            // 2. Validation Logic (Contrôle de Saisie)
            if (strlen($title) < 5) {
                $errors[] = "TITLE_INVALID: MIN_LENGTH_5_REQUIRED";
            }

            if (strlen($details) < 15) {
                $errors[] = "DETAILS_INVALID: MIN_LENGTH_15_REQUIRED";
            }

            // 3. Handle Errors
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                
                // Re-render the form with errors and the data already typed
                return $this->render('service_admin/create_requirement.html.twig', [
                    'service' => $service,
                    'sidebar_items' => $this->getAdminSidebarItems('services'),
                    'old_data' => $request->request->all()
                ]);
            }

            // 4. If no errors, Proceed to Save
            $requirement = new ServiceRequirement();
            $requirement->setService($service);
            $requirement->setTitle($title);
            $requirement->setDetails($details);
            $requirement->setRequirementType('text'); 
            $requirement->setAnswerFormat($request->request->get('answer_format'));
            $requirement->setPriorityLevel((int)$request->request->get('priority_level'));
            $requirement->setIsMandatory($request->request->has('is_mandatory'));
            $requirement->setOptionsJson([]);

            $em->persist($requirement);
            $em->flush();

            return $this->redirectToRoute('admin_service_requirements', ['id' => $service->getId()]);
        }

        return $this->render('service_admin/create_requirement.html.twig', [
            'service' => $service,
            'sidebar_items' => $this->getAdminSidebarItems('services'),
        ]);
    }
    #[Route('/admin/services/export/excel', name: 'admin_service_export_excel')]
    public function exportExcel(Request $request, ServiceRequestRepository $repo): Response
    {
        // Export up to 500 rows (page=1 with limit=500)
        $result = $repo->findForAdminIndex(
            $request->query->get('q'),
            $request->query->get('sort'),
            $request->query->get('category') ? (int) $request->query->get('category') : null,
            1,
            500
        );
        $services = $result['items'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // 1. Define Professional Headers
        $headers = [
            'ID', 
            'Title', 
            'Username',
            'Email',
            'Category', 
            'Min Budget',    // New
            'Max Budget', 
            'Duration',      // New
            'Status',
            'Level',
            'Description'
        ];
        $sheet->fromArray($headers, null, 'A1');

        // 2. Fill the Data
        $row = 2;
        foreach ($services as $s) {
            $sheet->fromArray([
                $s->getId(),
                $s->getTitle(),
                $s->getClient()->getUsername(),
                $s->getClient()->getEmail(),
                $s->getCategory()->getName(),
                $s->getBudgetMin(),                         // Min Budget
                $s->getBudgetMax(),                         // Max Budget
                $s->getDuration() . ' Days',                // Duration with unit
                $s->getStatus(),
                $s->getLevel(),
                $s->getDescription(),
            ], null, 'A' . $row++);
        }

        // 3. Auto-size columns so it's readable immediately
        foreach (range('A', 'K') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        return $this->streamExcel($writer, 'Service_Requests_Export.xlsx');
    }
    private function streamExcel(Xlsx $writer, string $fileName): StreamedResponse
    {
        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    #[Route('/admin/services/export/pdf', name: 'admin_service_export_pdf')]
    public function exportPdf(Request $request, ServiceRequestRepository $repo): Response
    {
        // Export up to 500 rows (page=1 with limit=500)
        $result = $repo->findForAdminIndex(
            $request->query->get('q'),
            $request->query->get('sort'),
            $request->query->get('category') ? (int) $request->query->get('category') : null,
            1,
            500
        );
        $services = $result['items'];

        $html = $this->renderView('service_admin/export_pdf.html.twig', ['services' => $services]);
        
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="services_report.pdf"'
        ]);
    }
    #[Route('/admin/services/search', name: 'admin_service_search', methods: ['GET'])]
    public function search(
        Request $request,
        ServiceRequestRepository $repo,
        WorkerCategoryRepository $categoryRepo
    ): Response {
        $query      = $request->query->get('q', '');
        $categoryId = $request->query->get('category') ? (int) $request->query->get('category') : null;
        $sort       = $request->query->get('sort', 'date_newest');
        $page       = max(1, (int) $request->query->get('page', 1));

        $result = $repo->findForAdminIndex($query, $sort, $categoryId, $page, 20);

        return $this->render('service_admin/_table_rows.html.twig', [
            'services' => $result['items'],
        ]);
    }




}
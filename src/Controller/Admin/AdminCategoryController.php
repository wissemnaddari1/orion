<?php

namespace App\Controller\Admin;

use App\Entity\WorkerCategory;
use App\Form\WorkerCategoryType;
use App\Repository\WorkerCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/worker-categories')]
final class AdminCategoryController extends BaseController
{
    public function __construct(
        private WorkerCategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
    ) {
    }

    #[Route('', name: 'admin_categories_index')]
    public function index(): Response
    {
        $categories = $this->categoryRepository->findBy([], ['display_order' => 'ASC']);

        return $this->render('pages/admin/category_list.html.twig', [
            'categories' => $categories,
            'sidebar_items' => $this->getAdminSidebarItems('worker_categories'),
            'topbar_title' => 'Worker Categories',
        ]);
    }

    #[Route('/new', name: 'admin_categories_new')]
    public function new(Request $request): Response
    {
        $category = new WorkerCategory();
        $form = $this->createForm(WorkerCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Helper to handle icon upload
            $this->handleIconUpload($form->get('iconFile')->getData(), $category);

            $this->entityManager->persist($category);
            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'La catégorie a été créée avec succès.',
                    'redirectUrl' => $this->generateUrl('admin_categories_index')
                ]);
            }

            $this->addFlash('success', 'Catégorie créée.');
            return $this->redirectToRoute('admin_categories_index');
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
             return new JsonResponse([
                'success' => false,
                'errors' => $this->getFormErrors($form)
            ], 422);
        }

        return $this->render('pages/admin/category_new.html.twig', [
            'form' => $form->createView(),
            'sidebar_items' => $this->getAdminSidebarItems('worker_categories'),
            'topbar_title' => 'New Worker Category',
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'admin_categories_edit')]
    public function edit(int $id, Request $request): Response
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        $form = $this->createForm(WorkerCategoryType::class, $category);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->handleIconUpload($form->get('iconFile')->getData(), $category);

            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Catégorie mise à jour avec succès.',
                    'redirectUrl' => $this->generateUrl('admin_categories_index')
                ]);
            }

            $this->addFlash('success', 'Catégorie mise à jour avec succès.');
            return $this->redirectToRoute('admin_categories_index');
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
            return new JsonResponse([
               'success' => false,
               'errors' => $this->getFormErrors($form)
           ], 422);
       }

            return $this->render('pages/admin/category_edit.html.twig', [
                'category' => $category,
                'form' => $form->createView(),
            'sidebar_items' => $this->getAdminSidebarItems('worker_categories'),
            'topbar_title' => 'Edit Worker Category',
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'admin_categories_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        $category = $this->categoryRepository->find($id);
        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        try {
            if ($category->getIcon() && $category->getIcon() !== 'default-icon') {
                $path = $this->getParameter('kernel.project_dir') . '/public/images/icons/' . $category->getIcon();
                if (file_exists($path)) {
                    unlink($path);
                }
            }

            $this->entityManager->remove($category);
            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Catégorie supprimée avec succès.',
                    'redirectUrl' => $this->generateUrl('admin_categories_index')
                ]);
            }

            $this->addFlash('success', 'Catégorie supprimée.');
        } catch (\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException $e) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Impossible de supprimer cette catégorie car elle est utilisée par des profils travailleurs.'
                ], 400);
            }
            $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle est utilisée.');
        }

        return $this->redirectToRoute('admin_categories_index');
    }

    private function handleIconUpload(?\Symfony\Component\HttpFoundation\File\UploadedFile $iconFile, WorkerCategory $category): void
    {
        if ($iconFile) {
            $oldIcon = $category->getIcon();
            if ($oldIcon && $oldIcon !== 'default-icon') {
                $oldPath = $this->getParameter('kernel.project_dir') . '/public/images/icons/' . $oldIcon;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $originalFilename = pathinfo($iconFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $ext = strtolower(pathinfo($iconFile->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'png');
            $ext = \in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'gif', 'webp'], true) ? $ext : 'png';
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $ext;

            $iconDir = $this->getParameter('kernel.project_dir') . '/public/images/icons';
            if (!is_dir($iconDir)) {
                mkdir($iconDir, 0755, true);
            }

            $iconFile->move($iconDir, $newFilename);
            $category->setIcon($newFilename);
        } elseif (!$category->getIcon()) {
            $category->setIcon('default-icon');
        }
    }

    /**
     * @return array<string, string>
     */
    private function getFormErrors(\Symfony\Component\Form\FormInterface $form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[$error->getOrigin()->getName()] = $error->getMessage();
        }
        return $errors;
    }

    private function getAdminSidebarItems(string $active): array
    {
        return [
            ['label' => 'Dashboard', 'url' => $this->generateUrl('admin_dashboard'), 'active' => $active === 'dashboard', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>'],
            ['label' => 'User Management', 'url' => $this->generateUrl('admin_users_index'), 'active' => $active === 'users', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>'],
            ['label' => 'Offers Management', 'url' => $this->generateUrl('admin_offers_index'), 'active' => $active === 'offers', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>'],
            ['label' => 'Service Management', 'url' => $this->generateUrl('admin_services_index'), 'active' => $active === 'services', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>'],
            ['label' => 'Contracts', 'url' => $this->generateUrl('admin_contracts_index'), 'active' => $active === 'contracts', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'],
            ['label' => 'Tickets', 'url' => $this->generateUrl('admin_ticket_list'), 'active' => $active === 'tickets', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>'],
            ['label' => 'Worker Categories', 'url' => $this->generateUrl('admin_categories_index'), 'active' => $active === 'worker_categories', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>'],
            ['label' => 'Ticket Categories', 'url' => $this->generateUrl('admin_category_ticket_index'), 'active' => $active === 'ticket_categories', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>'],
            ['label' => 'Certificates', 'url' => $this->generateUrl('admin_certificates_index'), 'active' => $active === 'certificates', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>'],
            ['label' => 'Face Auth Logs', 'url' => $this->generateUrl('admin_face_logs'), 'active' => $active === 'face_auth', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>'],
            ['label' => 'System Settings', 'url' => $this->generateUrl('admin_dashboard'), 'active' => $active === 'settings', 'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'],
        ];
    }
}

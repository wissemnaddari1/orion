<?php

namespace App\Controller;

use App\Entity\WorkerCategory;
use App\Repository\WorkerCategoryRepository;
use App\Service\ClientSidebarService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

final class CategoryController extends BaseController
{
    public function __construct(
        private WorkerCategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger,
        private ClientSidebarService $clientSidebar,
    ) {
    }

    private function handleIconUpload(Request $request, ?string $oldIcon = null): ?string
    {
        $iconFile = $request->files->get('icon');
        
        if ($iconFile) {
            // Delete old icon if exists
            if ($oldIcon && $oldIcon !== 'default-icon') {
                $oldPath = $this->getParameter('kernel.project_dir') . '/public/images/icons/' . $oldIcon;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Generate new filename (use client extension to avoid guessExtension() which requires fileinfo)
            $originalFilename = pathinfo($iconFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $this->slugger->slug($originalFilename);
            $ext = strtolower(pathinfo($iconFile->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'png');
            $ext = \in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'gif', 'webp'], true) ? $ext : 'png';
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $ext;

            // Create directory if not exists
            $iconDir = $this->getParameter('kernel.project_dir') . '/public/images/icons';
            if (!is_dir($iconDir)) {
                mkdir($iconDir, 0755, true);
            }

            // Move the file
            $iconFile->move($iconDir, $newFilename);
            
            return $newFilename;
        }

        return $oldIcon;
    }

    #[Route('/category', name: 'app_category')]
    public function oldIndex(): Response
    {
        return $this->redirectToRoute('client_categories_index');
    }

    #[Route('/worker/categories', name: 'worker_categories_index')]
    public function workerIndex(): Response
    {
        return $this->redirectToRoute('client_categories_index');
    }

    #[Route('/client/categories', name: 'client_categories_index')]
    public function index(Request $request): Response
    {
        $q = $request->query->get('q', $request->query->get('search', ''));

        $categories = $this->categoryRepository->searchByNameOrDescription($q ?: null);

        /** @var \Symfony\Component\Security\Core\User\UserInterface|null $user */
        $user = $this->getUser();
        $userName = $user && method_exists($user, 'getFirstName') ? $user->getFirstName() : 'User';

        return $this->render('pages/client/worker_categories.html.twig', [
            'categories' => $categories,
            'q' => $q,
            'search' => $q,
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => 'Categories',
            'user_name' => $userName,
        ]);
    }

    #[Route('/client/categories/{id<\d+>}', name: 'client_categories_detail')]
    public function detail(Request $request, int $id): Response
    {
        $category = $this->categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('Catégorie non trouvée');
        }

        /** @var \Symfony\Component\Security\Core\User\UserInterface|null $user */
        $user = $this->getUser();
        $userName = $user && method_exists($user, 'getFirstName') ? $user->getFirstName() : 'User';

        return $this->render('pages/client/worker_category_detail.html.twig', [
            'category' => $category,
            'workers' => $category->getWorkerProfile(),
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => $category->getName(),
            'user_name' => $userName,
        ]);
    }

    #[Route('/client/categories/new', name: 'client_categories_new')]
    public function new(Request $request): Response
    {
        // Creation is restricted to admins â€” redirect clients to categories list.
        $this->addFlash('error', 'Seules les administrateurs peuvent crÃ©er des catÃ©gories.');
        return $this->redirectToRoute('client_categories_index');
    }

    #[Route('/client/categories/{id<\d+>}/edit', name: 'client_categories_edit')]
    public function edit(int $id, Request $request): Response
    {
        // Editing from client routes is disabled. Redirect to categories list.
        $this->addFlash('error', 'Édition réservée aux administrateurs.');
        return $this->redirectToRoute('client_categories_index');
    }

    #[Route('/client/categories/{id<\d+>}/delete', name: 'client_categories_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        // Deletion from client routes is disabled. Redirect to categories list.
        $this->addFlash('error', 'Suppression réservée aux administrateurs.');
        return $this->redirectToRoute('client_categories_index');
    }
}

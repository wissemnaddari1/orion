<?php

namespace App\Controller;

use App\Entity\CategoryTicket;
use App\Entity\User;
use App\Form\CategoryTicketType;
use App\Repository\CategoryTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin CRUD for ticket categories
 */
#[Route('/admin/ticket-categories')]
#[IsGranted('ROLE_ADMIN')]
class AdminCategoryTicketController extends AbstractController
{
    #[Route('/', name: 'admin_category_ticket_index', methods: ['GET'])]
    public function index(CategoryTicketRepository $categoryRepository): Response
    {
        return $this->render('admin/category/index.html.twig', [
            'categories' => $categoryRepository->findBy([], ['createdAt' => 'DESC']),
            'sidebar_items' => $this->getAdminSidebarItems('ticket_categories'),
            'topbar_title' => 'Ticket Categories',
            'user_name' => $this->resolveCurrentUserName(),
            'notification_count' => 0,
        ]);
    }

    #[Route('/new', name: 'admin_category_ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = new CategoryTicket();
        $form = $this->createForm(CategoryTicketType::class, $category);
        $form->handleRequest($request);

        // SERVER-SIDE VALIDATION (controle de saisie)
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($category);
            $entityManager->flush();

            $this->addFlash('success', 'Category created successfully.');

            return $this->redirectToRoute('admin_category_ticket_index');
        }

        return $this->render('admin/category/new.html.twig', [
            'form' => $form->createView(),
            'sidebar_items' => $this->getAdminSidebarItems('ticket_categories'),
            'topbar_title' => 'Create Category',
            'user_name' => $this->resolveCurrentUserName(),
            'notification_count' => 0,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_category_ticket_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        CategoryTicketRepository $categoryRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $category = $categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('Category not found.');
        }

        $form = $this->createForm(CategoryTicketType::class, $category);
        $form->handleRequest($request);

        // SERVER-SIDE VALIDATION (controle de saisie)
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Category updated successfully.');

            return $this->redirectToRoute('admin_category_ticket_index');
        }

        return $this->render('admin/category/edit.html.twig', [
            'form' => $form->createView(),
            'category' => $category,
            'sidebar_items' => $this->getAdminSidebarItems('ticket_categories'),
            'topbar_title' => 'Edit Category',
            'user_name' => $this->resolveCurrentUserName(),
            'notification_count' => 0,
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
        ];
    }

    #[Route('/{id}/delete', name: 'admin_category_ticket_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        CategoryTicketRepository $categoryRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $category = $categoryRepository->find($id);

        if (!$category) {
            throw $this->createNotFoundException('Category not found.');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_category_'.$category->getId(), $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('admin_category_ticket_index');
        }

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'Category deleted successfully.');

        return $this->redirectToRoute('admin_category_ticket_index');
    }

    private function resolveCurrentUserName(): string
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return 'Admin';
        }

        $fullName = trim((string) $user->getFirstName() . ' ' . (string) $user->getLastName());
        if ($fullName !== '') {
            return $fullName;
        }

        if ((string) $user->getEmail() !== '') {
            return (string) $user->getEmail();
        }

        return 'Admin';
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\BanUserType;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\BanService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/users', name: 'admin_users_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends BaseController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private SluggerInterface $slugger,
        private BanService $banService
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $keyword = trim((string) $request->query->get('search', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $users = $this->userRepository->findForAdminList($keyword, $limit, $offset);
        $total = $this->userRepository->countForAdminList($keyword);
        $totalPages = (int) ceil($total / $limit);

        return $this->render('pages/admin/users/index.html.twig', [
            'users' => $users,
            'search_keyword' => $keyword,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'topbar_title' => 'User Management',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('users'),
        ]);
    }

    private function getAdminSidebarItems(string $active = ''): array
    {
        return [
            [
                'label' => 'Dashboard',
                'url' => $this->generateUrl('admin_dashboard'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
                'active' => $active === 'dashboard',
            ],
            [
                'label' => 'User Management',
                'url' => $this->generateUrl('admin_users_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
                'active' => $active === 'users',
            ],
            [
                'label' => 'Contracts',
                'url' => $this->generateUrl('admin_contracts_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'active' => $active === 'contracts',
            ],
            [
                'label' => 'Tickets',
                'url' => $this->generateUrl('admin_ticket_list'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h6m-6 4h10M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H7l-4 4v10a2 2 0 002 2z"/></svg>',
                'active' => $active === 'tickets',
            ],
            [
                'label' => 'Ticket Categories',
                'url' => $this->generateUrl('admin_category_ticket_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>',
                'active' => $active === 'ticket_categories',
            ],
            [
                'label' => 'Certificates',
                'url' => $this->generateUrl('admin_certificates_index'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>',
                'active' => $active === 'certificates',
            ],
            [
                'label' => 'Face Auth Logs',
                'url' => $this->generateUrl('admin_face_logs'),
                'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
                'active' => $active === 'face_logs',
            ],
        ];
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPasswordHash($hashedPassword);
            }

            $profilePictureFile = $form->get('profilePictureFile')->getData();
            if ($profilePictureFile) {
                $profilePicturePath = $this->uploadProfilePicture($profilePictureFile);
                $user->setProfilePicture('/uploads/profile/' . $profilePicturePath);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', 'User created successfully!');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('pages/admin/users/new.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'topbar_title' => 'Create User',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('users'),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('pages/admin/users/show.html.twig', [
            'user' => $user,
            'topbar_title' => 'User Details',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('users'),
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user): Response
    {
        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
                $user->setPasswordHash($hashedPassword);
            }

            $profilePictureFile = $form->get('profilePictureFile')->getData();
            if ($profilePictureFile) {
                $this->deleteProfilePictureFile($user->getProfilePicture());
                $profilePicturePath = $this->uploadProfilePicture($profilePictureFile);
                $user->setProfilePicture('/uploads/profile/' . $profilePicturePath);
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'User updated successfully!');
            return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
        }

        return $this->render('pages/admin/users/edit.html.twig', [
            'form' => $form->createView(),
            'user' => $user,
            'topbar_title' => 'Edit User',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('users'),
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, User $user): Response
    {
        $this->deleteProfilePictureFile($user->getProfilePicture());

        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'User deleted successfully!');

        return $this->redirectToRoute('admin_users_index');
    }

    #[Route('/{id}/verify-email', name: 'verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request, User $user): Response
    {
        $user->setEmailVerified(true);
        if ($user->getStatus() !== \App\Enum\UserStatus::ACTIVE) {
            $user->setStatus(\App\Enum\UserStatus::ACTIVE);
        }

        $this->entityManager->flush();

        $this->addFlash('success', 'Email verified successfully.');
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    #[Route('/{id}/ban', name: 'ban', methods: ['GET', 'POST'])]
    public function ban(Request $request, User $user): Response
    {
        $form = $this->createForm(BanUserType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $reason = trim((string) $form->get('reason')->getData());
            $note = $form->get('note')->getData() ? trim((string) $form->get('note')->getData()) : null;
            $preset = $form->get('duration_preset')->getData();
            $duration = $this->resolveDuration($preset, $form->get('custom_value')->getData(), $form->get('custom_unit')->getData());

            try {
                /** @var User|null $admin */
                $admin = $this->getUser();
                $this->banService->banUser($user, $admin, $reason, $note, $duration);
                $this->addFlash('success', 'User has been banned.');
                return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('pages/admin/users/ban.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
            'topbar_title' => 'Ban User',
            'user_name' => $this->getUser()?->getFullName() ?? 'Admin',
            'notification_count' => 0,
            'sidebar_items' => $this->getAdminSidebarItems('users'),
        ]);
    }

    #[Route('/{id}/unban', name: 'unban', methods: ['POST'])]
    public function unban(Request $request, User $user): Response
    {
        /** @var User|null $admin */
        $admin = $this->getUser();
        $this->banService->unbanUser($user, $admin, 'Manually lifted by admin');
        $this->addFlash('success', 'User has been unbanned.');
        return $this->redirectToRoute('admin_users_show', ['id' => $user->getId()]);
    }

    private function resolveDuration(?string $preset, mixed $customValue, mixed $customUnit): ?\DateInterval
    {
        if ($preset === BanUserType::PRESET_CUSTOM && $customValue > 0 && $customUnit) {
            $v = (int) $customValue;
            if ($v < 1) {
                return null;
            }
            if ($customUnit === 'hours') {
                return new \DateInterval('PT' . $v . 'H');
            }
            return new \DateInterval('P' . $v . 'D');
        }
        return match ($preset) {
            BanUserType::PRESET_2H => new \DateInterval('PT2H'),
            BanUserType::PRESET_1D => new \DateInterval('P1D'),
            BanUserType::PRESET_2D => new \DateInterval('P2D'),
            BanUserType::PRESET_3D => new \DateInterval('P3D'),
            BanUserType::PRESET_7D => new \DateInterval('P7D'),
            BanUserType::PRESET_30D => new \DateInterval('P30D'),
            default => null,
        };
    }

    /**
     * Helper method to upload files
     */
    private function uploadProfilePicture($file): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        // Use client extension to avoid guessExtension() which requires PHP fileinfo
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'jpg');
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = \in_array($ext, $allowed, true) ? $ext : 'jpg';
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $ext;

        try {
            $targetDirectory = $this->getParameter('user_profile_upload_dir');
            if (!is_dir($targetDirectory)) {
                mkdir($targetDirectory, 0777, true);
            }

            $file->move($targetDirectory, $newFilename);
        } catch (FileException $e) {
            throw new \RuntimeException('Failed to upload file: ' . $e->getMessage());
        }

        return $newFilename;
    }

    private function deleteProfilePictureFile(?string $profilePicturePath): void
    {
        if (!$profilePicturePath) {
            return;
        }

        $fullPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($profilePicturePath, '/');
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

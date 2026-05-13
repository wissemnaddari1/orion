<?php

namespace App\Controller;

use App\Entity\FaceProfile;
use App\Entity\User;
use App\Form\UserType;
use App\Repository\FaceProfileRepository;
use App\Repository\UserBanRepository;
use App\Service\FaceRecognitionClient;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[Route('/profile', name: 'profile_')]
class ProfileController extends BaseController
{
    public function __construct(
        private UserBanRepository $userBanRepository
    ) {
    }

    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get sidebar items based on user role
        $sidebarItems = $this->getSidebarItemsForRole($user);
        $banHistory = $this->userBanRepository->findLastBansForUser($user, 5);

        return $this->render('pages/profile/show.html.twig', [
            'user' => $user,
            'ban_history' => $banHistory,
            'topbar_title' => 'My Profile',
            'notification_count' => 0,
            'sidebar_items' => $sidebarItems,
        ]);
    }

    #[Route('/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        // Get sidebar items based on user role
        $sidebarItems = $this->getSidebarItemsForRole($user);

        $form = $this->createForm(UserType::class, $user, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle password change
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
                $user->setPassword($hashedPassword);
            }

            // Handle profile picture upload
            $profilePictureFile = $form->get('profilePictureFile')->getData();
            if ($profilePictureFile) {
                $fileExtension = $this->getSafeImageExtension($profilePictureFile);
                if (!$fileExtension) {
                    $profilePictureFile = null;
                }
            }
            if ($profilePictureFile) {
                $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $fileExtension;

                try {
                    // Delete old profile picture if exists
                    if ($user->getProfilePicture()) {
                        $oldPicturePath = $this->getParameter('kernel.project_dir') . '/public/uploads/profile/' . basename($user->getProfilePicture());
                        if (file_exists($oldPicturePath)) {
                            @unlink($oldPicturePath);
                        }
                    }

                    $profilePictureFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/profile',
                        $newFilename
                    );

                    $user->setProfilePicture($newFilename);
                } catch (FileException $e) {
                }
            }

            $entityManager->flush();

            return $this->redirectToRoute('profile_show');
        }

        return $this->render('pages/profile/edit.html.twig', [
            'form' => $form,
            'user' => $user,
            'topbar_title' => 'Edit Profile',
            'notification_count' => 0,
            'sidebar_items' => $sidebarItems,
        ]);
    }

    /**
     * Get sidebar items based on user's role
     */
    private function getSidebarItemsForRole(User $user): array
    {
        $roles = $user->getRoles();

        // Admin sidebar
        if (in_array('ROLE_ADMIN', $roles)) {
            return [
                [
                    'label' => 'Dashboard',
                    'url' => $this->generateUrl('admin_dashboard'),
                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>',
                    'active' => false,
                ],
                [
                    'label' => 'User Management',
                    'url' => $this->generateUrl('admin_users_index'),
                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
                    'active' => false,
                ],
                [
                    'label' => 'Face Auth Logs',
                    'url' => $this->generateUrl('admin_face_logs'),
                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>',
                    'active' => false,
                ],
                [
                    'label' => 'System Settings',
                    'url' => '#',
                    'icon' => '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
                    'active' => false,
                ],
            ];
        }

        // Worker sidebar
        if (in_array('ROLE_WORKER', $roles)) {
            return [
                ['label' => 'Dashboard', 'path' => '#', 'active' => false],
                ['label' => 'My Jobs', 'path' => '#', 'active' => false],
                ['label' => 'Available Projects', 'path' => '#', 'active' => false],
                ['label' => 'Contracts', 'path' => '#', 'active' => false],
                ['label' => 'Earnings', 'path' => '#', 'active' => false],
                ['label' => 'Support', 'path' => '#', 'active' => false],
            ];
        }

        // Client sidebar (default)
        return [
            ['label' => 'Dashboard', 'path' => $this->generateUrl('client_dashboard'), 'active' => false],
            ['label' => 'Offers', 'path' => '#', 'active' => false],
            ['label' => 'Negotiations', 'path' => '#', 'active' => false],
            ['label' => 'Contracts', 'path' => '#', 'active' => false],
            ['label' => 'Earnings', 'path' => '#', 'active' => false],
            ['label' => 'Support tickets', 'path' => '#', 'active' => false],
        ];
    }

    #[Route('/picture/upload', name: 'picture_upload', methods: ['POST'])]
    public function uploadProfilePicture(
        Request $request,
        SluggerInterface $slugger,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        $profilePictureFile = $request->files->get('profile_picture');

        if (!$profilePictureFile) {
            return $this->redirectToReferer($request);
        }

        // Validate file type
        $fileExtension = $this->getSafeImageExtension($profilePictureFile);
        if (!$fileExtension) {
            return $this->redirectToReferer($request);
        }

        // Validate file size (5MB max)
        if ($profilePictureFile->getSize() > 5 * 1024 * 1024) {
            return $this->redirectToReferer($request);
        }

        $originalFilename = pathinfo($profilePictureFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $fileExtension;

        try {
            // Delete old profile picture if exists
            if ($user->getProfilePicture()) {
                $oldPicturePath = $this->getParameter('kernel.project_dir') . '/public/uploads/profile/' . basename($user->getProfilePicture());
                if (file_exists($oldPicturePath)) {
                    @unlink($oldPicturePath);
                }
            }

            // Upload new picture
            $profilePictureFile->move(
                $this->getParameter('kernel.project_dir') . '/public/uploads/profile',
                $newFilename
            );

            // Update user entity
            $user->setProfilePicture($newFilename);
            $entityManager->flush();
        } catch (FileException $e) {
        }

        return $this->redirectToReferer($request);
    }

    #[Route('/picture/remove', name: 'picture_remove', methods: ['POST'])]
    public function removeProfilePicture(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        if ($user->getProfilePicture()) {
            // Delete file from disk
            $picturePath = $this->getParameter('kernel.project_dir') . '/public/uploads/profile/' . basename($user->getProfilePicture());
            if (file_exists($picturePath)) {
                @unlink($picturePath);
            }

            // Update user entity
            $user->setProfilePicture(null);
            $entityManager->flush();
        }

        return $this->redirectToReferer($request);
    }

    private function redirectToReferer(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        
        if ($referer) {
            return $this->redirect($referer);
        }

        // Fallback based on user role
        /** @var User $user */
        $user = $this->getUser();
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->redirectToRoute('admin_dashboard');
        } elseif (in_array('ROLE_WORKER', $roles)) {
            return $this->redirectToRoute('worker_dashboard');
        } else {
            return $this->redirectToRoute('client_dashboard');
        }
    }

    private function getSafeImageExtension(UploadedFile $file): ?string
    {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower((string) $file->getClientOriginalExtension());
        if ($extension && in_array($extension, $allowedExtensions, true)) {
            return $extension;
        }

        $mimeToExtension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $clientMimeType = $file->getClientMimeType();
        if ($clientMimeType && isset($mimeToExtension[$clientMimeType])) {
            return $mimeToExtension[$clientMimeType];
        }

        return null;
    }

    #[Route('/face/enroll', name: 'face_enroll', methods: ['POST'])]
    public function enrollFace(
        Request $request,
        FaceRecognitionClient $faceRecognitionClient,
        FaceProfileRepository $faceProfileRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->json(['ok' => false, 'message' => 'Invalid request payload.'], 400);
        }

        $imageDataUrl = (string) ($payload['image'] ?? '');
        $capturedBase64 = $this->extractBase64($imageDataUrl);

        if ($capturedBase64 === '') {
            return $this->json(['ok' => false, 'message' => 'Image is required.'], 400);
        }

        if (!$faceRecognitionClient->isAvailable()) {
            return $this->json([
                'ok' => false,
                'message' => 'Face recognition service is not running. Start it from the project root: python start_ai_services.py (face on port 5000) or: python -m uvicorn ai_face_service.main:app --host 127.0.0.1 --port 5000',
            ], 503);
        }

        try {
            $embedResult = $faceRecognitionClient->embed($capturedBase64);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->json(['ok' => false, 'message' => 'Face enrollment temporarily unavailable. Please try again.'], 500);
        }

        $embedding = $embedResult['embedding'] ?? [];
        if (empty($embedding)) {
            return $this->json(['ok' => false, 'message' => 'No face embedding obtained.'], 422);
        }

        $faceProfile = $faceProfileRepository->findOneByUser($user->getId());
        if ($faceProfile === null) {
            $faceProfile = new FaceProfile();
            $faceProfile->setUser($user);
            $entityManager->persist($faceProfile);
        }
        $faceProfile->setEmbedding($embedding);

        $user->recordFaceEnrolledAt(new \DateTime());
        $user->resetFaceFailedAttempts();
        $entityManager->flush();

        return $this->json([
            'ok' => true,
            'message' => 'Face enrolled successfully! You can now use face login.',
        ]);
    }

    #[Route('/face/remove', name: 'face_remove', methods: ['POST'])]
    public function removeFace(
        EntityManagerInterface $entityManager,
        FaceProfileRepository $faceProfileRepository
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $facePath = $user->getFaceImagePath();
        if ($facePath) {
            $fullPath = $this->getParameter('kernel.project_dir') . '/public/' . ltrim($facePath, '/');
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            $user->setFaceImagePath(null);
        }

        $faceProfile = $faceProfileRepository->findOneByUser((int) $user->getId());
        if ($faceProfile !== null) {
            $entityManager->remove($faceProfile);
        }

        $user->recordFaceEnrolledAt(null);
        $user->recordFaceLastVerified(null);
        $user->resetFaceFailedAttempts();
        $entityManager->flush();

        return $this->json(['ok' => true, 'message' => 'Face enrollment removed.']);
    }

    #[Route('/face', name: 'face_settings', methods: ['GET'])]
    public function faceSettings(FaceProfileRepository $faceProfileRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $sidebarItems = $this->getSidebarItemsForRole($user);
        $faceProfile = $faceProfileRepository->findOneByUser((int) $user->getId());

        return $this->render('pages/profile/face_settings.html.twig', [
            'user' => $user,
            'faceProfile' => $faceProfile,
            'topbar_title' => 'Face Login Settings',
            'notification_count' => 0,
            'sidebar_items' => $sidebarItems,
        ]);
    }

    private function extractBase64(string $dataUrl): string
    {
        if (str_starts_with($dataUrl, 'data:')) {
            $parts = explode(',', $dataUrl, 2);
            return $parts[1] ?? '';
        }

        return $dataUrl;
    }
}

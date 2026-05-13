<?php

namespace App\Controller;

use App\Entity\WorkerProfile;
use App\Entity\User;
use App\Form\WorkerProfileType;
use App\Repository\WorkerCategoryRepository;
use App\Repository\WorkerProfileRepository;
use App\Service\CvParserService;
use App\Service\CvUploadService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/worker/profiles')]
final class WorkerProfileController extends BaseController
{
    public function __construct(
        private WorkerProfileRepository $profileRepository,
        private WorkerCategoryRepository $categoryRepository,
        private EntityManagerInterface $entityManager,
        private CvUploadService $cvUploadService,
        private CvParserService $cvParserService,
    ) {
    }

    #[Route('', name: 'worker_profiles_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $user = $this->getCurrentUser();
        $profiles = $this->profileRepository->findBy(
            ['user' => $user],
            ['created_at' => 'DESC']
        );

        return $this->render('pages/worker/worker_profiles_list.html.twig', [
            'profiles' => $profiles,
        ]);
    }

    #[Route('/me', name: 'worker_profile_me')]
    public function me(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $profile = $this->profileRepository->findOneBy(['user' => $this->getCurrentUser()]);

        if (!$profile) {
            return $this->redirectToRoute('worker_profiles_new');
        }

        return $this->render('pages/worker/worker_profile_show.html.twig', [
            'profile' => $profile,
            'is_public_view' => false,
        ]);
    }

    #[Route('/new', name: 'worker_profiles_new')]
    public function new(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $existingProfile = $this->profileRepository->findOneBy(['user' => $this->getCurrentUser()]);
        if ($existingProfile) {
            return $this->redirectToRoute('worker_profiles_edit', ['id' => $existingProfile->getId()]);
        }
        $profile = new WorkerProfile();
        $profile->setUser($this->getCurrentUser());

        $form = $this->createForm(WorkerProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            


            $this->entityManager->persist($profile);
            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Votre profil a Ã©tÃ© crÃ©Ã© avec succÃ¨s.',
                    'redirectUrl' => $this->generateUrl('worker_profile_me')
                ]);
            }

            $this->addFlash('success', 'Profil crÃ©Ã©.');
            return $this->redirectToRoute('worker_profile_me');
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
            return new JsonResponse([
               'success' => false,
               'errors' => $this->getFormErrors($form)
           ], 422);
       }

        return $this->render('pages/worker/worker_profile_new.html.twig', [
            'form' => $form->createView(),
            'categories' => $this->categoryRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id<\d+>}', name: 'worker_profiles_show')]
    public function show(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $profile = $this->profileRepository->find($id);
        if (!$profile) {
            throw $this->createNotFoundException('Profil non trouvÃ©');
        }
        $this->assertOwnProfile($profile);

        return $this->render('pages/worker/worker_profile_show.html.twig', [
            'profile' => $profile,
            'is_public_view' => false,
        ]);
    }

    /**
     * Public view of a worker profile (e.g. for clients viewing matched freelancers).
     * Any authenticated user can view.
     */
    #[Route('/view/{id<\d+>}', name: 'worker_profile_view', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function view(int $id): Response
    {
        $profile = $this->profileRepository->find($id);
        if (!$profile) {
            throw $this->createNotFoundException('Profil non trouvÃ©');
        }

        return $this->render('pages/worker/worker_profile_show.html.twig', [
            'profile' => $profile,
            'is_public_view' => true,
        ]);
    }

    #[Route('/{id<\d+>}/edit', name: 'worker_profiles_edit')]
    public function edit(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $profile = $this->profileRepository->find($id);
        if (!$profile) {
            throw $this->createNotFoundException('Profil non trouvÃ©');
        }
        $this->assertOwnProfile($profile);

        $form = $this->createForm(WorkerProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {


            $this->entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Votre profil a Ã©tÃ© mis Ã  jour.',
                    'redirectUrl' => $this->generateUrl('worker_profile_me')
                ]);
            }

            $this->addFlash('success', 'Profil mis Ã  jour.');
            return $this->redirectToRoute('worker_profile_me');
        }

        if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
            return new JsonResponse([
               'success' => false,
               'errors' => $this->getFormErrors($form)
           ], 422);
       }

        return $this->render('pages/worker/worker_profile_edit.html.twig', [
            'profile' => $profile,
            'form' => $form->createView(),
            'categories' => $this->categoryRepository->findBy([], ['name' => 'ASC']),
        ]);
    }

    #[Route('/{id<\d+>}/delete', name: 'worker_profiles_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $profile = $this->profileRepository->find($id);
        if (!$profile) {
            throw $this->createNotFoundException('Profil non trouvÃ©');
        }
        $this->assertOwnProfile($profile);

        $this->entityManager->remove($profile);
        $this->entityManager->flush();

        $this->addFlash('success', 'Profil supprimÃ©.');
        return $this->redirectToRoute('worker_profiles_new');
    }

    #[Route('/parse-cv', name: 'worker_profiles_parse_cv', methods: ['POST'])]
    public function parseCv(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');

        $uploadedFile = $request->files->get('cv_file');
        if (!$uploadedFile) {
            return $this->json([
                'success' => false,
                'error' => 'No CV file uploaded.',
            ], 400);
        }

        if ($uploadedFile->getSize() > $this->cvUploadService->getMaxFileSize()) {
            return $this->json([
                'success' => false,
                'error' => 'File too large. Maximum size is 10MB.',
            ], 400);
        }

        $mimeType = (string) $uploadedFile->getMimeType();
        if (!in_array($mimeType, $this->cvUploadService->getAllowedMimeTypes(), true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid file type. Please upload PDF, DOC, DOCX, JPG, or PNG.',
            ], 400);
        }

        $relativePath = null;
        try {
            $relativePath = $this->cvUploadService->upload($uploadedFile);
            $fullPath = $this->cvUploadService->getFullPath($relativePath);

            $result = $this->cvParserService->parseCV($fullPath);

            if (!$result['success']) {
                return $this->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to parse CV. You can still fill the form manually.',
                ], 502);
            }

            return $this->json([
                'success' => true,
                'data' => $result['data'],
                'confidence' => $result['confidence'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'success' => false,
                'error' => 'Failed to upload CV. Please try again or fill the form manually.',
            ], 500);
        } finally {
            if ($relativePath) {
                $this->cvUploadService->delete($relativePath);
            }
        }
    }

    private function getFormErrors($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[$error->getOrigin()->getName()] = $error->getMessage();
        }
        return $errors;
    }

    private function getCurrentUserId(): int
    {
        return (int) $this->getCurrentUser()->getId();
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not authenticated.');
        }

        return $user;
    }

    private function assertOwnProfile(WorkerProfile $profile): void
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if ($profile->getUserId() !== $this->getCurrentUserId()) {
            throw $this->createAccessDeniedException('Not your profile.');
        }
    }
}

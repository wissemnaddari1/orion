<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\Conversation;
use App\Entity\Milestone;
use App\Entity\Offer;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Form\OfferType;
use App\Repository\ContractRepository;
use App\Repository\MilestoneRepository;
use App\Repository\OfferRepository;
use App\Repository\ServiceRequestRepository;
use App\Service\ContractPdfService;
use App\Service\OfferMailerService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/worker')]
final class WorkerController extends BaseController
{
    #[Route('', name: 'worker_home')]
    public function workerHome(): Response
    {
        return $this->redirectToRoute('worker_dashboard');
    }

    // ==================== CONTRACTS ====================

    #[Route('/contracts', name: 'worker_contracts_list')]
    public function contractsList(ContractRepository $contractRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        /** @var User $user */
        $user = $this->getUser();
        $contracts = $contractRepository->findBy(['worker' => $user], ['createdAt' => 'DESC']);

        return $this->render('pages/worker/contracts_list.html.twig', [
            'contracts' => $contracts,
            'sidebar_items' => $this->getWorkerSidebarItems('contracts'),
            'topbar_title' => 'My Contracts',
        ]);
    }

    /* â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ CREATE (form) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

    public function contractCreate(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->addFlash('error', 'Contract creation is disabled for workers. Contracts are auto-generated from accepted offers.');

        return $this->redirectToRoute('worker_contracts_list');
    }

    public function contractStore(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->addFlash('error', 'Contract creation is disabled for workers. Contracts are auto-generated from accepted offers.');

        return $this->redirectToRoute('worker_contracts_list');
    }

    #[Route('/contracts/{id}', name: 'worker_contract_show', requirements: ['id' => '\d+'])]
    public function contractShow(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);

        return $this->render('pages/worker/contract_show.html.twig', [
            'contract' => $contract,
            'sidebar_items' => $this->getWorkerSidebarItems('contracts'),
            'topbar_title' => $contract->getTitle() ?: ('Contract #' . $contract->getId()),
        ]);
    }

    #[Route('/contracts/{id}/pdf', name: 'worker_contract_pdf', requirements: ['id' => '\d+'])]
    public function contractPdf(Contract $contract, ContractPdfService $pdfService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);

        $pdfContent = $pdfService->generate($contract);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="contract_%d.pdf"', $contract->getId()),
        ]);
    }

    public function contractEdit(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);

        $this->addFlash('error', 'Contract editing is disabled for workers. Please contact admin.');

        return $this->redirectToRoute('worker_contract_show', ['id' => $contract->getId()]);
    }

    public function contractUpdate(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);

        $this->addFlash('error', 'Contract editing is disabled for workers. Please contact admin.');

        return $this->redirectToRoute('worker_contract_show', ['id' => $contract->getId()]);
    }

    // ==================== ESIGN ====================

    #[Route('/contracts/{id}/sign', name: 'worker_contract_sign', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function contractSign(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);
        if (!$contract->canBeSigned()) {
            $this->addFlash('error', 'This contract cannot be signed.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contract->getId()]);
        }
        if ($contract->isWorkerSigned()) {
            $this->addFlash('info', 'You have already signed this contract.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contract->getId()]);
        }

        return $this->render('pages/worker/contract_sign.html.twig', [
            'contract' => $contract,
            'sidebar_items' => $this->getWorkerSidebarItems('contracts'),
            'topbar_title' => 'Sign Contract',
        ]);
    }

    #[Route('/contracts/{id}/sign/submit', name: 'worker_contract_sign_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractSignSubmit(Contract $contract, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);
        if (!$contract->canBeSigned() || $contract->isWorkerSigned()) {
            $this->addFlash('error', 'Cannot sign this contract.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contract->getId()]);
        }

        $signatureData = $request->request->get('signature_data');
        if (empty($signatureData)) {
            $this->addFlash('error', 'Please provide your signature.');
            return $this->redirectToRoute('worker_contract_sign', ['id' => $contract->getId()]);
        }

        $contract->signByWorker($signatureData, $request->getClientIp() ?? '0.0.0.0');
        $em->flush();

        // Ensure messagerie conversation exists once both sides signed
        if ($contract->isFullySigned()) {
            $repo = $em->getRepository(Conversation::class);
            $existing = $repo->findOneBy(['contract' => $contract]);
            if ($existing === null) {
                $conv = new Conversation();
                $conv->setContract($contract);
                $conv->setClient($contract->getClient());
                $conv->setWorker($contract->getWorker());
                $em->persist($conv);
                $em->flush();
            }
        }

        $this->addFlash('success', 'Contract signed successfully!' . ($contract->isFullySigned() ? ' The contract is now active.' : ' Waiting for client signature.'));
        return $this->redirectToRoute('worker_contract_show', ['id' => $contract->getId()]);
    }

    // ==================== MILESTONES ====================

    #[Route('/contracts/{id}/milestones/new', name: 'worker_milestone_new', requirements: ['id' => '\d+'])]
    public function milestoneNew(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);

        return $this->render('pages/worker/milestone_new.html.twig', [
            'contract' => $contract,
            'errors' => [],
            'old' => [],
            'sidebar_items' => $this->getWorkerSidebarItems('contracts'),
            'topbar_title' => 'New Milestone',
        ]);
    }

    #[Route('/contracts/{id}/milestones/create', name: 'worker_milestone_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function milestoneCreate(Contract $contract, Request $request, EntityManagerInterface $em, MilestoneRepository $milestoneRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $this->assertWorkerOwns($contract);

        if (!$this->isCsrfTokenValid('milestone_create', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
            return $this->redirectToRoute('worker_milestone_new', ['id' => $contract->getId()]);
        }

        $title       = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));
        $dueDate     = $request->request->get('due_date', '');
        $orderIndex  = $request->request->get('order_index', '');
        $status      = $request->request->get('status', Milestone::STATUS_PENDING);
        $amount      = $request->request->get('amount');

        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'Title must be at least 3 characters.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'Title must not exceed 200 characters.';
        } elseif (preg_match('/<[^>]*>/', $title)) {
            $errors['title'] = 'Title must not contain HTML tags.';
        } elseif (!preg_match('/[a-zA-ZÃ€-Ã¿]/', $title)) {
            $errors['title'] = 'Title must contain at least one letter.';
        } elseif (preg_match('/(.)\\1{4,}/', $title)) {
            $errors['title'] = 'Title contains suspicious repeated characters.';
        }

        if ($description !== '') {
            if (mb_strlen($description) > 2000) {
                $errors['description'] = 'Description must not exceed 2000 characters.';
            } elseif (preg_match('/<[^>]*>/', $description)) {
                $errors['description'] = 'Description must not contain HTML tags.';
            } elseif (preg_match('/(.)\\1{9,}/', $description)) {
                $errors['description'] = 'Description contains suspicious repeated text.';
            }
        }

        if ($dueDate === '') {
            $errors['due_date'] = 'Due date is required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) || !strtotime($dueDate)) {
            $errors['due_date'] = 'Invalid date format (YYYY-MM-DD).';
        } elseif (strtotime($dueDate) < strtotime(date('Y-m-d'))) {
            $errors['due_date'] = 'Due date cannot be in the past.';
        } elseif ($contract->getEndDate() && strtotime($dueDate) > $contract->getEndDate()->getTimestamp()) {
            $errors['due_date'] = 'Due date cannot exceed contract end date.';
        } elseif ($contract->getStartDate() && strtotime($dueDate) < $contract->getStartDate()->getTimestamp()) {
            $errors['due_date'] = 'Due date cannot be before contract start date.';
        }

        if ($orderIndex === '' || $orderIndex === null) {
            $errors['order_index'] = 'Order is required.';
        } elseif (!is_numeric($orderIndex) || (int) $orderIndex < 1) {
            $errors['order_index'] = 'Order must be a positive integer.';
        } elseif ((int) $orderIndex > 100) {
            $errors['order_index'] = 'Order cannot exceed 100.';
        } elseif ((int) $orderIndex != $orderIndex) {
            $errors['order_index'] = 'Order must be a whole number.';
        } else {
            $existing = $milestoneRepository->findOneBy(['contract' => $contract, 'orderIndex' => (int) $orderIndex]);
            if ($existing) {
                $errors['order_index'] = 'Order ' . (int) $orderIndex . ' is already used by another milestone.';
            }
        }

        $validStatuses = [Milestone::STATUS_PENDING, Milestone::STATUS_IN_PROGRESS, Milestone::STATUS_REVISION_REQUESTED, Milestone::STATUS_CANCELLED];
        if (!in_array($status, $validStatuses, true)) {
            $errors['status'] = 'Invalid status.';
        }

        if ($amount !== null && $amount !== '') {
            if (!is_numeric($amount) || (float) $amount < 0) {
                $errors['amount'] = 'Amount must be a positive number.';
            } elseif ((float) $amount > 9999999.99) {
                $errors['amount'] = 'Amount must not exceed 9,999,999.99.';
            } elseif (preg_match('/\.\d{3,}/', (string) $amount)) {
                $errors['amount'] = 'Amount can have at most 2 decimal places.';
            } elseif ($contract->getAgreedPrice() && (float) $amount > (float) $contract->getAgreedPrice()) {
                $errors['amount'] = 'Amount cannot exceed contract price.';
            }
        }

        if (!isset($errors['amount']) && $amount !== null && $amount !== '' && $contract->getAgreedPrice() !== null) {
            $contractTotalCents = $this->moneyToCents($contract->getAgreedPrice());
            $existingMilestonesCents = $this->sumMilestoneAmountsCents($contract);
            $newMilestoneCents = $this->moneyToCents((string) $amount);

            if (($existingMilestonesCents + $newMilestoneCents) > $contractTotalCents) {
                $errors['amount'] = 'Total milestone amounts cannot exceed contract price.';
            }
        }

        if (!empty($errors)) {
            return $this->render('pages/worker/milestone_new.html.twig', [
                'contract' => $contract,
                'errors' => $errors,
                'old' => [
                    'title' => $title,
                    'description' => $description,
                    'due_date' => $dueDate,
                    'order_index' => $orderIndex,
                    'status' => $status,
                    'amount' => $amount,
                ],
                'sidebar_items' => $this->getWorkerSidebarItems('contracts'),
                'topbar_title' => 'New Milestone',
            ]);
        }

        $milestone = new Milestone();
        $milestone->setContract($contract);
        $milestone->setTitle($title);
        $milestone->setDescription($description ?: null);
        $milestone->setDueDate(new \DateTime($dueDate));
        $milestone->setOrderIndex((int) $orderIndex);
        $milestone->setStatus($status);

        if ($amount !== null && $amount !== '') {
            $milestone->setAmount($amount);
        }

        $em->persist($milestone);
        $em->flush();

        $this->addFlash('success', 'Milestone created successfully!');
        return $this->redirectToRoute('worker_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{contractId}/milestones/{milestoneId}/edit', name: 'worker_milestone_edit', requirements: ['contractId' => '\d+', 'milestoneId' => '\d+'])]
    public function milestoneEdit(int $contractId, int $milestoneId, ContractRepository $contractRepository, MilestoneRepository $milestoneRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $contract = $contractRepository->find($contractId);
        $milestone = $milestoneRepository->find($milestoneId);

        if (!$contract || !$milestone || $milestone->getContract()->getId() !== $contract->getId()) {
            throw $this->createNotFoundException('Milestone not found');
        }
        $this->assertWorkerOwns($contract);
        if (in_array($milestone->getStatus(), [Milestone::STATUS_DELIVERED, Milestone::STATUS_COMPLETED], true)) {
            $this->addFlash('error', 'Delivered or approved milestones cannot be edited directly.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
        }

        return $this->render('pages/worker/milestone_edit.html.twig', [
            'contract' => $contract,
            'milestone' => $milestone,
            'errors' => [],
            'old' => [],
            'sidebar_items' => $this->getWorkerSidebarItems('contracts'),
            'topbar_title' => 'Edit Milestone',
        ]);
    }

    #[Route('/contracts/{contractId}/milestones/{milestoneId}/update', name: 'worker_milestone_update', methods: ['POST'], requirements: ['contractId' => '\d+', 'milestoneId' => '\d+'])]
    public function milestoneUpdate(int $contractId, int $milestoneId, Request $request, ContractRepository $contractRepository, MilestoneRepository $milestoneRepository, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $contract = $contractRepository->find($contractId);
        $milestone = $milestoneRepository->find($milestoneId);

        if (!$milestone || !$contract || $milestone->getContract()->getId() !== $contractId) {
            throw $this->createNotFoundException('Milestone not found');
        }
        $this->assertWorkerOwns($contract);

        if (!$this->isCsrfTokenValid('milestone_update_' . $milestoneId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
            return $this->redirectToRoute('worker_milestone_edit', ['contractId' => $contractId, 'milestoneId' => $milestoneId]);
        }

        $title       = trim($request->request->get('title', ''));
        $description = trim($request->request->get('description', ''));
        $dueDate     = $request->request->get('due_date', '');
        $orderIndex  = $request->request->get('order_index', '');
        $status      = $request->request->get('status', '');
        $amount      = $request->request->get('amount');

        $errors = [];

        if ($title === '') {
            $errors['title'] = 'Title is required.';
        } elseif (mb_strlen($title) < 3) {
            $errors['title'] = 'Title must be at least 3 characters.';
        } elseif (mb_strlen($title) > 200) {
            $errors['title'] = 'Title must not exceed 200 characters.';
        } elseif (preg_match('/<[^>]*>/', $title)) {
            $errors['title'] = 'Title must not contain HTML tags.';
        } elseif (!preg_match('/[a-zA-ZÃ€-Ã¿]/', $title)) {
            $errors['title'] = 'Title must contain at least one letter.';
        } elseif (preg_match('/(.)\\1{4,}/', $title)) {
            $errors['title'] = 'Title contains suspicious repeated characters.';
        }

        if ($description !== '') {
            if (mb_strlen($description) > 2000) {
                $errors['description'] = 'Description must not exceed 2000 characters.';
            } elseif (preg_match('/<[^>]*>/', $description)) {
                $errors['description'] = 'Description must not contain HTML tags.';
            } elseif (preg_match('/(.)\\1{9,}/', $description)) {
                $errors['description'] = 'Description contains suspicious repeated text.';
            }
        }

        if ($dueDate === '') {
            $errors['due_date'] = 'Due date is required.';
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate) || !strtotime($dueDate)) {
            $errors['due_date'] = 'Invalid date format (YYYY-MM-DD).';
        } elseif ($contract->getEndDate() && strtotime($dueDate) > $contract->getEndDate()->getTimestamp()) {
            $errors['due_date'] = 'Due date cannot exceed contract end date.';
        } elseif ($contract->getStartDate() && strtotime($dueDate) < $contract->getStartDate()->getTimestamp()) {
            $errors['due_date'] = 'Due date cannot be before contract start date.';
        }

        if ($orderIndex === '' || $orderIndex === null) {
            $errors['order_index'] = 'Order is required.';
        } elseif (!is_numeric($orderIndex) || (int) $orderIndex < 1) {
            $errors['order_index'] = 'Order must be a positive integer.';
        } elseif ((int) $orderIndex > 100) {
            $errors['order_index'] = 'Order cannot exceed 100.';
        } elseif ((int) $orderIndex != $orderIndex) {
            $errors['order_index'] = 'Order must be a whole number.';
        } else {
            $existing = $milestoneRepository->findOneBy(['contract' => $contract, 'orderIndex' => (int) $orderIndex]);
            if ($existing && $existing->getId() !== $milestone->getId()) {
                $errors['order_index'] = 'Order ' . (int) $orderIndex . ' is already used by another milestone.';
            }
        }

        $validStatuses = [Milestone::STATUS_PENDING, Milestone::STATUS_IN_PROGRESS, Milestone::STATUS_REVISION_REQUESTED, Milestone::STATUS_CANCELLED];
        if (!in_array($status, $validStatuses, true)) {
            $errors['status'] = 'Invalid status.';
        }

        if ($amount !== null && $amount !== '') {
            if (!is_numeric($amount) || (float) $amount < 0) {
                $errors['amount'] = 'Amount must be a positive number.';
            } elseif ((float) $amount > 9999999.99) {
                $errors['amount'] = 'Amount must not exceed 9,999,999.99.';
            } elseif (preg_match('/\.\d{3,}/', (string) $amount)) {
                $errors['amount'] = 'Amount can have at most 2 decimal places.';
            } elseif ($contract->getAgreedPrice() && (float) $amount > (float) $contract->getAgreedPrice()) {
                $errors['amount'] = 'Amount cannot exceed contract price.';
            }
        }

        if (!isset($errors['amount']) && $contract->getAgreedPrice() !== null) {
            $contractTotalCents = $this->moneyToCents($contract->getAgreedPrice());
            $otherMilestonesCents = $this->sumMilestoneAmountsCents($contract, $milestone->getId());
            $updatedMilestoneCents = ($amount !== null && $amount !== '') ? $this->moneyToCents((string) $amount) : 0;

            if (($otherMilestonesCents + $updatedMilestoneCents) > $contractTotalCents) {
                $errors['amount'] = 'Total milestone amounts cannot exceed contract price.';
            }
        }

        if (!empty($errors)) {
            return $this->render('pages/worker/milestone_edit.html.twig', [
                'contract' => $contract,
                'milestone' => $milestone,
                'errors' => $errors,
                'old' => [
                    'title' => $title,
                    'description' => $description,
                    'due_date' => $dueDate,
                    'order_index' => $orderIndex,
                    'status' => $status,
                    'amount' => $amount,
                ],
                'sidebar_items' => $this->getWorkerSidebarItems('contracts'),
                'topbar_title' => 'Edit Milestone',
            ]);
        }

        $milestone->setTitle($title);
        $milestone->setDescription($description ?: null);
        $milestone->setDueDate(new \DateTime($dueDate));
        $milestone->setOrderIndex((int) $orderIndex);
        $milestone->setStatus($status);

        $amount = $request->request->get('amount');
        if ($amount !== null && $amount !== '') {
            $milestone->setAmount($amount);
        } else {
            $milestone->setAmount(null);
        }

        $em->flush();

        $this->addFlash('success', 'Milestone updated successfully!');
        return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
    }

    #[Route('/contracts/{contractId}/milestones/{milestoneId}/deliver', name: 'worker_milestone_deliver', methods: ['POST'], requirements: ['contractId' => '\d+', 'milestoneId' => '\d+'])]
    public function milestoneDeliver(
        int $contractId,
        int $milestoneId,
        Request $request,
        ContractRepository $contractRepository,
        MilestoneRepository $milestoneRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $contract = $contractRepository->find($contractId);
        $milestone = $milestoneRepository->find($milestoneId);

        if (!$milestone || !$contract || $milestone->getContract()->getId() !== $contractId) {
            throw $this->createNotFoundException('Milestone not found');
        }
        $this->assertWorkerOwns($contract);

        if (!$this->isCsrfTokenValid('milestone_deliver_' . $milestoneId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
        }

        if (in_array($milestone->getStatus(), [Milestone::STATUS_DELIVERED, Milestone::STATUS_COMPLETED], true)) {
            $this->addFlash('error', 'Delivered or approved milestones cannot be edited directly.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
        }

        if (!in_array($contract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS], true)) {
            $this->addFlash('error', 'Only active contracts can receive milestone deliveries.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
        }
        if (!$contract->isUpfrontPaid()) {
            $this->addFlash('error', 'Client upfront funding is required before delivering milestones.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
        }
        if (!in_array($milestone->getStatus(), [Milestone::STATUS_PENDING, Milestone::STATUS_IN_PROGRESS, Milestone::STATUS_REVISION_REQUESTED], true)) {
            $this->addFlash('error', 'This milestone cannot be delivered in its current status.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
        }

        $milestone->markDelivered();
        if ($contract->getStatus() === Contract::STATUS_ACTIVE) {
            $contract->setStatus(Contract::STATUS_IN_PROGRESS);
        }
        $em->flush();

        $this->addFlash('success', 'Milestone delivered. Waiting for client review.');
        return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
    }

    #[Route('/contracts/{contractId}/milestones/{milestoneId}/delete', name: 'worker_milestone_delete', methods: ['POST'], requirements: ['contractId' => '\d+', 'milestoneId' => '\d+'])]
    public function milestoneDelete(int $contractId, int $milestoneId, Request $request, ContractRepository $contractRepository, MilestoneRepository $milestoneRepository, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        $contract = $contractRepository->find($contractId);
        $milestone = $milestoneRepository->find($milestoneId);

        if (!$milestone || !$contract || $milestone->getContract()->getId() !== $contractId) {
            throw $this->createNotFoundException('Milestone not found');
        }
        $this->assertWorkerOwns($contract);

        if (!$this->isCsrfTokenValid('milestone_delete_' . $milestoneId, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');
            return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
        }

        $em->remove($milestone);
        $em->flush();

        $this->addFlash('success', 'Milestone deleted successfully!');
        return $this->redirectToRoute('worker_contract_show', ['id' => $contractId]);
    }

    // ==================== OFFERS ====================

    #[Route('/service-requests', name: 'worker_service_requests')]
    public function serviceRequests(Request $request, ServiceRequestRepository $serviceRequestRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');

        $search = trim((string) $request->query->get('search', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $qb = $serviceRequestRepository->createQueryBuilder('sr')
            ->leftJoin('sr.client', 'c')
            ->leftJoin('sr.category', 'cat')
            ->where('UPPER(sr.status) NOT IN (:closedStatuses)')
            ->setParameter('closedStatuses', ['IN_PROGRESS', 'COMPLETED', 'CLOSED', 'CANCELLED'])
            ->orderBy('sr.createdAt', 'DESC');

        if ($search !== '') {
            $qb->andWhere('sr.title LIKE :search OR sr.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $total = (int) (clone $qb)->select('COUNT(DISTINCT sr.id)')->getQuery()->getSingleScalarResult();
        $totalPages = max(1, (int) ceil($total / $limit));

        $query = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query, true);
        $serviceRequests = iterator_to_array($paginator);

        return $this->render('pages/worker/service_requests.html.twig', [
            'service_requests' => $serviceRequests,
            'search_keyword' => $search,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'sidebar_items' => $this->getWorkerSidebarItems('service_requests'),
            'topbar_title' => 'Available Service Requests',
        ]);
    }

    #[Route('/service-requests/{id}', name: 'worker_service_request_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function serviceRequestShow(ServiceRequest $serviceRequest): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');

        return $this->render('pages/worker/service_request_show.html.twig', [
            'service_request' => $serviceRequest,
            'sidebar_items' => $this->getWorkerSidebarItems('service_requests'),
            'topbar_title' => 'Service Request Details',
        ]);
    }

    #[Route('/offers', name: 'worker_offers_list')]
    public function offersList(Request $request, OfferRepository $offerRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        /** @var User $user */
        $user = $this->getUser();

        $q = trim((string) $request->query->get('q', ''));
        $statusFilter = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $result = $offerRepository->findForWorker($user, $q !== '' ? $q : null, $statusFilter !== '' ? $statusFilter : null, $page, $limit);
        $offers = $result['offers'];
        $total = $result['total'];
        $totalPages = max(1, (int) ceil($total / $limit));

        $template = $request->isXmlHttpRequest() 
            ? 'pages/worker/_offers_list_grid.html.twig' 
            : 'pages/worker/offers_list.html.twig';

        return $this->render($template, [
            'offers' => $offers,
            'q' => $q,
            'status_filter' => $statusFilter,
            'page' => $page,
            'total_pages' => $totalPages,
            'total' => $total,
            'sidebar_items' => $this->getWorkerSidebarItems('offers'),
            'topbar_title' => 'My Offers',
        ]);
    }

    #[Route('/offers/new/{serviceRequest}', name: 'worker_offer_new', requirements: ['serviceRequest' => '\d+'], methods: ['GET', 'POST'])]
    public function offerNew(Request $request, ServiceRequest $serviceRequest, EntityManagerInterface $em, OfferMailerService $mailerService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        /** @var User $user */
        $user = $this->getUser();

        $offer = new Offer();
        $offer->setStatus('PENDING');
        $offer->setPriorityLevel('MEDIUM');
        $offer->setServiceRequest($serviceRequest);
        $offer->setWorker($user);

        $form = $this->createForm(OfferType::class, $offer);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($offer);
            $em->flush();

            // Send notification email to client
            $mailerService->sendNewOfferEmail($offer);

            $this->addFlash('success', 'Offer submitted successfully!');
            return $this->redirectToRoute('worker_offers_list');
        }

        return $this->render('pages/worker/offer_new.html.twig', [
            'offer' => $offer,
            'service_request' => $serviceRequest,
            'form' => $form,
            'sidebar_items' => $this->getWorkerSidebarItems('service_requests'),
            'topbar_title' => 'Submit Offer',
        ]);
    }


    #[Route('/offers/{id}', name: 'worker_offer_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function offerShow(Offer $offer, \App\Repository\NegotiationRepository $negotiationRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        /** @var User $user */
        $user = $this->getUser();

        if ($offer->getWorker()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your offer.');
        }

        // Fetch latest negotiation for this offer (like client controller)
        $negotiation = $negotiationRepository->findLatestByOffer($offer);

        return $this->render('pages/worker/offer_show.html.twig', [
            'offer' => $offer,
            'negotiation' => $negotiation,
            'sidebar_items' => $this->getWorkerSidebarItems('offers'),
            'topbar_title' => 'Offer Details',
        ]);
    }

    #[Route('/offers/{id}/negotiation/accept', name: 'worker_offer_negotiation_accept', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function negotiationAccept(
        Offer $offer,
        \App\Repository\NegotiationRepository $negotiationRepository,
        EntityManagerInterface $em,
        Request $request,
        \App\Service\ContractFromOfferService $contractFromOfferService
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        /** @var User $user */
        $user = $this->getUser();
        if ($offer->getWorker()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your offer.');
        }
        $negotiation = $negotiationRepository->findLatestByOffer($offer);
        if (!$negotiation) {
            $this->addFlash('error', 'No negotiation found.');
            return $this->redirectToRoute('worker_offer_show', ['id' => $offer->getId()]);
        }
        if (!$this->isCsrfTokenValid('negotiation_accept' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('worker_offer_show', ['id' => $offer->getId()]);
        }
        $negotiation->setStatus('ACCEPTED');
        $negotiation->setLastActionAt(new \DateTime());
        $offer->setPrice($negotiation->getCounterPrice());
        $offer->setStatus('ACCEPTED');

        // Keep offer lifecycle consistent with client acceptance flow.
        $otherOffers = $this->entityManager->getRepository(Offer::class)->createQueryBuilder('o')
            ->where('o.serviceRequest = :sr')
            ->andWhere('o.id != :id')
            ->andWhere('o.status IN (:statuses)')
            ->setParameter('sr', $offer->getServiceRequest())
            ->setParameter('id', $offer->getId())
            ->setParameter('statuses', [Offer::STATUS_PENDING, Offer::STATUS_NEGOTIATING])
            ->getQuery()
            ->getResult();

        foreach ($otherOffers as $other) {
            if ($other instanceof Offer) {
                $other->setStatus(Offer::STATUS_REJECTED);
            }
        }

        if ($offer->getServiceRequest() !== null) {
            $offer->getServiceRequest()->setStatus('IN_PROGRESS');
        }

        // Generate draft-to-sign contract from accepted negotiated offer.
        $contractFromOfferService->createFromAcceptedOffer($offer);
        $em->flush();
        $this->addFlash('success', 'Negotiation accepted and offer updated.');
        return $this->redirectToRoute('worker_offer_show', ['id' => $offer->getId()]);
    }

    #[Route('/offers/{id}/negotiation/reject', name: 'worker_offer_negotiation_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function negotiationReject(Offer $offer, \App\Repository\NegotiationRepository $negotiationRepository, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_WORKER');
        /** @var User $user */
        $user = $this->getUser();
        if ($offer->getWorker()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your offer.');
        }
        $negotiation = $negotiationRepository->findLatestByOffer($offer);
        if (!$negotiation) {
            $this->addFlash('error', 'No negotiation found.');
            return $this->redirectToRoute('worker_offer_show', ['id' => $offer->getId()]);
        }
        if (!$this->isCsrfTokenValid('negotiation_reject' . $offer->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('worker_offer_show', ['id' => $offer->getId()]);
        }
        $negotiation->setStatus('REJECTED');
        $negotiation->setLastActionAt(new \DateTime());
        $offer->setStatus('REJECTED');
        $em->flush();
        $this->addFlash('success', 'Negotiation rejected and offer marked as rejected.');
        return $this->redirectToRoute('worker_offer_show', ['id' => $offer->getId()]);
    }

    // ==================== HELPERS ====================

    private function assertWorkerOwns(Contract $contract): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getWorker()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
    }

    private function moneyToCents(string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    private function sumMilestoneAmountsCents(Contract $contract, ?int $excludeMilestoneId = null): int
    {
        $sum = 0;

        foreach ($contract->getMilestones() as $milestone) {
            if ($excludeMilestoneId !== null && $milestone->getId() === $excludeMilestoneId) {
                continue;
            }

            $amount = $milestone->getAmount();
            if ($amount === null || $amount === '') {
                continue;
            }

            $sum += $this->moneyToCents($amount);
        }

        return $sum;
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

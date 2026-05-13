<?php

namespace App\Controller;

use App\Entity\Contract;
use App\Entity\Conversation;
use App\Entity\Milestone;
use App\Entity\User;
use App\Repository\ContractRepository;
use App\Repository\MilestoneRepository;
use App\Service\ClientSidebarService;
use App\Service\ContractPdfService;
use App\Service\StripeCheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use App\Controller\BaseController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/client')]
final class ClientController extends BaseController
{
    public function __construct(
        private ClientSidebarService $clientSidebar,
        private StripeCheckoutService $stripeCheckoutService,
        private string $paymentProvider = 'stripe',
    ) {
    }

    // ==================== CONTRACTS ====================

    #[Route('/contracts', name: 'client_contracts_list')]
    public function contractsList(Request $request, ContractRepository $contractRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        $limit = 30;
        $page = max(1, (int) $request->query->get('page', 1));
        $offset = ($page - 1) * $limit;

        $total = $contractRepository->countByClient($user);
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 1;
        $contracts = $contractRepository->findByClientPaginated($user, $limit, $offset);

        return $this->render('pages/client/contracts_list.html.twig', [
            'contracts' => $contracts,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $totalPages,
            'total' => $total,
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => 'Contracts',
        ]);
    }

    public function contractNew(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        $this->addFlash('error', 'Contract creation is disabled for clients. Contracts are auto-generated from accepted offers.');

        return $this->redirectToRoute('client_contracts_list');
    }

    public function contractCreate(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        $this->addFlash('error', 'Contract creation is disabled for clients. Contracts are auto-generated from accepted offers.');

        return $this->redirectToRoute('client_contracts_list');
    }

    #[Route('/contracts/{id}', name: 'client_contract_show', requirements: ['id' => '\d+'])]
    public function contractShow(Request $request, Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        return $this->render('pages/client/contract_show.html.twig', [
            'contract' => $contract,
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => $contract->getTitle() ?: ('Contract #' . $contract->getId()),
        ]);
    }

    #[Route('/contracts/{id}/pdf', name: 'client_contract_pdf', requirements: ['id' => '\d+'])]
    public function contractPdf(Contract $contract, ContractPdfService $pdfService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        $pdfContent = $pdfService->generate($contract);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('inline; filename="contract_%d.pdf"', $contract->getId()),
        ]);
    }

    public function contractEdit(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        $this->addFlash('error', 'Contract editing is disabled for clients. Please contact admin.');

        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

    public function contractUpdate(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        $this->addFlash('error', 'Contract editing is disabled for clients. Please contact admin.');

        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}/send-for-signing', name: 'client_contract_send_sign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractSendForSigning(Contract $contract, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
        if ($contract->getStatus() !== Contract::STATUS_DRAFT) {
            $this->addFlash('error', 'Only draft contracts can be sent for signing.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        $contract->setStatus(Contract::STATUS_PENDING_SIGN);
        $em->flush();

        $this->addFlash('success', 'Contract sent for signing!');
        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}/sign', name: 'client_contract_sign', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function contractSign(Request $request, Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
        if (!$contract->canBeSigned()) {
            $this->addFlash('error', 'This contract cannot be signed.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if ($contract->isClientSigned()) {
            $this->addFlash('info', 'You have already signed this contract.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        return $this->render('pages/client/contract_sign.html.twig', [
            'contract' => $contract,
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => 'Sign Contract',
        ]);
    }

    #[Route('/contracts/{id}/sign/submit', name: 'client_contract_sign_submit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractSignSubmit(Contract $contract, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
        if (!$contract->canBeSigned() || $contract->isClientSigned()) {
            $this->addFlash('error', 'Cannot sign this contract.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        $signatureData = $request->request->get('signature_data');
        if (empty($signatureData)) {
            $this->addFlash('error', 'Please provide your signature.');
            return $this->redirectToRoute('client_contract_sign', ['id' => $contract->getId()]);
        }

        $contract->signByClient($signatureData, $request->getClientIp() ?? '0.0.0.0');
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

        $this->addFlash('success', 'Contract signed successfully!' . ($contract->isFullySigned() ? ' The contract is now active.' : ' Waiting for worker signature.'));
        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

    public function contractDelete(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        $this->addFlash('error', 'Contract deletion is disabled for clients. Please contact admin.');

        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}/cancel', name: 'client_contract_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractCancel(Contract $contract, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
        if (!$contract->canBeCancelled()) {
            $this->addFlash('error', 'This contract cannot be cancelled.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        $reason = $request->request->get('cancellation_reason', 'Cancelled by client');
        $contract->cancel($reason);
        $em->flush();

        $this->addFlash('success', 'Contract cancelled.');
        return $this->redirectToRoute('client_contracts_list');
    }

    #[Route('/contracts/{id}/fund-upfront', name: 'client_contract_fund_upfront', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractFundUpfront(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        if (!in_array($contract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS], true)) {
            $this->addFlash('error', 'Upfront funding is only available for active contracts.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if (!$contract->isFullySigned()) {
            $this->addFlash('error', 'Both signatures are required before funding the upfront amount.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if ($contract->isUpfrontPaid()) {
            $this->addFlash('info', 'Upfront amount is already funded.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        $provider = strtolower(trim($this->paymentProvider));
        $preferMock = in_array($provider, ['mock', 'sandbox', 'test'], true);

        if (!$preferMock && $this->stripeCheckoutService->isEnabled()) {
            try {
                $checkoutUrl = $this->stripeCheckoutService->createUpfrontCheckoutUrl($contract);

                return $this->redirect($checkoutUrl, 303);
            } catch (\Throwable $e) {
                $this->addFlash('error', 'Stripe checkout failed, switched to mock payment mode for testing.');
            }
        }

        if (!$preferMock) {
            $this->addFlash('info', 'Stripe is not configured. Using mock payment mode.');
        }

        return $this->redirectToRoute('client_contract_mock_checkout', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}/mock-payment', name: 'client_contract_mock_checkout', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function contractMockCheckout(Request $request, Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        if (!in_array($contract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS], true)) {
            $this->addFlash('error', 'Upfront funding is only available for active contracts.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if (!$contract->isFullySigned()) {
            $this->addFlash('error', 'Both signatures are required before funding the upfront amount.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if ($contract->isUpfrontPaid()) {
            $this->addFlash('info', 'Upfront amount is already funded.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        return $this->render('pages/client/contract_mock_checkout.html.twig', [
            'contract' => $contract,
            'sidebar_items' => $this->clientSidebar->getItems($request),
            'topbar_title' => 'Mock Payment Checkout',
        ]);
    }

    #[Route('/contracts/{id}/mock-payment/confirm', name: 'client_contract_mock_confirm', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractMockConfirm(Contract $contract, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        if (!in_array($contract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS], true)) {
            $this->addFlash('error', 'Upfront funding is only available for active contracts.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if (!$contract->isFullySigned()) {
            $this->addFlash('error', 'Both signatures are required before funding the upfront amount.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if ($contract->isUpfrontPaid()) {
            $this->addFlash('info', 'Upfront amount is already funded.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        $contract->markUpfrontPaid();
        $em->flush();

        $this->addFlash('success', 'Mock payment confirmed successfully.');
        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId(), 'payment' => 'mock_success']);
    }

    #[Route('/contracts/{id}/mock-payment/cancel', name: 'client_contract_mock_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractMockCancel(Contract $contract): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }

        $this->addFlash('info', 'Mock payment cancelled.');
        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId(), 'payment' => 'mock_cancelled']);
    }

    #[Route('/contracts/{contractId}/milestones/{milestoneId}/approve', name: 'client_milestone_approve', methods: ['POST'], requirements: ['contractId' => '\d+', 'milestoneId' => '\d+'])]
    public function milestoneApprove(
        int $contractId,
        int $milestoneId,
        ContractRepository $contractRepository,
        MilestoneRepository $milestoneRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();

        $contract = $contractRepository->find($contractId);
        $milestone = $milestoneRepository->find($milestoneId);

        if (!$contract || !$milestone || $milestone->getContract()->getId() !== $contractId) {
            throw $this->createNotFoundException('Milestone not found.');
        }
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
        if (!$contract->isUpfrontPaid()) {
            $this->addFlash('error', 'Upfront payment must be funded before approving milestones.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if ($milestone->getStatus() !== Milestone::STATUS_DELIVERED) {
            $this->addFlash('error', 'Only delivered milestones can be approved.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        $milestone->markCompleted();
        $contract->releaseMilestoneAmount($milestone);
        $em->flush();

        $this->addFlash('success', 'Milestone approved and amount released.');
        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{contractId}/milestones/{milestoneId}/request-revision', name: 'client_milestone_request_revision', methods: ['POST'], requirements: ['contractId' => '\d+', 'milestoneId' => '\d+'])]
    public function milestoneRequestRevision(
        int $contractId,
        int $milestoneId,
        ContractRepository $contractRepository,
        MilestoneRepository $milestoneRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();

        $contract = $contractRepository->find($contractId);
        $milestone = $milestoneRepository->find($milestoneId);

        if (!$contract || !$milestone || $milestone->getContract()->getId() !== $contractId) {
            throw $this->createNotFoundException('Milestone not found.');
        }
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
        if ($milestone->getStatus() !== Milestone::STATUS_DELIVERED) {
            $this->addFlash('error', 'Only delivered milestones can be sent back for revision.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        $milestone->requestRevision();
        if (in_array($contract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS], true)) {
            $contract->setStatus(Contract::STATUS_IN_PROGRESS);
        }
        $em->flush();

        $this->addFlash('success', 'Revision requested for this milestone.');
        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

    #[Route('/contracts/{id}/complete', name: 'client_contract_complete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function contractComplete(Contract $contract, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_CLIENT');
        /** @var User $user */
        $user = $this->getUser();
        if ($contract->getClient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Not your contract.');
        }
        if (!in_array($contract->getStatus(), [Contract::STATUS_ACTIVE, Contract::STATUS_IN_PROGRESS])) {
            $this->addFlash('error', 'Only active contracts can be completed.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if (!$contract->isUpfrontPaid()) {
            $this->addFlash('error', 'Upfront payment must be funded before completing the contract.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }
        if (!$contract->areAllMilestonesFinalized() || $contract->hasPendingApprovals()) {
            $this->addFlash('error', 'All milestones must be approved or cancelled before completion.');
            return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
        }

        $contract->complete();
        $em->flush();

        $this->addFlash('success', 'Contract marked as completed!');
        return $this->redirectToRoute('client_contract_show', ['id' => $contract->getId()]);
    }

}

<?php

namespace App\Controller;

use App\Service\TicketSupportAIService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for handling AI-powered ticket support
 * 
 * This controller provides endpoints for integrating with the Ticket Support AI
 * service and demonstrates how to use it in a Symfony application.
 */
class TicketSupportAIController extends AbstractController
{
    private TicketSupportAIService $aiService;

    /**
     * Constructor
     *
     * @param TicketSupportAIService $aiService The AI service for ticket support
     */
    public function __construct(TicketSupportAIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Get a solution suggestion for a new ticket
     *
     * @Route("/api/tickets/ai-solve", name="ticket_ai_solve", methods={"POST"})
     *
     * @param Request $request The HTTP request
     * @return JsonResponse JSON response with AI suggestion
     */
    public function solveTicket(Request $request): JsonResponse
    {
        // Get request data
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (empty($data['subject']) || empty($data['message'])) {
            return $this->json([
                'error' => 'Subject and message are required fields',
            ], 400);
        }

        // Get AI suggestion
        $aiResponse = $this->aiService->solveTicket(
            $data['subject'],
            $data['message'],
            $data['category'] ?? null
        );

        return $this->json($aiResponse);
    }

    /**
     * Update the AI knowledge base with a new resolved ticket
     *
     * @Route("/api/tickets/ai-update-kb", name="ticket_ai_update_kb", methods={"POST"})
     *
     * @param Request $request The HTTP request
     * @return JsonResponse JSON response with update status
     */
    public function updateKnowledgeBase(Request $request): JsonResponse
    {
        // Get request data
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (empty($data['subject']) || empty($data['message']) || empty($data['resolution'])) {
            return $this->json([
                'error' => 'Subject, message, and resolution are required fields',
            ], 400);
        }

        // Update knowledge base
        $response = $this->aiService->updateKnowledgeBase(
            $data['subject'],
            $data['message'],
            $data['resolution'],
            $data['category'] ?? null
        );

        return $this->json($response);
    }

    /**
     * Retrain the AI model
     *
     * @Route("/api/tickets/ai-retrain", name="ticket_ai_retrain", methods={"POST"})
     *
     * @return JsonResponse JSON response with retrain status
     */
    public function retrainModel(): JsonResponse
    {
        $response = $this->aiService->retrainModel();

        return $this->json($response);
    }

    /**
     * Get statistics about the AI knowledge base
     *
     * @Route("/api/tickets/ai-stats", name="ticket_ai_stats", methods={"GET"})
     *
     * @return JsonResponse JSON response with knowledge base statistics
     */
    public function getKnowledgeBaseStats(): JsonResponse
    {
        $stats = $this->aiService->getKnowledgeBaseStats();

        return $this->json($stats);
    }

    /**
     * Check if the AI service is available
     *
     * @Route("/api/tickets/ai-status", name="ticket_ai_status", methods={"GET"})
     *
     * @return JsonResponse JSON response with service status
     */
    public function getServiceStatus(): JsonResponse
    {
        $isAvailable = $this->aiService->isServiceAvailable();

        return $this->json([
            'available' => $isAvailable,
            'checked_at' => (new \DateTime())->format('c'),
        ]);
    }
}

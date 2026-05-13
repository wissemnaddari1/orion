<?php

namespace App\Controller\Worker;

use App\Entity\ServiceRequest;
use App\Service\OfferPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/worker/offer-ai')]
#[IsGranted('ROLE_WORKER')]
class OfferAiController extends AbstractController
{
    public function __construct(
        private OfferPredictionService $predictionService
    ) {}

    #[Route('/enhance/{id}', name: 'worker_offer_enhance', methods: ['POST'])]
    public function enhance(ServiceRequest $serviceRequest, Request $request): JsonResponse
    {
        $worker = $this->getUser();
        if (!$worker) {
            return new JsonResponse(['error' => 'User not found'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        // Prepare data for the AI service
        $offerData = [
            'price' => $data['price'] ?? 0,
            'message' => $data['message'] ?? '',
            'estimated_time_days' => $data['estimated_time_days'] ?? 0,
            'included_revisions' => $data['included_revisions'] ?? 0,
            'deliverables' => $data['deliverables'] ?? '',
        ];

        $requestData = [
            'budget_min' => $serviceRequest->getBudgetMin(),
            'budget_max' => $serviceRequest->getBudgetMax(),
            'duration' => $serviceRequest->getDuration(),
        ];

        $workerData = [
            'rating_avg' => $worker->getRatingAvg() ?? 0,
            'total_reviews' => $worker->getTotalReviews() ?? 0,
        ];

        // Call the AI service
        $result = $this->predictionService->analyzeEnhancement($offerData, $requestData, $workerData);

        if (!$result) {
            return new JsonResponse(['error' => 'AI Service currently unavailable'], 503);
        }

        return new JsonResponse($result);
    }
}

<?php

namespace App\Controller;

use App\Entity\ServiceRequirement;
use App\Repository\ServiceRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiRequestServiceController extends AbstractController
{
    public function __construct(
        private HttpClientInterface $client,
        private EntityManagerInterface $em,
        private string $serviceRequestAiBaseUrl = 'http://127.0.0.1:5010',
    ) {
    }

    #[Route('/ai/generate/{id}', name: 'ai_generate', methods: ['POST'])]
    public function generate(int $id, ServiceRequestRepository $repository): JsonResponse
    {
        $serviceRequest = $repository->find($id);

        if (!$serviceRequest) {
            return new JsonResponse(['error' => 'Service Request not found'], 404);
        }

        // 1. Get Category Name safely
        $categoryObj = $serviceRequest->getCategory();
        
        // If it's an object, get its name; otherwise, treat as string
        $rawCategory = is_object($categoryObj) ? $categoryObj->getName() : (string)$categoryObj;
        $categoryMap = [
            // Database Name => Flask/Model Name
            'Backend Developer'    => 'Backend Developer',
            'Frontend Developer'   => 'Frontend Developer',
            'Full Stack Developer' => 'Full Stack Developer',
            'Mobile App Developer' => 'Mobile App Developer',
            'DevOps Engineer'      => 'DevOps Engineer',
            'UI/UX Designer'       => 'UI/UX Designer',
            
            // Fallbacks just in case
            'backend'   => 'Backend Developer',
            'frontend'  => 'Frontend Developer',
            'fullstack' => 'Full Stack Developer',
            'mobile'    => 'Mobile App Developer',
            'devops'    => 'DevOps Engineer',
            'uiux'      => 'UI/UX Designer',
        ];

        $flaskCategory = $categoryMap[trim($rawCategory)] ?? null;
        if (!$flaskCategory) {
            return new JsonResponse(['error' => "Category '{$rawCategory}' not supported by AI"], 400);
        }

        // ... inside generate() method in AiRequestServiceController.php

        try {
            // 1. Call Flask
            $tier = 'LOW'; 
            $aiTitles = [];
            
            $base = rtrim($this->serviceRequestAiBaseUrl, '/');
            $response = $this->client->request('POST', $base.'/ai-predict', [
                'json' => [
                    'category'   => $flaskCategory,
                    'budget_max' => (float) ($serviceRequest->getBudgetMax() ?? 0),
                    'duration'   => (int)   ($serviceRequest->getDuration() ?? 30),
                    'top_k'      => 15,
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                return new JsonResponse(['error' => 'Flask API error'], 500);
            }

            $data = $response->toArray();
            $aiTitles = $data['titles'] ?? []; // Matches Flask output key
            $tier = $data['tier'] ?? 'LOW';    // Matches Flask output key

            // 2. Load Master Library from CSV
            $masterData = $this->loadMasterLibrary();
            $currentCategoryName = strtolower(trim($flaskCategory)); // e.g. "backend developer"

            // 3. Create ServiceRequirements
            foreach ($aiTitles as $titleText) {
                $requirement = new ServiceRequirement();
                $requirement->setTitle($titleText);
                $requirement->setService($serviceRequest);
                $lookupKey = $currentCategoryName . '|' . strtolower(trim($titleText));


                // Set Priority based on Flask Tier
                $priority = in_array($tier, ['ELITE', 'HIGH'], true) ? 3 : 1;
                $requirement->setPriorityLevel($priority);
                $requirement->setOptionsJson([]);

                // Enrich data from your Master Library CSV
                if (isset($masterData[$lookupKey])) {
                    $csvRow = $masterData[$lookupKey];
        
                    $requirement->setDetails($csvRow['description']);
                    $requirement->setRequirementType($csvRow['type']);
                    $requirement->setAnswerFormat($csvRow['format']);
                    $requirement->setIsMandatory($csvRow['is_mandatory']);

                    $priorityMap = ['High' => 3, 'Medium' => 2, 'Low' => 1];
                    $requirement->setPriorityLevel($priorityMap[$csvRow['importance']] ?? 1);
                }
                 else {
                    // Fallback for unknown titles
                    $requirement->setDetails("AI Suggested requirement for project clarity.");
                    $requirement->setRequirementType('Text');
                    $requirement->setAnswerFormat('PDF');
                    $requirement->setIsMandatory(true);
                    $requirement->setPriorityLevel(1);
                }

                $requirement->setOptionsJson([]);

                $this->em->persist($requirement);
            }

            $this->em->flush();

            return new JsonResponse([
                'status' => 'success',
                'tier' => $tier,
                'count' => count($aiTitles)
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
                'details' => 'Check if Flask is running on port 5000'
            ], 500);
        }
    }

    /**
     * @return array<string, array{description: string, is_mandatory: bool, type: string, importance: string, format: string}>
     */
    private function loadMasterLibrary(): array
    {
        $csvPath = $this->getParameter('kernel.project_dir') . '/ai_request_service/master_library.csv';
        $library = [];

        if (!file_exists($csvPath)) {
            return []; // Return empty if file is missing to avoid crashing
        }

        if (($handle = fopen($csvPath, "r")) !== FALSE) {
            $headers = fgetcsv($handle, 1000, ",");
            $col = array_flip($headers); 

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // We create a unique key: "category|title" 
                // example: "backend developer|database erd"
                $cat = strtolower(trim($data[$col['category']]));
                $tit = strtolower(trim($data[$col['title']]));
                $lookupKey = $cat . '|' . $tit;

                $library[$lookupKey] = [
                    'description'  => $data[$col['description']] ?? '',
                    'is_mandatory' => ($data[$col['is_mandatory']] == '1'),
                    'type'         => $data[$col['type']] ?? 'Text',
                    'importance'   => $data[$col['importance']] ?? 'Medium',
                    'format'       => $data[$col['format']] ?? 'PDF',
                ];
            }
            fclose($handle);
        }

        return $library;
    }
}
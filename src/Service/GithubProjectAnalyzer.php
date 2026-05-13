<?php

namespace App\Service;

use App\Entity\WorkerCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GithubProjectAnalyzer
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL   = 'llama-3.1-8b-instant';

    public function __construct(
        private HttpClientInterface  $httpClient,
        private EntityManagerInterface $em,
        private string               $groqApiKey,
    ) {}

    public function analyze(string $url): array
    {
        // ── 1. Extract repo slug ──────────────────────────────────────────────
        if (!preg_match('/github\.com\/([^\/]+\/[^\/\?#]+)/', $url, $matches)) {
            return ['success' => false, 'error' => 'Invalid GitHub URL'];
        }
        $repo = rtrim($matches[1], '/');

        // ── 2. GitHub: repo info ──────────────────────────────────────────────
        $repoData = $this->httpClient->request('GET', "https://api.github.com/repos/{$repo}", [
            'headers' => ['User-Agent' => 'FreelanceApp']
        ])->toArray();

        // ── 3. GitHub: README ─────────────────────────────────────────────────
        $readmeText = '';
        try {
            $readmeData = $this->httpClient->request('GET', "https://api.github.com/repos/{$repo}/readme", [
                'headers' => ['User-Agent' => 'FreelanceApp']
            ])->toArray();
            $raw        = base64_decode($readmeData['content'] ?? '');
            $raw        = preg_replace('/[#*`\[\]>_~\-]+/', '', $raw);
            $readmeText = substr(trim(preg_replace('/\n+/', ' ', $raw)), 0, 600);
        } catch (\Exception) {}

        // ── 4. GitHub: languages ──────────────────────────────────────────────
        $languages = array_keys($this->httpClient->request('GET', "https://api.github.com/repos/{$repo}/languages", [
            'headers' => ['User-Agent' => 'FreelanceApp']
        ])->toArray());

        // ── 5. Detect category ────────────────────────────────────────────────
        $categoryName = $this->detectCategory($languages);
        $category     = $this->em->getRepository(WorkerCategory::class)
                                 ->findOneBy(['name' => $categoryName]);

        // ── 6. Groq AI: description + budget + duration ───────────────────────
        $aiResult = $this->analyzeWithAi(
            name:         $repoData['name'] ?? $repo,
            category:     $categoryName,
            languages:    $languages,
            repoDesc:     $repoData['description'] ?? '',
            readmeText:   $readmeText,
            stars:        $repoData['stargazers_count'] ?? 0,
            openIssues:   $repoData['open_issues_count'] ?? 0,
        );

        // ── 7. Clamp values so AI can never go crazy ──────────────────────────
        $hours     = max(20, min(250, (int)($aiResult['estimated_hours'] ?? 80)));
        $budgetMin = max(100, (int)($aiResult['budget_min'] ?? $hours * 35));
        $budgetMax = max($budgetMin + 100, (int)($aiResult['budget_max'] ?? $hours * 85));
        $duration  = max(1, (int)($aiResult['duration'] ?? (int)ceil($hours / 6)));

        return [
            'success'     => true,
            'repo'        => $repo,
            'title'       => ucwords(str_replace(['-', '_'], ' ', $repoData['name'] ?? $repo)),
            'description' => $aiResult['description'] ?? $repoData['description'] ?? '',
            'budget_min'  => $budgetMin,
            'budget_max'  => $budgetMax,
            'duration'    => $duration,
            'category_id' => $category?->getId(),
            'language'    => implode(', ', array_slice($languages, 0, 3)),
        ];
    }

    private function detectCategory(array $languages): string
    {
        $hasBackend  = (bool) array_intersect($languages, ['PHP', 'Python', 'Java', 'Go', 'Ruby', 'C#', 'C++']);
        $hasFrontend = (bool) array_intersect($languages, ['JavaScript', 'TypeScript', 'CSS', 'HTML']);
        $hasMobile   = (bool) array_intersect($languages, ['Swift', 'Kotlin', 'Dart']);
        $hasDevops   = (bool) array_intersect($languages, ['Dockerfile', 'Shell', 'HCL']);

        return match(true) {
            $hasMobile                  => 'Mobile App Developer',
            $hasDevops                  => 'DevOps Engineer',
            $hasBackend && $hasFrontend => 'Full Stack Developer',
            $hasBackend                 => 'Backend Developer',
            $hasFrontend                => 'Frontend Developer',
            default                     => 'Full Stack Developer',
        };
    }

    private function analyzeWithAi(
        string $name,
        string $category,
        array  $languages,
        string $repoDesc,
        string $readmeText,
        int    $stars,
        int    $openIssues,
    ): array {
        $langList = implode(', ', array_slice($languages, 0, 6));

        $prompt = <<<PROMPT
You are a freelance project consultant. A client wants to hire a freelancer to work on this GitHub project.

GITHUB PROJECT DATA:
- Name: {$name}
- Category: {$category}
- Languages: {$langList}
- Stars: {$stars}
- Open Issues: {$openIssues}
- GitHub Description: {$repoDesc}
- README excerpt: {$readmeText}

YOUR TASKS:
1. Write a professional service request description (4-6 sentences) from the CLIENT's perspective hiring a freelancer. Be specific to this tech stack and purpose. No generic filler.

2. Estimate for a SINGLE skilled {$category} freelancer:
   - Break into tasks, estimate hours per task (max 250 hours total)
   - Calendar days = total hours / 6
   - budget_min = hours × 35, budget_max = hours × 85

Respond with ONLY raw JSON, no markdown:
{
  "description": "<professional description>",
  "budget_min": <integer>,
  "budget_max": <integer>,
  "duration": <integer days>,
  "estimated_hours": <integer>
}
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'       => self::GROQ_MODEL,
                    'temperature' => 0.4,
                    'max_tokens'  => 400,
                    'messages'    => [
                        ['role' => 'system', 'content' => 'You are a project estimation expert. Respond with valid raw JSON only. Never use markdown.'],
                        ['role' => 'user',   'content' => $prompt],
                    ],
                ],
            ]);

            $content = $response->toArray()['choices'][0]['message']['content'] ?? '{}';
            $content = trim(preg_replace('/```json|```/', '', $content));
            $parsed  = json_decode($content, true);

            if (json_last_error() === JSON_ERROR_NONE && !empty($parsed)) {
                return $parsed;
            }
        } catch (\Exception) {}

        // Fallback if AI fails
        return [
            'description'     => $readmeText ?: $repoDesc,
            'budget_min'      => 700,
            'budget_max'      => 1500,
            'duration'        => 30,
            'estimated_hours' => 80,
        ];
    }
}
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/code-scan')]
#[IsGranted('ROLE_USER')]
class CodeScanController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private string $groqApiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        string $groqApiKey
    ) {
        $this->httpClient = $httpClient;
        $this->groqApiKey = $groqApiKey;
    }

    #[Route('', name: 'code_scan_index', methods: ['GET', 'POST'])]
    public function index(Request $request, SessionInterface $session): Response
    {
        $chatHistory = $session->get('code_scan_history', []);

        if ($request->isMethod('POST')) {

            // CSRF protection
            if (!$this->isCsrfTokenValid('code_scan', $request->request->get('_token'))) {
                return $this->json(['error' => 'Invalid CSRF token'], 403);
            }

            $code = trim($request->request->get('code', ''));

            if ($code === '') {
                return $this->json(['error' => 'Please provide source code.'], 400);
            }

            if (strlen($code) > 20000) {
                return $this->json(['error' => 'Code too large (max 20k characters).'], 400);
            }

            // Store user input
            $chatHistory[] = [
                'role' => 'user',
                'content' => $code,
            ];

            // Scan code
            $result = $this->scanWithGroq($code);

            // Store assistant output
            $chatHistory[] = [
                'role' => 'assistant',
                'content' => $result,
            ];

            // Keep last 10 messages only
            $session->set('code_scan_history', array_slice($chatHistory, -10));

            if ($request->isXmlHttpRequest()) {
                return $this->json([
                    'response' => $result,
                ]);
            }

            return $this->redirectToRoute('code_scan_index');
        }

        return $this->render('code_scan/index.html.twig', [
            'chatHistory' => $chatHistory,
        ]);
    }

    /**
     * STRICT static code analysis using Groq Responses API
     */
    private function scanWithGroq(string $code): string
    {
        $systemPrompt = <<<PROMPT
You are a static code security scanner. Analyze the provided source code and identify security vulnerabilities.

CRITICAL FORMATTING RULES:
1. Output MUST be valid JSON only
2. Return an array of vulnerability objects
3. Each object has: type, severity, description, recommendation
4. If no vulnerabilities, return empty array: []
5. Severity MUST be one of: Critical, High, Medium, Low
6. Order by severity: Critical first, Low last

EXAMPLE OUTPUT FORMAT:
[
  {
    "type": "SQL Injection",
    "severity": "High",
    "description": "Direct concatenation of user input into SQL query",
    "recommendation": "Use prepared statements with bound parameters"
  }
]

DO NOT include any text outside the JSON array.
PROMPT;

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://api.groq.com/openai/v1/responses',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->groqApiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'model' => 'openai/gpt-oss-20b',
                        'input' => $systemPrompt . "\n\nCODE TO ANALYZE:\n" . $code,
                        'temperature' => 0,
                    ],
                    'timeout' => 60,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return $this->formatNoVulnerabilities();
            }

            $data = $response->toArray(false);
            $rawOutput = $data['output'][0]['content'][0]['text'] ?? '';

            // Parse and format the response
            return $this->formatVulnerabilities($rawOutput);

        } catch (\Throwable $e) {
            return $this->formatNoVulnerabilities();
        }
    }

    /**
     * Parse LLM output and format as consistent text
     */
    private function formatVulnerabilities(string $rawOutput): string
    {
        // Clean the output - remove markdown code blocks if present
        $cleaned = preg_replace('/```(?:json)?\s*|\s*```/', '', $rawOutput);
        $cleaned = trim($cleaned);

        // Try to parse as JSON
        $vulnerabilities = json_decode($cleaned, true);

        // If parsing fails or not an array, return no vulnerabilities
        if (!is_array($vulnerabilities) || empty($vulnerabilities)) {
            return $this->formatNoVulnerabilities();
        }

        // Sort by severity
        $severityOrder = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];
        usort($vulnerabilities, function ($a, $b) use ($severityOrder) {
            $severityA = $severityOrder[$a['severity'] ?? 'Low'] ?? 999;
            $severityB = $severityOrder[$b['severity'] ?? 'Low'] ?? 999;
            return $severityA - $severityB;
        });

        // Format as text
        $output = [];
        foreach ($vulnerabilities as $vuln) {
            if (!isset($vuln['type']) || !isset($vuln['severity'])) {
                continue; // Skip invalid entries
            }

            $output[] = sprintf(
                "- Vulnerability type: %s\n  Severity: %s\n  Description: %s\n  Recommendation: %s",
                $vuln['type'],
                $vuln['severity'],
                $vuln['description'] ?? 'No description provided',
                $vuln['recommendation'] ?? 'No recommendation provided'
            );
        }

        return empty($output) 
            ? $this->formatNoVulnerabilities() 
            : implode("\n", $output);
    }

    /**
     * Standard "no vulnerabilities" message
     */
    private function formatNoVulnerabilities(): string
    {
        return "No security vulnerabilities detected.";
    }
}
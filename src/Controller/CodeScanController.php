<?php

namespace App\Controller;

use App\Entity\CodeScanChat;
use App\Entity\CodeScanMessage;
use App\Repository\CodeScanChatRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/code-scan')]
#[IsGranted('ROLE_USER')]
class CodeScanController extends AbstractController
{
    private HttpClientInterface $httpClient;
    private string $groqApiKey;
    private EntityManagerInterface $entityManager;

    public function __construct(
        HttpClientInterface $httpClient,
        string $groqApiKey,
        EntityManagerInterface $entityManager
    ) {
        $this->httpClient = $httpClient;
        $this->groqApiKey = $groqApiKey;
        $this->entityManager = $entityManager;
    }

    #[Route('', name: 'code_scan_index', methods: ['GET'])]
    public function index(CodeScanChatRepository $chatRepository): Response
    {
        $user = $this->getUser();
        $chats = $chatRepository->findBy(
            ['user' => $user],
            ['updatedAt' => 'DESC']
        );

        return $this->render('code_scan/index.html.twig', [
            'chats' => $chats,
        ]);
    }

    #[Route('/chat/new', name: 'code_scan_new_chat', methods: ['POST'])]
    public function newChat(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('new_chat', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $chat = new CodeScanChat();
        $chat->setUser($this->getUser());
        $chat->setTitle('New Scan - ' . date('Y-m-d H:i'));

        $this->entityManager->persist($chat);
        $this->entityManager->flush();

        return $this->redirectToRoute('code_scan_chat', ['id' => $chat->getId()]);
    }

    #[Route('/chat/{id}', name: 'code_scan_chat', methods: ['GET'])]
    public function chat(CodeScanChat $chat): Response
    {
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('code_scan/chat.html.twig', [
            'chat' => $chat,
        ]);
    }

    #[Route('/chat/{id}/send', name: 'code_scan_send', methods: ['POST'])]
    public function sendMessage(Request $request, CodeScanChat $chat): Response
    {
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

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

        $userMessage = new CodeScanMessage();
        $userMessage->setChat($chat);
        $userMessage->setRole('user');
        $userMessage->setContent($code);
        $chat->addMessage($userMessage);

        $result = $this->scanWithGroq($code);

        $assistantMessage = new CodeScanMessage();
        $assistantMessage->setChat($chat);
        $assistantMessage->setRole('assistant');
        $assistantMessage->setContent($result);
        $chat->addMessage($assistantMessage);

        if ($chat->getMessages()->count() === 2) {
            $chat->setTitle($this->generateChatTitle($code));
        }

        $chat->updateTimestamp();

        $this->entityManager->persist($chat);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'userMessage' => [
                'id' => $userMessage->getId(),
                'role' => 'user',
                'content' => $code,
                'createdAt' => $userMessage->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
            'assistantMessage' => [
                'id' => $assistantMessage->getId(),
                'role' => 'assistant',
                'content' => $result,
                'createdAt' => $assistantMessage->getCreatedAt()->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    #[Route('/chat/{id}/delete', name: 'code_scan_delete', methods: ['POST'])]
    public function deleteChat(Request $request, CodeScanChat $chat): Response
    {
        if ($chat->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_chat', $request->request->get('_token'))) {
            return $this->json(['error' => 'Invalid CSRF token'], 403);
        }

        $this->entityManager->remove($chat);
        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    private function generateChatTitle(string $code): string
    {
        $lines = explode("\n", $code);
        $firstLine = trim($lines[0] ?? 'Code Scan');
        
        $title = str_replace(['<?php', '<?', '?>'], '', $firstLine);
        $title = trim($title);
        
        if (strlen($title) > 50) {
            $title = substr($title, 0, 47) . '...';
        }
        
        return $title ?: 'Code Scan - ' . date('H:i');
    }

    private function scanWithGroq(string $code): string
    {
        $systemPrompt = <<<PROMPT
You are an expert security code analyzer. Your job is to find ALL security vulnerabilities in the provided code.

ANALYZE FOR THESE VULNERABILITIES:
- SQL Injection (concatenated queries, unsanitized input)
- Cross-Site Scripting/XSS (unescaped output)
- Remote Code Execution (eval, system, exec, shell_exec)
- Command Injection (system calls with user input)
- Path Traversal (file operations with user input)
- Insecure Deserialization (unserialize with user data)
- Hardcoded Credentials (passwords, API keys in code)
- Weak Cryptography (MD5, SHA1 for passwords)
- Missing Authentication/Authorization checks
- Information Disclosure (exposed error messages)
- CSRF (state-changing operations without tokens)
- Open Redirect (header Location with user input)
- File Upload issues (no validation)
- XXE (XML parsing with external entities)

CRITICAL: You MUST find vulnerabilities if they exist. Be thorough.

OUTPUT FORMAT (STRICT JSON):
{
  "vulnerabilities": [
    {
      "type": "SQL Injection",
      "severity": "Critical",
      "description": "Specific technical explanation of the vulnerability",
      "recommendation": "Specific fix for this code"
    }
  ]
}

If NO vulnerabilities found, return:
{
  "vulnerabilities": []
}

ONLY output valid JSON. No other text.
PROMPT;

        try {
            $response = $this->httpClient->request(
                'POST',
                'https://api.groq.com/openai/v1/chat/completions',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->groqApiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => [
                        'model' => 'llama-3.3-70b-versatile',
                        'messages' => [
                            [
                                'role' => 'system',
                                'content' => $systemPrompt
                            ],
                            [
                                'role' => 'user',
                                'content' => "Analyze this code for security vulnerabilities:\n\n" . $code
                            ]
                        ],
                        'temperature' => 0,
                        'response_format' => ['type' => 'json_object'],
                    ],
                    'timeout' => 60,
                ]
            );

            if ($response->getStatusCode() !== 200) {
                return $this->formatNoVulnerabilities();
            }

            $data = $response->toArray(false);
            $rawOutput = $data['choices'][0]['message']['content'] ?? '';

            return $this->formatVulnerabilities($rawOutput);

        } catch (\Throwable $e) {
            return $this->formatNoVulnerabilities();
        }
    }

    private function formatVulnerabilities(string $rawOutput): string
    {
        $cleaned = preg_replace('/```(?:json)?\s*|\s*```/', '', $rawOutput);
        $cleaned = trim($cleaned);

        $data = json_decode($cleaned, true);

        $vulnerabilities = null;
        if (isset($data['vulnerabilities']) && is_array($data['vulnerabilities'])) {
            $vulnerabilities = $data['vulnerabilities'];
        } elseif (is_array($data) && !isset($data['vulnerabilities'])) {
            $vulnerabilities = $data;
        }

        if (!is_array($vulnerabilities) || empty($vulnerabilities)) {
            return $this->formatNoVulnerabilities();
        }

        $severityOrder = ['Critical' => 0, 'High' => 1, 'Medium' => 2, 'Low' => 3];
        usort($vulnerabilities, function ($a, $b) use ($severityOrder) {
            $severityA = $severityOrder[$a['severity'] ?? 'Low'] ?? 999;
            $severityB = $severityOrder[$b['severity'] ?? 'Low'] ?? 999;
            return $severityA - $severityB;
        });

        $output = [];
        foreach ($vulnerabilities as $vuln) {
            if (!isset($vuln['type']) || !isset($vuln['severity'])) {
                continue;
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

    private function formatNoVulnerabilities(): string
    {
        return "No security vulnerabilities detected.";
    }
}
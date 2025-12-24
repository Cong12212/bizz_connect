<?php

namespace App\Services;

use App\Models\AppKnowledge;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class GeminiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    protected string $model = 'gemini-2.0-flash-exp';

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');

        if (empty($this->apiKey)) {
            throw new \Exception('GEMINI_API_KEY not configured in .env');
        }
    }

    public function generateGuide(string $topic, array $userContext = []): array
    {
        try {
            $relevantKnowledge = AppKnowledge::getRelevantKnowledge($topic);

            if (empty($relevantKnowledge)) {
                return [
                    'success' => false,
                    'error' => 'Không tìm thấy thông tin liên quan trong hệ thống'
                ];
            }

            $systemInstruction = $this->buildSystemInstruction($relevantKnowledge);
            $userPrompt = $this->buildPrompt($topic, $userContext);

            $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(30)
                ->withoutVerifying()
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemInstruction . "\n\n---\n\n" . $userPrompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 2048,
                        'topP' => 0.95,
                    ]
                ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                Log::error('Gemini API Error', [
                    'status' => $response->status(),
                    'body' => $errorBody
                ]);

                throw new \Exception(
                    $errorBody['error']['message'] ?? 'API request failed'
                );
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $usage = $data['usageMetadata'] ?? [];

            return [
                'success' => true,
                'content' => $text,
                'sources' => array_column($relevantKnowledge, 'title'),
                'usage' => [
                    'prompt_tokens' => $usage['promptTokenCount'] ?? 0,
                    'completion_tokens' => $usage['candidatesTokenCount'] ?? 0,
                    'total_tokens' => $usage['totalTokenCount'] ?? 0,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Gemini Service Error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => 'Đã xảy ra lỗi: ' . $e->getMessage()
            ];
        }
    }

    public function generateGuideStream(string $topic, callable $callback): void
    {
        try {
            $relevantKnowledge = AppKnowledge::getRelevantKnowledge($topic);

            if (empty($relevantKnowledge)) {
                $callback([
                    'type' => 'error',
                    'message' => 'Không tìm thấy thông tin liên quan'
                ]);
                return;
            }

            $systemInstruction = $this->buildSystemInstruction($relevantKnowledge);
            $userPrompt = $this->buildPrompt($topic);

            $url = "{$this->baseUrl}/models/{$this->model}:streamGenerateContent?key={$this->apiKey}&alt=sse";

            $response = Http::timeout(60)
                ->withoutVerifying()
                ->withOptions(['stream' => true])
                ->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [
                                ['text' => $systemInstruction . "\n\n---\n\n" . $userPrompt]
                            ]
                        ]
                    ]
                ]);

            $body = $response->toPsrResponse()->getBody();

            while (!$body->eof()) {
                $chunk = $body->read(8192);
                $lines = explode("\n", $chunk);

                foreach ($lines as $line) {
                    if (strpos($line, 'data: ') === 0) {
                        $jsonStr = trim(substr($line, 6));

                        if ($jsonStr === '[DONE]') {
                            break 2;
                        }

                        $data = json_decode($jsonStr, true);

                        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                            $callback([
                                'type' => 'chunk',
                                'text' => $data['candidates'][0]['content']['parts'][0]['text']
                            ]);
                        }
                    }
                }
            }

            $callback(['type' => 'done']);
        } catch (\Exception $e) {
            Log::error('Gemini Stream Error: ' . $e->getMessage());
            $callback([
                'type' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }

    private function buildSystemInstruction(array $knowledge): string
    {
        $knowledgeText = $this->formatKnowledge($knowledge);

        return <<<INSTRUCTION
Bạn là trợ lý AI thông minh hỗ trợ người dùng sử dụng ứng dụng.

KIẾN THỨC VỀ ỨNG DỤNG:
{$knowledgeText}

NHIỆM VỤ CỦA BẠN:
1. Dựa HOÀN TOÀN vào KIẾN THỨC VỀ ỨNG DỤNG để trả lời
2. Đưa ra hướng dẫn cụ thể, từng bước một
3. Sử dụng ngôn ngữ đơn giản, dễ hiểu, thân thiện
4. Nếu thông tin không có trong kiến thức, hãy nói rõ "Ứng dụng hiện chưa hỗ trợ tính năng này"
5. Không bịa đặt hoặc đoán mò thông tin

ĐỊNH DẠNG TRẢ LỜI:
- Sử dụng markdown cho dễ đọc
- Chia thành các bước rõ ràng với số thứ tự
- Highlight các nút bấm, tên màn hình bằng **bold**
- Thêm emoji phù hợp để sinh động (nhưng đừng lạm dụng)

TONE: Thân thiện, nhiệt tình, như một người hướng dẫn kiên nhẫn
INSTRUCTION;
    }

    private function buildPrompt(string $topic, array $context = []): string
    {
        $contextText = !empty($context)
            ? "\n\nThông tin bổ sung từ người dùng:\n" . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '';

        return "Người dùng hỏi: {$topic}{$contextText}\n\nHãy trả lời dựa trên kiến thức đã cung cấp ở trên.";
    }

    private function formatKnowledge(array $knowledge): string
    {
        $formatted = [];

        foreach ($knowledge as $item) {
            $formatted[] = "### " . ($item['title'] ?? 'Unknown');
            $formatted[] = json_encode($item['content'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $formatted[] = "---";
        }

        return implode("\n\n", $formatted);
    }

    public function getFullKnowledgeContext(): array
    {
        return Cache::remember('gemini_full_knowledge_context', 3600, function () {
            return AppKnowledge::getAllKnowledgeContext();
        });
    }
}

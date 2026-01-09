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

    /**
     * Generate guide with context (platform + locale)
     */
    public function generateGuide(string $question, array $context = []): array
    {
        try {
            $platform = $context['platform'] ?? 'web';
            $locale = $context['locale'] ?? 'vi';

            // Lấy knowledge base theo platform và locale
            $relevantKnowledge = $this->getRelevantKnowledge($question, $platform, $locale);

            if (empty($relevantKnowledge)) {
                return [
                    'success' => false,
                    'error' => $locale === 'vi'
                        ? 'Không tìm thấy thông tin liên quan trong hệ thống'
                        : 'No relevant information found in the system'
                ];
            }

            $systemInstruction = $this->buildSystemInstruction($relevantKnowledge, $platform, $locale);
            $userPrompt = $this->buildPrompt($question, $context, $locale);

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
                'platform' => $platform,
                'locale' => $locale,
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

    /**
     * Stream generation with context
     */
    public function generateGuideStream(string $question, callable $callback, array $context = []): void
    {
        try {
            $platform = $context['platform'] ?? 'web';
            $locale = $context['locale'] ?? 'vi';

            $relevantKnowledge = $this->getRelevantKnowledge($question, $platform, $locale);

            if (empty($relevantKnowledge)) {
                $callback([
                    'type' => 'error',
                    'message' => $locale === 'vi'
                        ? 'Không tìm thấy thông tin liên quan'
                        : 'No relevant information found'
                ]);
                return;
            }

            $systemInstruction = $this->buildSystemInstruction($relevantKnowledge, $platform, $locale);
            $userPrompt = $this->buildPrompt($question, $context, $locale);

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

            // Send metadata first
            $callback([
                'type' => 'start',
                'platform' => $platform,
                'locale' => $locale,
                'sources' => array_column($relevantKnowledge, 'title')
            ]);

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

    /**
     * Get relevant knowledge từ database
     */
    private function getRelevantKnowledge(string $question, string $platform, string $locale): array
    {
        $keywords = $this->extractKeywords($question);

        // Search trong database
        $results = AppKnowledge::active()
            ->byLocale($locale)
            ->byPlatform($platform)
            ->where(function ($query) use ($keywords, $question) {
                // Search trong searchable_text
                foreach ($keywords as $keyword) {
                    $query->orWhere('searchable_text', 'like', "%{$keyword}%");
                }

                // Search trong title
                $query->orWhere('title', 'like', "%{$question}%");

                // Search trong sample_questions
                $query->orWhereRaw(
                    "JSON_SEARCH(sample_questions, 'one', ?) IS NOT NULL",
                    ["%{$question}%"]
                );
            })
            ->orderBy('priority', 'desc')
            ->orderBy('view_count', 'desc')
            ->limit(5) // Giới hạn 5 bài relevant nhất
            ->get(['id', 'title', 'category', 'content', 'key'])
            ->toArray();

        return $results;
    }

    /**
     * Extract keywords từ question
     */
    private function extractKeywords(string $text): array
    {
        // Vietnamese stop words
        $stopWords = [
            'là',
            'của',
            'và',
            'có',
            'thế',
            'nào',
            'như',
            'để',
            'cho',
            'làm',
            'sao',
            'được',
            'với',
            'trong',
            'trên',
            'how',
            'to',
            'do',
            'i',
            'the',
            'a',
            'an',
            'is',
            'are',
            'what',
            'why',
            'when',
            'where',
            'can',
            'could'
        ];

        // Lowercase và split
        $words = preg_split('/\s+/', mb_strtolower($text));

        // Filter stop words và từ ngắn
        return array_values(array_filter($words, function ($word) use ($stopWords) {
            return mb_strlen($word) > 2 && !in_array($word, $stopWords);
        }));
    }

    /**
     * Build system instruction với platform-aware context
     */
    private function buildSystemInstruction(array $knowledge, string $platform, string $locale): string
    {
        $knowledgeText = $this->formatKnowledge($knowledge, $platform);

        $platformName = $platform === 'web' ? 'Web' : 'Mobile';

        if ($locale === 'vi') {
            return <<<INSTRUCTION
Bạn là trợ lý AI thông minh hỗ trợ người dùng sử dụng ứng dụng Bizz-Connect trên nền tảng {$platformName}.

KIẾN THỨC VỀ ỨNG DỤNG:
{$knowledgeText}

NHIỆM VỤ CỦA BẠN:
1. Dựa HOÀN TOÀN vào KIẾN THỨC VỀ ỨNG DỤNG để trả lời
2. Đưa ra hướng dẫn cụ thể cho nền tảng {$platformName}, từng bước một
3. Sử dụng ngôn ngữ đơn giản, dễ hiểu, thân thiện
4. Nếu thông tin không có trong kiến thức, hãy nói rõ "Ứng dụng hiện chưa hỗ trợ tính năng này"
5. Không bịa đặt hoặc đoán mò thông tin
6. Chỉ đưa ra hướng dẫn cho nền tảng {$platformName} (không nhắc đến nền tảng khác)

ĐỊNH DẠNG TRẢ LỜI:
- Sử dụng markdown cho dễ đọc
- Chia thành các bước rõ ràng với số thứ tự
- Highlight các nút bấm, tên màn hình bằng **bold**
- Thêm emoji phù hợp để sinh động (nhưng đừng lạm dụng)
- Nếu có tips hoặc lưu ý, đưa vào cuối câu trả lời

TONE: Thân thiện, nhiệt tình, như một người hướng dẫn kiên nhẫn
INSTRUCTION;
        } else {
            return <<<INSTRUCTION
You are an intelligent AI assistant helping users use the Bizz-Connect app on {$platformName} platform.

APPLICATION KNOWLEDGE:
{$knowledgeText}

YOUR TASKS:
1. Base your answers COMPLETELY on the APPLICATION KNOWLEDGE
2. Provide specific, step-by-step instructions for {$platformName} platform
3. Use simple, easy-to-understand, friendly language
4. If information is not in the knowledge base, clearly state "The app doesn't support this feature yet"
5. Don't make up or guess information
6. Only provide instructions for {$platformName} platform (don't mention other platforms)

RESPONSE FORMAT:
- Use markdown for readability
- Break down into clear numbered steps
- Highlight buttons, screen names in **bold**
- Add appropriate emojis to make it lively (but don't overuse)
- If there are tips or notes, add them at the end

TONE: Friendly, enthusiastic, like a patient instructor
INSTRUCTION;
        }
    }

    /**
     * Build user prompt
     */
    private function buildPrompt(string $question, array $context, string $locale): string
    {
        $contextText = !empty($context['extra'])
            ? "\n\n" . ($locale === 'vi' ? 'Thông tin bổ sung: ' : 'Additional info: ')
            . json_encode($context['extra'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '';

        $promptPrefix = $locale === 'vi' ? 'Người dùng hỏi: ' : 'User asks: ';
        $promptSuffix = $locale === 'vi'
            ? "\n\nHãy trả lời dựa trên kiến thức đã cung cấp ở trên."
            : "\n\nPlease answer based on the knowledge provided above.";

        return "{$promptPrefix}{$question}{$contextText}{$promptSuffix}";
    }

    /**
     * Format knowledge với platform-aware content
     */
    private function formatKnowledge(array $knowledge, string $platform): string
    {
        $formatted = [];

        foreach ($knowledge as $item) {
            $formatted[] = "### " . ($item['title'] ?? 'Unknown');
            $formatted[] = "**Category:** " . ($item['category'] ?? 'general');
            $formatted[] = "**Key:** " . ($item['key'] ?? 'unknown');

            // Format content theo platform
            $content = $item['content'] ?? [];

            if (isset($content['description'])) {
                $formatted[] = "\n**Description:** " . $content['description'];
            }

            // Lấy steps theo platform
            if (isset($content['steps'])) {
                $steps = $content['steps'][$platform]
                    ?? $content['steps']['web']
                    ?? $content['steps']['mobile']
                    ?? [];

                if (!empty($steps)) {
                    $formatted[] = "\n**Steps:**";
                    foreach ($steps as $index => $step) {
                        $formatted[] = ($index + 1) . ". " . $step;
                    }
                }
            }

            // Tips
            if (!empty($content['tips'])) {
                $formatted[] = "\n**Tips:**";
                foreach ($content['tips'] as $tip) {
                    $formatted[] = "- " . $tip;
                }
            }

            // Notes
            if (!empty($content['notes'])) {
                $formatted[] = "\n**Notes:**";
                foreach ($content['notes'] as $note) {
                    $formatted[] = "⚠️ " . $note;
                }
            }

            // Common errors
            if (!empty($content['common_errors'])) {
                $formatted[] = "\n**Common Errors:**";
                foreach ($content['common_errors'] as $error) {
                    $formatted[] = "❌ " . ($error['error'] ?? '');
                    $formatted[] = "   ✅ Solution: " . ($error['solution'] ?? '');
                }
            }

            $formatted[] = "---";
        }

        return implode("\n", $formatted);
    }

    /**
     * Get full knowledge context (cached)
     */
    public function getFullKnowledgeContext(string $locale = 'vi', string $platform = 'all'): array
    {
        $cacheKey = "gemini_knowledge_{$locale}_{$platform}";

        return Cache::remember($cacheKey, 3600, function () use ($locale, $platform) {
            return AppKnowledge::active()
                ->byLocale($locale)
                ->byPlatform($platform)
                ->orderBy('priority', 'desc')
                ->get(['id', 'key', 'title', 'category', 'content'])
                ->toArray();
        });
    }

    /**
     * Clear knowledge cache
     */
    public function clearKnowledgeCache(): void
    {
        $locales = ['vi', 'en'];
        $platforms = ['all', 'web', 'mobile'];

        foreach ($locales as $locale) {
            foreach ($platforms as $platform) {
                Cache::forget("gemini_knowledge_{$locale}_{$platform}");
            }
        }
    }
}

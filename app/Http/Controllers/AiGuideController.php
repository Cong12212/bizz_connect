<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AppKnowledge;
use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AiGuideController extends Controller
{
    protected $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Ask AI a question
     * POST /api/ai-guide/ask
     * 
     * Body: {
     *   "question": "Làm sao thêm danh bạ?",
     *   "platform": "web",
     *   "locale": "vi"
     * }
     */
    public function ask(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'platform' => 'required|in:web,mobile',
            'locale' => 'nullable|in:vi,en'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $question = $request->input('question');
        $platform = $request->input('platform');
        $locale = $request->input('locale', 'vi');

        // Step 1: Search trong database trước
        $knowledge = $this->searchKnowledge($question, $platform, $locale);

        if ($knowledge) {
            // Tìm thấy trong KB → Trả về ngay
            $knowledge->increment('view_count');

            return response()->json([
                'success' => true,
                'source' => 'knowledge_base',
                'answer' => $this->formatAnswer($knowledge, $platform),
                'knowledge_id' => $knowledge->id
            ]);
        }

        // Step 2: Không tìm thấy → Dùng Gemini AI
        return $this->askGemini($question, $platform, $locale);
    }

    /**
     * Ask AI với streaming response
     * POST /api/ai-guide/ask-stream
     */
    public function askStream(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'question' => 'required|string|max:500',
            'platform' => 'required|in:web,mobile',
            'locale' => 'nullable|in:vi,en'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $question = $request->input('question');
        $platform = $request->input('platform');
        $locale = $request->input('locale', 'vi');

        // Search KB trước
        $knowledge = $this->searchKnowledge($question, $platform, $locale);

        if ($knowledge) {
            // Nếu có trong KB → Stream từng phần của answer
            return $this->streamKnowledgeAnswer($knowledge, $platform);
        }

        // Không có trong KB → Stream từ Gemini
        return $this->streamGeminiAnswer($question, $platform, $locale);
    }

    /**
     * Get all categories
     * GET /api/ai-guide/categories?locale=vi&platform=web
     */
    public function getCategories(Request $request): JsonResponse
    {
        $locale = $request->input('locale', 'vi');
        $platform = $request->input('platform', 'all');

        $categories = AppKnowledge::active()
            ->byLocale($locale)
            ->byPlatform($platform)
            ->select('category')
            ->distinct()
            ->pluck('category');

        return response()->json([
            'success' => true,
            'categories' => $categories
        ]);
    }

    /**
     * Get knowledge by category
     * GET /api/ai-guide/category/{category}?locale=vi&platform=web
     */
    public function getByCategory(Request $request, string $category): JsonResponse
    {
        $locale = $request->input('locale', 'vi');
        $platform = $request->input('platform', 'all');

        $knowledge = AppKnowledge::active()
            ->byCategory($category)
            ->byLocale($locale)
            ->byPlatform($platform)
            ->orderBy('priority', 'desc')
            ->orderBy('view_count', 'desc')
            ->get(['id', 'key', 'title', 'content']);

        // Format content cho từng item
        $items = $knowledge->map(function ($item) use ($platform) {
            $content = $item->content;

            // Lấy steps count theo platform
            $stepsKey = $platform === 'mobile' ? 'mobile' : 'web';
            $steps = $content['steps'][$stepsKey]
                ?? $content['steps']['web']
                ?? $content['steps']['mobile']
                ?? [];

            return [
                'id' => $item->id,
                'key' => $item->key,
                'title' => $item->title,
                'description' => $content['description'] ?? '',
                'steps_count' => count($steps)
            ];
        });

        return response()->json([
            'success' => true,
            'category' => $category,
            'total' => $items->count(),
            'items' => $items
        ]);
    }

    /**
     * Get knowledge detail by key
     * GET /api/ai-guide/knowledge/{key}?locale=vi&platform=web
     */
    public function getKnowledge(Request $request, string $key): JsonResponse
    {
        $locale = $request->input('locale', 'vi');
        $platform = $request->input('platform', 'web');

        $knowledge = AppKnowledge::active()
            ->where('key', $key)
            ->byLocale($locale)
            ->first();

        if (!$knowledge) {
            return response()->json([
                'success' => false,
                'message' => 'Knowledge not found'
            ], 404);
        }

        $knowledge->increment('view_count');

        return response()->json([
            'success' => true,
            'knowledge' => $this->formatAnswer($knowledge, $platform)
        ]);
    }

    /**
     * Get popular/trending knowledge
     * GET /api/ai-guide/popular?locale=vi&platform=web&limit=5
     */
    public function getPopular(Request $request): JsonResponse
    {
        $locale = $request->input('locale', 'vi');
        $platform = $request->input('platform', 'all');
        $limit = $request->input('limit', 5);

        $popular = AppKnowledge::active()
            ->byLocale($locale)
            ->byPlatform($platform)
            ->orderBy('view_count', 'desc')
            ->orderBy('priority', 'desc')
            ->limit($limit)
            ->get(['id', 'key', 'title', 'category', 'view_count']);

        return response()->json([
            'success' => true,
            'popular' => $popular
        ]);
    }

    /**
     * Search knowledge (cho autocomplete/suggestions)
     * GET /api/ai-guide/search?q=thêm danh bạ&locale=vi&platform=web
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q');
        $locale = $request->input('locale', 'vi');
        $platform = $request->input('platform', 'all');

        if (empty($query) || strlen($query) < 2) {
            return response()->json([
                'success' => false,
                'message' => 'Query too short'
            ], 400);
        }

        $results = AppKnowledge::active()
            ->byLocale($locale)
            ->byPlatform($platform)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('searchable_text', 'like', "%{$query}%");
            })
            ->orderBy('priority', 'desc')
            ->orderBy('view_count', 'desc')
            ->limit(10)
            ->get(['id', 'key', 'title', 'category']);

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $results
        ]);
    }

    // ==================== PRIVATE METHODS ====================

    /**
     * Search knowledge trong database
     */
    private function searchKnowledge(string $question, string $platform, string $locale): ?AppKnowledge
    {
        $keywords = $this->extractKeywords($question);

        return AppKnowledge::active()
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

                // Search trong keywords
                foreach ($keywords as $keyword) {
                    $query->orWhereRaw(
                        "JSON_SEARCH(keywords, 'one', ?) IS NOT NULL",
                        ["%{$keyword}%"]
                    );
                }
            })
            ->orderBy('priority', 'desc')
            ->orderBy('view_count', 'desc')
            ->first();
    }

    /**
     * Extract keywords từ câu hỏi
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
     * Format answer từ knowledge base
     */
    private function formatAnswer(AppKnowledge $knowledge, string $platform): array
    {
        $content = $knowledge->content;

        // Lấy steps theo platform (ưu tiên platform được chọn)
        $stepsKey = $platform === 'mobile' ? 'mobile' : 'web';
        $steps = $content['steps'][$stepsKey]
            ?? $content['steps']['web']
            ?? $content['steps']['mobile']
            ?? [];

        // Lấy images theo platform
        $images = $content['media']['images'][$platform]
            ?? $content['media']['images']['web']
            ?? $content['media']['images']['mobile']
            ?? [];

        // Get related articles
        $related = $this->getRelatedArticles($knowledge);

        return [
            'id' => $knowledge->id,
            'key' => $knowledge->key,
            'title' => $knowledge->title,
            'category' => $knowledge->category,
            'platform' => $platform,
            'description' => $content['description'] ?? '',
            'steps' => $steps,
            'tips' => $content['tips'] ?? [],
            'notes' => $content['notes'] ?? [],
            'common_errors' => $content['common_errors'] ?? [],
            'images' => $images,
            'video_url' => $content['media']['video_url'] ?? null,
            'related' => $related
        ];
    }

    /**
     * Get related articles
     */
    private function getRelatedArticles(AppKnowledge $knowledge): array
    {
        $relatedKeys = $knowledge->related_keys ?? [];

        if (empty($relatedKeys)) {
            return [];
        }

        return AppKnowledge::active()
            ->whereIn('key', $relatedKeys)
            ->where('locale', $knowledge->locale)
            ->select('id', 'key', 'title', 'category')
            ->get()
            ->toArray();
    }

    /**
     * Ask Gemini AI (fallback)
     */
    private function askGemini(string $question, string $platform, string $locale): JsonResponse
    {
        // Build context cho Gemini
        $context = [
            'platform' => $platform,
            'locale' => $locale
        ];

        $result = $this->geminiService->generateGuide($question, $context);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json([
            'success' => true,
            'source' => 'gemini_ai',
            'answer' => [
                'content' => $result['content'],
                'sources' => $result['sources'] ?? [],
                'platform' => $platform,
                'locale' => $locale
            ],
            'usage' => $result['usage'] ?? null
        ]);
    }

    /**
     * Stream knowledge answer
     */
    private function streamKnowledgeAnswer(AppKnowledge $knowledge, string $platform)
    {
        return response()->stream(function () use ($knowledge, $platform) {
            $answer = $this->formatAnswer($knowledge, $platform);

            // Stream start
            echo "data: " . json_encode([
                'type' => 'start',
                'source' => 'knowledge_base'
            ]) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            // Stream title
            echo "data: " . json_encode([
                'type' => 'title',
                'text' => $answer['title']
            ]) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            // Stream description
            if (!empty($answer['description'])) {
                echo "data: " . json_encode([
                    'type' => 'description',
                    'text' => $answer['description']
                ]) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            // Stream steps
            foreach ($answer['steps'] as $index => $step) {
                echo "data: " . json_encode([
                    'type' => 'step',
                    'number' => $index + 1,
                    'text' => $step
                ]) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
                usleep(100000); // 100ms delay cho smooth
            }

            // Stream tips
            if (!empty($answer['tips'])) {
                echo "data: " . json_encode([
                    'type' => 'tips',
                    'items' => $answer['tips']
                ]) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            // Stream related
            if (!empty($answer['related'])) {
                echo "data: " . json_encode([
                    'type' => 'related',
                    'items' => $answer['related']
                ]) . "\n\n";
                if (ob_get_level() > 0) ob_flush();
                flush();
            }

            // Stream end
            echo "data: " . json_encode([
                'type' => 'done',
                'knowledge_id' => $knowledge->id
            ]) . "\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();

            $knowledge->increment('view_count');
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Stream Gemini answer
     */
    private function streamGeminiAnswer(string $question, string $platform, string $locale)
    {
        return response()->stream(function () use ($question, $platform, $locale) {
            $context = [
                'platform' => $platform,
                'locale' => $locale
            ];

            $this->geminiService->generateGuideStream(
                $question,
                function ($data) {
                    echo "data: " . json_encode($data) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                },
                $context
            );
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}

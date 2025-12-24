<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class GuideController extends Controller
{
    protected $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    /**
     * Generate guide (normal response)
     * POST /api/guides/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string|max:500',
            'context' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->geminiService->generateGuide(
            $request->input('topic'),
            $request->input('context', [])
        );

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json($result);
    }

    /**
     * Generate guide với streaming (Server-Sent Events)
     * POST /api/guides/stream
     */
    public function stream(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'topic' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        return response()->stream(function () use ($request) {
            $this->geminiService->generateGuideStream(
                $request->input('topic'),
                function ($data) {
                    echo "data: " . json_encode($data) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            );
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * Get all knowledge categories
     * GET /api/guides/categories
     */
    public function getCategories(): JsonResponse
    {
        $categories = \App\Models\AppKnowledge::active()
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
     * GET /api/guides/category/{category}
     */
    public function getByCategory(string $category): JsonResponse
    {
        $knowledge = \App\Models\AppKnowledge::active()
            ->category($category)
            ->orderBy('priority', 'desc')
            ->get(['title', 'key', 'content']);

        return response()->json([
            'success' => true,
            'category' => $category,
            'items' => $knowledge
        ]);
    }
}

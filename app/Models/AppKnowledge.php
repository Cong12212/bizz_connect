<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AppKnowledge extends Model
{
    protected $table = 'app_knowledge';

    protected $fillable = [
        'category',
        'key',
        'title',
        'content',
        'searchable_text',
        'priority',
        'is_active'
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer'
    ];

    /**
     * Scope: Chỉ lấy knowledge đang active
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Tìm theo category
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Tìm knowledge liên quan dựa trên topic
     */
    public static function getRelevantKnowledge(string $topic, int $limit = 5): array
    {
        // Chuẩn hóa topic - bỏ dấu, lowercase
        $normalizedTopic = strtolower($topic);

        $results = self::active()
            ->where(function ($query) use ($topic, $normalizedTopic) {
                $query->where('searchable_text', 'like', "%{$topic}%")
                    ->orWhere('searchable_text', 'like', "%{$normalizedTopic}%")
                    ->orWhere('title', 'like', "%{$topic}%")
                    ->orWhere('key', 'like', "%{$topic}%");
            })
            ->orderBy('priority', 'desc')
            ->limit($limit)
            ->get();

        // Nếu vẫn không tìm thấy, trả về TẤT CẢ để AI có context
        if ($results->isEmpty()) {
            $results = self::active()
                ->orderBy('priority', 'desc')
                ->limit($limit)
                ->get();
        }

        return $results->toArray();
    }

    /**
     * Lấy tất cả knowledge để build context
     */
    public static function getAllKnowledgeContext(): array
    {
        return self::active()
            ->orderBy('category')
            ->orderBy('priority', 'desc')
            ->get()
            ->groupBy('category')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'title' => $item->title,
                        'content' => $item->content
                    ];
                })->toArray();
            })
            ->toArray();
    }
}

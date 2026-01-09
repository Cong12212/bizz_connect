<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppKnowledge extends Model
{
    use SoftDeletes;

    protected $table = 'app_knowledge';

    protected $fillable = [
        'category',
        'key',
        'platform',
        'locale',
        'title',
        'content',
        'searchable_text',
        'keywords',
        'sample_questions',
        'related_keys',
        'priority',
        'is_active'
    ];

    protected $casts = [
        'content' => 'array',
        'keywords' => 'array',
        'sample_questions' => 'array',
        'related_keys' => 'array',
        'is_active' => 'boolean',
        'view_count' => 'integer',
        'priority' => 'integer',
    ];

    // Scope helpers
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByLocale($query, $locale)
    {
        return $query->where('locale', $locale);
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where(function ($q) use ($platform) {
            $q->where('platform', $platform)
                ->orWhere('platform', 'all');
        });
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}

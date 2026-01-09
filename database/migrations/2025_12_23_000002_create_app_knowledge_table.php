<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_knowledge', function (Blueprint $table) {
            $table->id();

            // ✅ SỬA TỪ ENUM SANG VARCHAR
            $table->string('category', 50)->index(); // ← Thay đổi ở đây

            $table->string('key', 100)->unique();

            // ✅ SỬA ENUM platform
            $table->string('platform', 20)->default('all')->index(); // ← Thay đổi

            $table->string('locale', 5)->default('en')->index();
            $table->string('title');

            $table->json('content');
            $table->text('searchable_text')->nullable();
            $table->json('keywords')->nullable();
            $table->json('sample_questions')->nullable();
            $table->json('related_keys')->nullable();

            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('view_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category', 'platform', 'locale', 'is_active'], 'idx_main');
            $table->index(['priority', 'view_count'], 'idx_ranking');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_knowledge');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained()->onDelete('cascade');
            $table->string('code', 20);
            $table->string('name');
            $table->timestamps();
            $table->index(['country_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('states');
        Schema::dropIfExists('countries');
    }
};

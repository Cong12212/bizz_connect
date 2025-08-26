<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->enum('role', ['OWNER','ADMIN','MEMBER'])->default('MEMBER');
            $table->boolean('status')->default(true); // 1=active, 0=inactive
            $table->timestamps();

            $table->unique(['user_id','company_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('memberships');
    }
};

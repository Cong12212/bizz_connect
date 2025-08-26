<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // BIGINT auto increment
            $table->string('email', 255)->unique();
            $table->string('name', 200)->nullable();
            $table->string('password_hash', 255)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('phone', 50)->nullable();

            // Xác minh & trạng thái
            $table->enum('status', ['PENDING','ACTIVE','SUSPENDED'])->default('PENDING')->index();
            $table->boolean('is_verified')->default(false)->index();
            $table->timestamp('verified_at')->nullable();

            $table->char('verify_token', 64)->nullable()->unique();
            $table->timestamp('verify_token_expires_at')->nullable()->index();

            $table->string('otp_hash', 255)->nullable();
            $table->timestamp('otp_expires_at')->nullable()->index();
            $table->unsignedSmallInteger('otp_attempts')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};

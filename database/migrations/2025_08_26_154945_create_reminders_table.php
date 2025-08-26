<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->date('date');
            $table->time('time');
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->index('contact_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('reminders');
    }
};

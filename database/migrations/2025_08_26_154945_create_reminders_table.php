<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('reminders', function (Blueprint $t) {
      $t->id();
      $t->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
      $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete(); // người được nhắc
      $t->string('title');
      $t->text('note')->nullable();
      $t->dateTime('due_at')->index();
      $t->string('status')->default('pending')->index();  // pending|done|skipped|cancelled
      $t->string('channel')->default('app');              // app|email|calendar
      $t->string('external_event_id')->nullable();        // id sự kiện nếu sync lịch
      $t->timestamps();
      $t->softDeletes();
    });
  }
  public function down(): void { Schema::dropIfExists('reminders'); }
};

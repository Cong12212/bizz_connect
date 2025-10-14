<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('reminders', function (Blueprint $t) {
      $t->id();
      $t->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
      $t->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete(); 
      $t->string('title');
      $t->text('note')->nullable();
      $t->dateTime('due_at')->index();
      $t->string('status')->default('pending')->index();  
      $t->string('channel')->default('app');             
      $t->string('external_event_id')->nullable();      
      $t->timestamps();
      $t->softDeletes();
    });
  }
  public function down(): void { Schema::dropIfExists('reminders'); }
};

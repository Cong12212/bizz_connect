<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('subscriptions', function (Blueprint $t) {
      $t->id();
      // một trong hai: gắn cho công ty hoặc cá nhân pro
      $t->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
      $t->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();

      $t->string('plan');    // free|pro|pro_plus
      $t->string('status')->default('active'); // active|past_due|canceled
      $t->timestamp('current_period_start')->nullable();
      $t->timestamp('current_period_end')->nullable();

      $t->string('payment_provider')->nullable();      // stripe|paypal|...
      $t->string('provider_customer_id')->nullable();
      $t->string('provider_subscription_id')->nullable();

      $t->timestamps();

      $t->index(['company_id','user_id','status']);
    });
  }
  public function down(): void { Schema::dropIfExists('subscriptions'); }
};

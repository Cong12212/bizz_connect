<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::create('contacts', function (Blueprint $t) {
      $t->id();

      // Sở hữu bởi cá nhân hoặc thuộc công ty (ít nhất 1 trong 2)
      $t->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
      $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();

      // Thông tin chính
      $t->string('name');
      $t->string('job_title')->nullable();
      $t->string('company')->nullable();

      $t->string('email')->nullable();
      $t->string('phone')->nullable();

      $t->string('address_line1')->nullable();
      $t->string('address_line2')->nullable();
      $t->string('city')->nullable();
      $t->string('state')->nullable();
      $t->string('postal_code')->nullable();
      $t->string('country')->nullable();

      $t->string('linkedin_url')->nullable();
      $t->string('website_url')->nullable();

      // Meta & OCR
      $t->text('ocr_raw')->nullable();            // lưu JSON string raw OCR nếu muốn
      $t->unsignedBigInteger('duplicate_of_id')->nullable(); // tham chiếu trùng mềm
      $t->text('search_text')->nullable();        // concat để fulltext

      $t->string('source')->default('manual');    // manual|scan|import

      $t->timestamps();
      $t->softDeletes();

      // Index
      $t->index('owner_user_id');
      $t->index('company_id');
      $t->index('email');
      $t->index('phone');
      $t->fullText(['name','company','email','phone','search_text']);
    });
  }
  public function down(): void { Schema::dropIfExists('contacts'); }
};

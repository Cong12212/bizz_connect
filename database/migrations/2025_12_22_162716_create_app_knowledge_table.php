<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('app_knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50)->index();
            $table->string('key')->unique();
            $table->string('title');
            $table->json('content');
            $table->text('searchable_text')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('app_knowledge');
    }
};

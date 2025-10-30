<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Thêm cột address_id (nullable để không ảnh hưởng data cũ)
            $table->unsignedBigInteger('address_id')->nullable()->after('phone');

            // Thêm foreign key constraint
            $table->foreign('address_id')
                ->references('id')
                ->on('addresses')
                ->onDelete('set null'); // Khi xóa address thì set null, không xóa contact
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Xóa foreign key trước
            $table->dropForeign(['address_id']);

            // Xóa cột
            $table->dropColumn('address_id');
        });
    }
};

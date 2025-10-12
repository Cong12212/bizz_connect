<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('contact_reminder')) {
            Schema::create('contact_reminder', function (Blueprint $t) {
                // $t->engine = 'InnoDB'; // bật nếu bạn muốn chỉ định engine
                $t->unsignedBigInteger('contact_id');
                $t->unsignedBigInteger('reminder_id');
                $t->timestamps();

                // Khóa tổng hợp chống trùng
                $t->primary(['contact_id','reminder_id'], 'contact_reminder_pk');

                // Index đơn lẻ để tối ưu truy vấn theo 1 phía
                $t->index('contact_id',  'contact_reminder_contact_idx');
                $t->index('reminder_id', 'contact_reminder_reminder_idx');

                // FK
                $t->foreign('contact_id')
                  ->references('id')->on('contacts')
                  ->cascadeOnDelete();

                $t->foreign('reminder_id')
                  ->references('id')->on('reminders')
                  ->cascadeOnDelete();
            });
        }

        // Backfill: đưa contact chính (reminders.contact_id) vào pivot nếu chưa có.
        // Dùng anti-join để tránh duplicate và đảm bảo contact/reminder đều tồn tại.
        // MySQL:
        DB::statement("
            INSERT INTO contact_reminder (contact_id, reminder_id, created_at, updated_at)
            SELECT r.contact_id, r.id, NOW(), NOW()
            FROM reminders r
            INNER JOIN contacts c ON c.id = r.contact_id
            LEFT JOIN contact_reminder cr
                   ON cr.contact_id = r.contact_id
                  AND cr.reminder_id = r.id
            WHERE r.contact_id IS NOT NULL
              AND cr.contact_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_reminder');
    }
};

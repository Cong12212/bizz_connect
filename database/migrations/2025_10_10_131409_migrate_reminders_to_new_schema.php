<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    /** Check index exists */
    protected function indexExists(string $table, string $indexName): bool
    {
        $schema = DB::getDatabaseName();
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    /** Check FK on a column exists */
    protected function foreignKeyExists(string $table, string $column): bool
    {
        $schema = DB::getDatabaseName();
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $schema)
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }

    public function up(): void
    {
        // 1) Add columns (additive)
        Schema::table('reminders', function (Blueprint $t) {
            if (!Schema::hasColumn('reminders', 'contact_id')) {
                $t->foreignId('contact_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('reminders', 'title')) {
                $t->string('title')->nullable()->after('owner_user_id');
            }
            if (!Schema::hasColumn('reminders', 'note')) {
                $t->text('note')->nullable()->after('title');
            }
            if (!Schema::hasColumn('reminders', 'channel')) {
                $t->string('channel')->default('app')->after('status');
            }
            if (!Schema::hasColumn('reminders', 'external_event_id')) {
                $t->string('external_event_id')->nullable()->after('channel');
            }
            if (!Schema::hasColumn('reminders', 'deleted_at')) {
                $t->softDeletes();
            }
            if (!Schema::hasColumn('reminders', 'due_at')) {
                $t->dateTime('due_at')->nullable();
            }
        });

        // 1.1) Add FK for contact_id if missing
        if (Schema::hasColumn('reminders', 'contact_id') && ! $this->foreignKeyExists('reminders', 'contact_id')) {
            Schema::table('reminders', function (Blueprint $t) {
                $t->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            });
        }

        // 1.2) Add indexes only if missing
        if (! $this->indexExists('reminders', 'reminders_due_at_index')) {
            Schema::table('reminders', fn (Blueprint $t) => $t->index('due_at', 'reminders_due_at_index'));
        }
        if (! $this->indexExists('reminders', 'reminders_status_index')) {
            Schema::table('reminders', fn (Blueprint $t) => $t->index('status', 'reminders_status_index'));
        }

        // 2) Map data from old columns only if they exist
        if (Schema::hasColumn('reminders', 'name')) {
            DB::statement("UPDATE reminders SET title = COALESCE(title, name) WHERE title IS NULL OR title = ''");
        }
        if (Schema::hasColumn('reminders', 'description')) {
            DB::statement("UPDATE reminders SET note = COALESCE(note, description) WHERE note IS NULL OR note = ''");
        }

        // Normalize status (safe even if already in new values)
        DB::statement("
            UPDATE reminders
            SET status = CASE status
                WHEN 'open' THEN 'pending'
                WHEN 'done' THEN 'done'
                WHEN 'cancelled' THEN 'cancelled'
                ELSE status
            END
        ");

        // 3) Backfill contact_id from pivot (take MIN(contact_id))
        if (Schema::hasTable('contact_reminder')) {
            DB::statement("
                UPDATE reminders r
                JOIN (
                  SELECT reminder_id, MIN(contact_id) AS cid
                  FROM contact_reminder
                  GROUP BY reminder_id
                ) x ON x.reminder_id = r.id
                SET r.contact_id = x.cid
                WHERE r.contact_id IS NULL
            ");
        }

        // 4) Create placeholder contact per owner for remaining NULLs & assign
        DB::statement("
            INSERT INTO contacts (owner_user_id, name, job_title, company, email, phone, address, notes, source, created_at, updated_at)
            SELECT r.owner_user_id,
                   CONCAT('__UNASSIGNED_CONTACT__ (user ', r.owner_user_id, ')') AS name,
                   NULL, NULL, NULL, NULL, NULL,
                   'Auto-created for orphan reminders', 'system', NOW(), NOW()
            FROM reminders r
            LEFT JOIN contacts c
              ON c.owner_user_id = r.owner_user_id
             AND c.name = CONCAT('__UNASSIGNED_CONTACT__ (user ', r.owner_user_id, ')')
            WHERE r.contact_id IS NULL
              AND c.id IS NULL
            GROUP BY r.owner_user_id
        ");

        DB::statement("
            UPDATE reminders r
            JOIN contacts c
              ON c.owner_user_id = r.owner_user_id
             AND c.name = CONCAT('__UNASSIGNED_CONTACT__ (user ', r.owner_user_id, ')')
            SET r.contact_id = c.id
            WHERE r.contact_id IS NULL
        ");

        // (Optional) Enforce NOT NULL once sure there's no NULL remaining:
        // DB::statement('ALTER TABLE reminders MODIFY contact_id BIGINT UNSIGNED NOT NULL');
    }

    public function down(): void
    {
        // best-effort: drop indexes if exist
        try { DB::statement('ALTER TABLE reminders DROP INDEX reminders_due_at_index'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE reminders DROP INDEX reminders_status_index'); } catch (\Throwable $e) {}

        Schema::table('reminders', function (Blueprint $t) {
            if (Schema::hasColumn('reminders', 'deleted_at')) $t->dropSoftDeletes();
            if (Schema::hasColumn('reminders', 'external_event_id')) $t->dropColumn('external_event_id');
            if (Schema::hasColumn('reminders', 'channel')) $t->dropColumn('channel');
            if (Schema::hasColumn('reminders', 'note')) $t->dropColumn('note');
            if (Schema::hasColumn('reminders', 'title')) $t->dropColumn('title');

            if (Schema::hasColumn('reminders', 'contact_id')) {
                try { $t->dropForeign('reminders_contact_id_foreign'); } catch (\Throwable $e) {}
                $t->dropColumn('contact_id');
            }
        });
    }
};

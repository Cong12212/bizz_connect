<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // contacts: composite (owner_user_id, deleted_at) speeds up the base
        // WHERE owner_user_id = ? AND deleted_at IS NULL on every contacts query.
        Schema::table('contacts', function (Blueprint $table) {
            $table->index(['owner_user_id', 'deleted_at'], 'contacts_owner_deleted_idx');
        });

        // contact_tag: covering index (tag_id, contact_id) so the tag-filter
        // subquery SELECT contact_id FROM contact_tag WHERE tag_id IN (...)
        // GROUP BY contact_id HAVING COUNT(...) never needs to touch the main row.
        Schema::table('contact_tag', function (Blueprint $table) {
            $table->index(['tag_id', 'contact_id'], 'contact_tag_tag_contact_idx');
        });

        // reminders: composite indexes for the two most common filter patterns.
        Schema::table('reminders', function (Blueprint $table) {
            $table->index(['owner_user_id', 'deleted_at'], 'reminders_owner_deleted_idx');
            $table->index(['owner_user_id', 'status', 'due_at'], 'reminders_owner_status_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex('contacts_owner_deleted_idx');
        });

        Schema::table('contact_tag', function (Blueprint $table) {
            $table->dropIndex('contact_tag_tag_contact_idx');
        });

        Schema::table('reminders', function (Blueprint $table) {
            $table->dropIndex('reminders_owner_deleted_idx');
            $table->dropIndex('reminders_owner_status_due_idx');
        });
    }
};

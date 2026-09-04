<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telling people that something is waiting for them.
 *
 * The whole approval ladder assumes somebody notices a form has arrived. Until
 * now nothing said so — a demand could sit at tier 2 for a week because the
 * Administrative Officer had no reason to log in.
 *
 * This is Laravel's notifications table with one addition: tenant_id. A
 * notification is about work at ONE school, so somebody who works at two sees
 * only the ones belonging to the school they are currently in. Without that
 * column, switching schools would carry the other school's alerts across.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            // Nullable so a notification can outlive the school being deleted,
            // and so account-level messages have somewhere to sit.
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The bell's query: mine, at this school, unread first, newest first.
            $table->index(['notifiable_id', 'tenant_id', 'read_at'], 'idx_notifications_bell');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Settings are per school: the petty cash ceiling and the school's own
        // name are exactly the kind of thing that differs between them. The key
        // alone is therefore not enough to identify a row.
        Schema::create('app_settings', function (Blueprint $table) {
            // A surrogate id rather than a composite (tenant_id, key) primary
            // key: Eloquent builds its UPDATE from the primary key alone and
            // does not apply global scopes to save queries, so a composite key
            // it cannot see would have let one school's save rewrite them all.
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('key');
            $table->json('value');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['tenant_id', 'key']);
        });

        // APPEND ONLY. UPDATE and DELETE are revoked in the integrity migration.
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Nullable: a platform-owner action sits above any one school, and
            // signing in happens before a school has been chosen.
            $table->foreignUuid('tenant_id')->nullable()->constrained('tenants');
            $table->timestamp('at')->useCurrent();
            $table->foreignUuid('actor_id')->nullable()->constrained('users');
            $table->string('action');            // "DEMAND_APPROVED", "COUNT_ENTERED" …
            $table->string('entity');            // table name
            $table->string('entity_id')->nullable();
            $table->text('detail');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->index(['tenant_id', 'at']);
            $table->index(['tenant_id', 'entity', 'entity_id']);
            $table->index(['tenant_id', 'actor_id', 'at'], 'idx_audit_actor_at');
            $table->index(['tenant_id', 'action']);
        });

        // Per-school, per-fiscal-year counters for DF / PO / PC references,
        // bumped atomically. Each school's numbering starts at 0001.
        Schema::create('ref_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('prefix', 8);
            $table->string('fiscal_year', 12);
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['tenant_id', 'prefix', 'fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ref_counters');
        Schema::dropIfExists('audit_log');
        Schema::dropIfExists('app_settings');
    }
};

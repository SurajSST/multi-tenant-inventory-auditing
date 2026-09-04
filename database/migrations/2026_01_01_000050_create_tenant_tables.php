<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenancy: one database, many schools, keyed by tenant_id.
 *
 * The split that matters here is IDENTITY versus MEMBERSHIP.
 *
 *   users        who a person is. One row per human being. Global.
 *   tenant_users what that person is AT one school — their staff code,
 *                their designation, the tier they approve at, and whether
 *                they still work there.
 *
 * Somebody can be the Accounts Officer at one school and a Stock Auditor at
 * another, so a staff code and an approval tier are facts about a posting,
 * not about a person. Roles hang off the posting for the same reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');                 // "Prativa Secondary School"
            $table->string('slug')->unique();       // "prativa"
            $table->string('short_name')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->index('is_active');
        });

        // One row per person per school.
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('staff_code');
            $table->string('designation');
            // 0 = not on this school's approval chain.
            $table->unsignedTinyInteger('approval_tier')->default(0);
            // Ends the posting without touching the person's account.
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->useCurrent();

            $table->unique(['tenant_id', 'user_id']);
            $table->unique(['tenant_id', 'staff_code']);
            // The target every composite foreign key below points at.
            $table->unique(['tenant_id', 'id'], 'uniq_membership_tenant_id');
            $table->index(['tenant_id', 'approval_tier']);
        });

        // Roles are held at a school, not by a person in the abstract.
        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_user_id')->constrained('tenant_users')->cascadeOnDelete();
            $table->string('role', 32);

            $table->unique(['tenant_user_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('tenant_users');
        Schema::dropIfExists('tenants');
    }
};

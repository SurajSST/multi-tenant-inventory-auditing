<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every table here carries tenant_id, and every foreign key between them is a
 * COMPOSITE key on (tenant_id, parent_id). That is deliberate: it makes it
 * structurally impossible for one school's item type to sit under another
 * school's category, whatever the application layer believes. A global scope
 * that gets forgotten is a bug; a composite key that gets forgotten is a
 * migration that will not run.
 *
 * Each table also carries UNIQUE (tenant_id, id) — redundant for uniqueness,
 * since id is already the primary key, but required as the target a composite
 * foreign key can point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Blocks / locations. Each carries its own code prefix so a block can
        // be coded separately where the school needs that.
        Schema::create('locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');             // "Block A", "Hostel Wing"
            $table->string('code');             // "A"
            $table->string('note')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id'], 'uniq_location_tenant_id');
        });

        // Blocks an auditor is allowed to count in. No rows = every block in
        // that school. Hangs off the posting, not the person: somebody auditing
        // two schools has a separate scope at each.
        Schema::create('audit_scopes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('tenant_user_id');
            $table->uuid('location_id');
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['tenant_user_id', 'location_id']);
            $table->foreign(['tenant_id', 'tenant_user_id'])
                ->references(['tenant_id', 'id'])->on('tenant_users')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations')->cascadeOnDelete();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id'], 'uniq_category_tenant_id');
        });

        Schema::create('subcategories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('category_id');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['category_id', 'name']);
            $table->unique(['tenant_id', 'id'], 'uniq_subcategory_tenant_id');
            $table->foreign(['tenant_id', 'category_id'])
                ->references(['tenant_id', 'id'])->on('categories');
        });

        // One row per KIND of thing — "Chair — Senior" with prefix CHAIR.S.
        // The individual physical units are CHAIR.S.1, CHAIR.S.2 … (asset_units).
        Schema::create('item_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            // Code prefix exactly as it appears in the school's LIST_OF_INV.
            // Unique within the school; two schools may both use CHAIR.S.
            $table->string('code_prefix');
            $table->uuid('category_id');
            $table->uuid('subcategory_id')->nullable();
            $table->string('lifespan', 16);
            $table->string('unit_of_measure', 16)->default('PCS');
            // Last known rate, used only to pre-fill demand forms.
            $table->decimal('indicative_rate', 14, 2)->nullable();
            // Alert when the total across all blocks drops to or below this.
            $table->integer('reorder_level')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'code_prefix']);
            $table->unique(['tenant_id', 'id'], 'uniq_item_type_tenant_id');
            $table->index('category_id');
            $table->index(['tenant_id', 'lifespan']);

            $table->foreign(['tenant_id', 'category_id'])
                ->references(['tenant_id', 'id'])->on('categories');
            $table->foreign(['tenant_id', 'subcategory_id'])
                ->references(['tenant_id', 'id'])->on('subcategories');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_types');
        Schema::dropIfExists('subcategories');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('audit_scopes');
        Schema::dropIfExists('locations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // APPEND ONLY — see the integrity migration, which revokes UPDATE and
        // DELETE at the database level.
        //
        // Stock is a ledger, not a number. The current quantity for an
        // (item type, block) pair is the newest row. Correcting a miscount adds
        // a row carrying the previous figure; it never erases one.
        Schema::create('stock_count_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('item_type_id');
            $table->uuid('location_id');
            // Quantity standing after this entry.
            $table->integer('quantity');
            // Quantity before it, copied in so the trail reads on its own.
            $table->integer('previous_qty')->default(0);
            $table->string('source', 24);
            // Receipt this came from, when it was not a manual count.
            $table->uuid('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->foreignUuid('counted_by_id')->constrained('users');
            $table->timestamp('counted_at')->useCurrent();

            $table->index(['tenant_id', 'item_type_id', 'location_id', 'counted_at'], 'idx_counts_latest');
            $table->index('location_id');

            // The item and the block must belong to the same school as the entry.
            $table->foreign(['tenant_id', 'item_type_id'])
                ->references(['tenant_id', 'id'])->on('item_types');
            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations');
        });

        // Optional per-unit register for durables that need tracking one by one
        // (laptops, projectors, smart boards). unit_no is the running number
        // after the code prefix: LAPTOP.1, LAPTOP.2 …
        Schema::create('asset_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('item_type_id');
            $table->integer('unit_no');
            // Materialised full code, unique across the school.
            $table->string('unit_code');
            $table->uuid('location_id')->nullable();
            $table->string('serial_no')->nullable();
            $table->string('status', 16)->default('ACTIVE');
            $table->date('acquired_on')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'unit_code']);
            $table->unique(['item_type_id', 'unit_no']);
            $table->index('location_id');

            $table->foreign(['tenant_id', 'item_type_id'])
                ->references(['tenant_id', 'id'])->on('item_types');
            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_units');
        Schema::dropIfExists('stock_count_entries');
    }
};

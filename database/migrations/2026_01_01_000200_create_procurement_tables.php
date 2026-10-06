<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Configurable value bands, per school.
        Schema::create('approval_tiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedTinyInteger('tier_no');
            $table->decimal('min_amount', 14, 2);
            // null = "and above" — the top band.
            $table->decimal('max_amount', 14, 2)->nullable();
            $table->string('decider_label');
            // True for the committee band: a minute reference becomes mandatory.
            $table->boolean('requires_minute')->default(false);
            $table->boolean('is_active')->default(true);

            $table->unique(['tenant_id', 'tier_no']);
        });

        Schema::create('demand_forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('ref');                      // DF-2082-0001, per school
            $table->string('fiscal_year', 12);          // "2082/83"
            $table->foreignUuid('raised_by_id')->constrained('users');
            $table->string('department');
            $table->text('justification');
            $table->date('need_by_date')->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->string('status', 16)->default('PENDING');
            // Tier currently holding the form. Null once it is closed.
            $table->unsignedTinyInteger('current_tier')->nullable();
            // Highest tier that must sign, derived from the value at submission.
            $table->unsignedTinyInteger('final_tier');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->unique(['tenant_id', 'ref']);
            $table->unique(['tenant_id', 'id'], 'uniq_demand_tenant_id');
            $table->index(['tenant_id', 'status', 'current_tier']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'raised_by_id', 'status'], 'idx_demands_user_status');
            $table->index(['tenant_id', 'status', 'closed_at'], 'idx_demands_tenant_status_closed');
            $table->index(['tenant_id', 'status', 'created_at'], 'idx_demands_tenant_status_created');
            $table->index(['tenant_id', 'department', 'created_at'], 'idx_demands_tenant_dept_created');
        });

        Schema::create('demand_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('demand_id');
            // Null when the requester is asking for something not yet on the register.
            $table->uuid('item_type_id')->nullable();
            // Free text name, always filled (copied from the item type when linked).
            $table->string('item_name');
            $table->integer('quantity');
            $table->decimal('unit_rate', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('specification')->nullable();

            $table->unique(['tenant_id', 'id'], 'uniq_demand_line_tenant_id');
            $table->index('demand_id');
            $table->index('item_type_id');

            $table->foreign(['tenant_id', 'demand_id'])
                ->references(['tenant_id', 'id'])->on('demand_forms')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'item_type_id'])
                ->references(['tenant_id', 'id'])->on('item_types');
        });

        // APPEND ONLY. One decision per tier per form.
        Schema::create('demand_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('demand_id');
            $table->unsignedTinyInteger('tier_no');
            $table->foreignUuid('actor_id')->constrained('users');
            $table->string('action', 16);
            $table->text('reason')->nullable();
            // Committee minute reference, mandatory on the top band.
            $table->string('minute_ref')->nullable();
            $table->timestamp('acted_at')->useCurrent();

            $table->unique(['demand_id', 'tier_no']);

            $table->foreign(['tenant_id', 'demand_id'])
                ->references(['tenant_id', 'id'])->on('demand_forms')->cascadeOnDelete();
            // The band signed against has to be that school's own band.
            $table->foreign(['tenant_id', 'tier_no'])
                ->references(['tenant_id', 'tier_no'])->on('approval_tiers');
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('pan_vat')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'id'], 'uniq_vendor_tenant_id');
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('ref');                      // PO-2082-0001, per school
            $table->string('fiscal_year', 12);
            $table->uuid('demand_id')->nullable();
            $table->uuid('vendor_id');
            $table->decimal('order_amount', 14, 2);
            $table->date('expected_date')->nullable();
            $table->string('note')->nullable();
            $table->string('status', 16)->default('PLACED');
            $table->foreignUuid('ordered_by_id')->constrained('users');
            $table->timestamp('ordered_at')->useCurrent();

            $table->unique(['tenant_id', 'ref']);
            $table->unique(['tenant_id', 'id'], 'uniq_order_tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'ordered_by_id'], 'idx_orders_orderer');
            $table->index(['tenant_id', 'ordered_at'], 'idx_orders_tenant_ordered');
            $table->index(['tenant_id', 'status', 'ordered_at'], 'idx_orders_tenant_status_ordered');
            $table->index('vendor_id');
            $table->index('demand_id');

            $table->foreign(['tenant_id', 'demand_id'])
                ->references(['tenant_id', 'id'])->on('demand_forms');
            $table->foreign(['tenant_id', 'vendor_id'])
                ->references(['tenant_id', 'id'])->on('vendors');
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('purchase_order_id');
            $table->uuid('demand_line_id')->nullable();
            $table->uuid('item_type_id')->nullable();
            $table->string('description', 255);
            $table->integer('quantity_ordered');
            $table->string('unit', 30)->default('piece');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0.00);
            $table->decimal('tax', 14, 2)->default(0.00);
            $table->decimal('line_total', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('demand_line_id')->references('id')->on('demand_lines')->nullOnDelete();
            $table->foreign('item_type_id')->references('id')->on('item_types')->nullOnDelete();

            $table->index(['tenant_id', 'purchase_order_id']);
            $table->index(['demand_line_id']);
        });

        // The separation-of-duties gate. A CHECK constraint and a trigger in the
        // integrity migration refuse any row where received_by_id = ordered_by_id.
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('purchase_order_id');
            // Copied from the order purely so the CHECK constraint can compare them.
            // The trigger re-reads the order to confirm it was not faked.
            $table->uuid('ordered_by_id');
            $table->foreignUuid('received_by_id')->constrained('users');
            $table->uuid('location_id');
            $table->string('condition', 16)->default('GOOD');
            $table->text('discrepancy_note')->nullable();
            // Delivery challan / gate pass number.
            $table->string('challan_no')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('received_at')->useCurrent();

            $table->unique(['tenant_id', 'id'], 'uniq_receipt_tenant_id');
            $table->index(['tenant_id', 'purchase_order_id'], 'idx_receipt_tenant_po');
            $table->index(['tenant_id', 'received_at'], 'idx_receipts_tenant_received');
            $table->index('location_id');

            $table->foreign('ordered_by_id')->references('id')->on('users');
            $table->foreign(['tenant_id', 'purchase_order_id'])
                ->references(['tenant_id', 'id'])->on('purchase_orders');
            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations');
        });

        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('receipt_id');
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('demand_line_id');
            $table->uuid('location_id')->nullable();
            $table->integer('qty_ordered');
            $table->integer('qty_received');
            $table->string('remark')->nullable();

            $table->index('receipt_id');
            $table->index('demand_line_id');
            $table->index('purchase_order_line_id');
            $table->index(['tenant_id', 'demand_line_id'], 'idx_grl_tenant_demand_line');

            $table->foreign(['tenant_id', 'receipt_id'])
                ->references(['tenant_id', 'id'])->on('goods_receipts')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'demand_line_id'])
                ->references(['tenant_id', 'id'])->on('demand_lines');
            $table->foreign('purchase_order_line_id')
                ->references('id')->on('purchase_order_lines')->nullOnDelete();
            $table->foreign(['tenant_id', 'location_id'])
                ->references(['tenant_id', 'id'])->on('locations');
        });

        Schema::create('supplier_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('ref', 40);
            $table->string('fiscal_year', 10);
            $table->uuid('vendor_id');
            $table->uuid('purchase_order_id')->nullable();
            $table->uuid('goods_receipt_id')->nullable();
            $table->decimal('total_amount', 14, 2);
            $table->string('reason', 255);
            $table->string('status', 30)->default('POSTED');
            $table->uuid('returned_by_id');
            $table->timestamp('returned_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('vendor_id')->references('id')->on('vendors');
            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
            $table->foreign('goods_receipt_id')->references('id')->on('goods_receipts')->nullOnDelete();
            $table->foreign('returned_by_id')->references('id')->on('users');

            $table->index(['tenant_id', 'vendor_id']);
            $table->index(['purchase_order_id']);
            $table->index(['goods_receipt_id']);
        });

        Schema::create('supplier_return_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('supplier_return_id');
            $table->uuid('goods_receipt_line_id')->nullable();
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('item_type_id');
            $table->uuid('location_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('supplier_return_id')->references('id')->on('supplier_returns')->cascadeOnDelete();
            $table->foreign('goods_receipt_line_id')->references('id')->on('goods_receipt_lines')->nullOnDelete();
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete();
            $table->foreign('item_type_id')->references('id')->on('item_types');
            $table->foreign('location_id')->references('id')->on('locations');

            $table->index(['tenant_id', 'supplier_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_return_lines');
        Schema::dropIfExists('supplier_returns');
        Schema::dropIfExists('goods_receipt_lines');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('demand_approvals');
        Schema::dropIfExists('demand_lines');
        Schema::dropIfExists('demand_forms');
        Schema::dropIfExists('approval_tiers');
    }
};

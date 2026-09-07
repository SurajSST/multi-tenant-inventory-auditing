<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('bill_no');
            $table->string('fiscal_year', 12);
            $table->uuid('purchase_order_id')->nullable();
            $table->uuid('vendor_id');
            $table->date('bill_date');
            $table->decimal('bill_amount', 14, 2);
            $table->decimal('vat_amount', 14, 2)->default(0);
            // Snapshot of what was approved and what was ordered, frozen at entry.
            $table->decimal('approved_amount', 14, 2)->nullable();
            $table->decimal('ordered_amount', 14, 2)->nullable();
            $table->decimal('variance_amount', 14, 2)->default(0);
            $table->string('match_status', 24)->default('MATCHED');
            $table->string('payment_status', 20)->default('UNPAID');
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('attachment_path')->nullable();
            $table->foreignUuid('entered_by_id')->constrained('users');
            $table->timestamp('entered_at')->useCurrent();
            // Filled only when Accounts accepts a mismatch, with a written reason.
            $table->foreignUuid('cleared_by_id')->nullable()->constrained('users');
            $table->timestamp('cleared_at')->nullable();
            $table->text('variance_note')->nullable();

            $table->unique(['tenant_id', 'vendor_id', 'bill_no'], 'uniq_bills_vendor_bill');
            $table->unique(['tenant_id', 'id'], 'uniq_bill_tenant_id');
            $table->index(['tenant_id', 'match_status']);
            $table->index(['tenant_id', 'payment_status']);
            $table->index(['tenant_id', 'bill_date']);
            $table->index(['tenant_id', 'entered_at'], 'idx_bills_tenant_entered');
            $table->index(['tenant_id', 'match_status', 'entered_at'], 'idx_bills_tenant_status_entered');
            $table->index(['tenant_id', 'purchase_order_id'], 'idx_bills_tenant_po');
            $table->index('purchase_order_id');
            $table->index('vendor_id');

            $table->foreign(['tenant_id', 'purchase_order_id'])
                ->references(['tenant_id', 'id'])->on('purchase_orders');
            $table->foreign(['tenant_id', 'vendor_id'])
                ->references(['tenant_id', 'id'])->on('vendors');
        });

        Schema::create('bill_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('bill_id');
            $table->uuid('purchase_order_line_id')->nullable();
            $table->uuid('item_type_id')->nullable();
            $table->string('description', 255);
            $table->integer('quantity');
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount', 14, 2)->default(0.00);
            $table->decimal('tax', 14, 2)->default(0.00);
            $table->decimal('line_total', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants');
            $table->foreign('bill_id')->references('id')->on('bills')->cascadeOnDelete();
            $table->foreign('purchase_order_line_id')->references('id')->on('purchase_order_lines')->nullOnDelete();
            $table->foreign('item_type_id')->references('id')->on('item_types')->nullOnDelete();

            $table->index(['tenant_id', 'bill_id']);
            $table->index(['purchase_order_line_id']);
        });

        Schema::create('petty_cash_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('serial');                   // PC-2082-0001, per school
            $table->string('fiscal_year', 12);
            $table->string('bill_no');
            $table->date('bill_date')->nullable();
            $table->string('vendor_name');
            $table->decimal('amount', 14, 2);
            // Ceiling in force at the moment of issue, frozen for the record.
            $table->decimal('ceiling_at_issue', 14, 2);
            // The person physically standing in front of the issuer.
            $table->string('claimant_name');
            $table->string('purpose');
            // The issuer ticks this to confirm the original bill was sighted.
            $table->boolean('bill_sighted')->default(false);
            $table->string('status', 16)->default('ISSUED');
            $table->string('bill_claim_key')->storedAs("CASE WHEN status = 'VOIDED' THEN NULL ELSE bill_no END")->nullable();
            $table->foreignUuid('issued_by_id')->constrained('users');
            $table->timestamp('issued_at')->useCurrent();
            $table->foreignUuid('paid_by_id')->nullable()->constrained('users');
            $table->timestamp('paid_at')->nullable();
            $table->string('void_reason')->nullable();

            $table->unique(['tenant_id', 'serial']);
            $table->unique(['tenant_id', 'bill_claim_key'], 'uniq_live_token_per_bill');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'issued_at'], 'idx_tokens_issued_at');
            $table->index(['tenant_id', 'status', 'issued_at'], 'idx_tokens_tenant_status_issued');
            $table->index(['tenant_id', 'bill_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_tokens');
        Schema::dropIfExists('bill_lines');
        Schema::dropIfExists('bills');
    }
};

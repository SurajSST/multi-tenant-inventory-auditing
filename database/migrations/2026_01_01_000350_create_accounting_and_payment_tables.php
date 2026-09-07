<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Chart of Accounts
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('code', 20);                  // 1010, 1200, 2000, etc.
            $table->string('name');
            $table->string('type', 20);                  // ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'code']);
            $table->unique(['tenant_id', 'id'], 'uniq_account_tenant_id');
            $table->index(['tenant_id', 'type']);
        });

        // 2. Journal Entries (General Ledger Header)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entry_no');                  // JV-2082-0001
            $table->string('fiscal_year', 12);
            $table->date('entry_date');
            $table->string('reference_type', 50)->nullable(); // GOODS_RECEIPT, BILL, PAYMENT, PETTY_CASH, MANUAL
            $table->uuid('reference_id')->nullable();
            $table->text('memo')->nullable();
            $table->foreignUuid('posted_by_id')->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'entry_no']);
            $table->unique(['tenant_id', 'id'], 'uniq_journal_tenant_id');
            $table->index(['tenant_id', 'entry_date']);
            $table->index(['tenant_id', 'reference_type', 'reference_id'], 'idx_journal_ref');
        });

        // 3. Journal Entry Lines (Debits and Credits)
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('journal_entry_id');
            $table->uuid('account_id');
            $table->decimal('debit', 14, 2)->default(0.00);
            $table->decimal('credit', 14, 2)->default(0.00);
            $table->string('description')->nullable();

            $table->index('journal_entry_id');
            $table->index('account_id');
            $table->index(['tenant_id', 'account_id'], 'idx_jel_tenant_account');
            $table->index(['tenant_id', 'journal_entry_id', 'account_id'], 'idx_jel_tenant_je_acc');

            $table->foreign(['tenant_id', 'journal_entry_id'])
                ->references(['tenant_id', 'id'])->on('journal_entries')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'account_id'])
                ->references(['tenant_id', 'id'])->on('accounts');
        });

        // 4. Payments (Accounts Payable Disbursement Header)
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('voucher_no');                // PV-2082-0001
            $table->string('fiscal_year', 12);
            $table->uuid('vendor_id');
            $table->date('payment_date');
            $table->decimal('amount', 14, 2);
            $table->string('payment_method', 20);        // BANK_TRANSFER, CHEQUE, CASH
            $table->string('reference_no')->nullable();  // Cheque / Bank Txn ID
            $table->foreignUuid('paid_by_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'voucher_no']);
            $table->unique(['tenant_id', 'id'], 'uniq_payment_tenant_id');
            $table->index(['tenant_id', 'payment_date']);
            $table->index('vendor_id');

            $table->foreign(['tenant_id', 'vendor_id'])
                ->references(['tenant_id', 'id'])->on('vendors');
        });

        // 5. Payment Allocations (Bill Settlements)
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->uuid('payment_id');
            $table->uuid('bill_id');
            $table->decimal('amount_allocated', 14, 2);
            $table->timestamp('created_at')->useCurrent();

            $table->index('payment_id');
            $table->index('bill_id');

            $table->foreign(['tenant_id', 'payment_id'])
                ->references(['tenant_id', 'id'])->on('payments')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'bill_id'])
                ->references(['tenant_id', 'id'])->on('bills');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};

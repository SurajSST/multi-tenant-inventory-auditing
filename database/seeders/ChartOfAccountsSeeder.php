<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);

        if (! $context->has()) {
            foreach (Tenant::all() as $tenant) {
                $context->runFor($tenant, fn () => $this->seedAccounts());
            }
        } else {
            $this->seedAccounts();
        }
    }

    private function seedAccounts(): void
    {
        $accounts = [
            // Assets
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => 'ASSET', 'description' => 'Physical cash in school safe'],
            ['code' => '1020', 'name' => 'Operating Bank Account', 'type' => 'ASSET', 'description' => 'Main institutional operating bank account'],
            ['code' => '1030', 'name' => 'Petty Cash Float', 'type' => 'ASSET', 'description' => 'Imprest petty cash float for minor expenses'],
            ['code' => '1200', 'name' => 'Inventory Asset', 'type' => 'ASSET', 'description' => 'Value of stock and durables held in stores'],
            ['code' => '1300', 'name' => 'VAT Receivable', 'type' => 'ASSET', 'description' => 'Input VAT paid on purchases'],

            // Liabilities
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'LIABILITY', 'description' => 'Outstanding obligations to suppliers for verified bills'],
            ['code' => '2100', 'name' => 'Goods Received Not Invoiced (GRNI)', 'type' => 'LIABILITY', 'description' => 'Accrued liability for goods received awaiting supplier invoice'],

            // Equity
            ['code' => '3000', 'name' => 'Institutional Fund / Retained Earnings', 'type' => 'EQUITY', 'description' => 'School accumulated surplus and reserves'],

            // Revenue
            ['code' => '4000', 'name' => 'Institutional Grants & Revenue', 'type' => 'REVENUE', 'description' => 'School operational revenue and allocations'],

            // Expenses
            ['code' => '5010', 'name' => 'Educational & Office Supplies Expense', 'type' => 'EXPENSE', 'description' => 'Consumables, classroom materials, and stationery'],
            ['code' => '5020', 'name' => 'Repairs & Maintenance Expense', 'type' => 'EXPENSE', 'description' => 'Upkeep of furniture, lab gear, and facilities'],
            ['code' => '5030', 'name' => 'Utilities & General Operations', 'type' => 'EXPENSE', 'description' => 'Daily running costs and administrative sundries'],
            ['code' => '5040', 'name' => 'Inventory Shrinkage & Adjustment', 'type' => 'EXPENSE', 'description' => 'Audit write-downs, variances, and damages'],
        ];

        foreach ($accounts as $acc) {
            Account::firstOrCreate(
                ['code' => $acc['code']],
                [
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'description' => $acc['description'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Enums\DemandStatus;
use App\Enums\MatchStatus;
use App\Enums\OrderStatus;
use App\Enums\TokenStatus;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\DemandApproval;
use App\Models\DemandForm;
use App\Models\DemandLine;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\PettyCashToken;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Support\FiscalYear;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TestingDataSeeder extends Seeder
{
    public function run(): void
    {
        // This seeder fabricates demand forms, orders, a goods receipt, a bill
        // and petty cash tokens. Those land in append-only tables that the
        // database refuses UPDATE and DELETE on — so on a real install they
        // could never be taken back out again. It runs in local, or not at all.
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn(
                '  Demo data is for local development only — skipped. '.
                'These records cannot be deleted once written.'
            );

            return;
        }

        $fy = FiscalYear::label();
        $admin = User::where('email', 'admin@gmail.com')->first() ?? User::where('email', 'md@prativa.edu.np')->first();
        $hod = User::where('email', 'hod.science@prativa.edu.np')->first() ?? $admin;
        $adminOfficer = User::where('email', 'admin.officer@prativa.edu.np')->first() ?? $admin;
        $md = User::where('email', 'md@prativa.edu.np')->first() ?? $admin;
        $chairman = User::where('email', 'chairman@prativa.edu.np')->first() ?? $admin;
        $purchaseOfficer = User::where('email', 'purchase@prativa.edu.np')->first() ?? $admin;
        $receivingOfficer = User::where('email', 'store@prativa.edu.np')->first() ?? $admin;
        $accountsOfficer = User::where('email', 'accounts@prativa.edu.np')->first() ?? $admin;
        $teacher = User::where('email', 'p.karki@prativa.edu.np')->first() ?? $admin;

        $blockA = Location::where('code', 'A')->first() ?? Location::first();
        $blockB = Location::where('code', 'B')->first() ?? Location::first();
        $blockC = Location::where('code', 'C')->first() ?? Location::first();

        $tableItem = ItemType::where('code_prefix', '2024.3T')->first() ?? ItemType::first();
        $chairItem = ItemType::where('code_prefix', 'CHAIR.S')->first() ?? ItemType::first();
        $paperItem = ItemType::where('code_prefix', 'PPR.A4')->first() ?? ItemType::skip(1)->first();
        $markerItem = ItemType::where('code_prefix', 'MRK.BK')->first() ?? ItemType::skip(2)->first();

        // 1. Seed Vendors
        $v1 = Vendor::updateOrCreate(
            ['name' => 'Nepal IT Solutions Pvt. Ltd.'],
            ['pan_vat' => '601234567', 'phone' => '01-4433221', 'address' => 'Putalisadak, Kathmandu', 'is_active' => true]
        );
        $v2 = Vendor::updateOrCreate(
            ['name' => 'Sajha Pustak Bhandar'],
            ['pan_vat' => '300456789', 'phone' => '01-4221199', 'address' => 'Bagbazar, Kathmandu', 'is_active' => true]
        );
        $v3 = Vendor::updateOrCreate(
            ['name' => 'Everest Educational Furniture'],
            ['pan_vat' => '609876543', 'phone' => '01-5544332', 'address' => 'Patan Industrial Estate, Lalitpur', 'is_active' => true]
        );
        $v4 = Vendor::updateOrCreate(
            ['name' => 'Himalayan Scientific Suppliers'],
            ['pan_vat' => '605554443', 'phone' => '01-4788990', 'address' => 'Tripureshwor, Kathmandu', 'is_active' => true]
        );

        // 2. Seed Demand Forms
        // Demand 1: Completed & Received Procurement (DF-2082-0001)
        $d1 = DemandForm::updateOrCreate(
            ['ref' => 'DF-2082-0001'],
            [
                'fiscal_year' => $fy,
                'raised_by_id' => $teacher->id,
                'department' => 'Grade 8 & 9 Classrooms',
                'justification' => 'Replacement 3-seater student study tables and ergonomic chairs for secondary wing.',
                'need_by_date' => Carbon::now()->addDays(10),
                'total_amount' => 145000.00,
                'status' => DemandStatus::APPROVED,
                'current_tier' => null,
                'final_tier' => 3,
                'created_at' => Carbon::now()->subDays(15),
                'closed_at' => Carbon::now()->subDays(12),
            ]
        );
        DemandLine::firstOrCreate(
            ['demand_id' => $d1->id, 'item_name' => '2024 · 3 Seater Table (Steel Frame)'],
            ['item_type_id' => $tableItem?->id, 'quantity' => 10, 'unit_rate' => 8500.00, 'line_total' => 85000.00, 'specification' => 'Powder coated steel frame with waterproof ply']
        );
        DemandLine::firstOrCreate(
            ['demand_id' => $d1->id, 'item_name' => 'Student Chair with Wooden Plank Back'],
            ['item_type_id' => $chairItem?->id, 'quantity' => 20, 'unit_rate' => 3000.00, 'line_total' => 60000.00, 'specification' => 'High durability seasoned wood']
        );
        DemandApproval::firstOrCreate(['demand_id' => $d1->id, 'tier_no' => 1], ['actor_id' => $hod->id, 'action' => 'APPROVE', 'reason' => 'Classroom seats urgently needed', 'acted_at' => Carbon::now()->subDays(14)]);
        DemandApproval::firstOrCreate(['demand_id' => $d1->id, 'tier_no' => 2], ['actor_id' => $adminOfficer->id, 'action' => 'APPROVE', 'reason' => 'Within secondary section quota', 'acted_at' => Carbon::now()->subDays(13)]);
        DemandApproval::firstOrCreate(['demand_id' => $d1->id, 'tier_no' => 3], ['actor_id' => $md->id, 'action' => 'APPROVE', 'reason' => 'Approved by MD', 'acted_at' => Carbon::now()->subDays(12)]);

        // Purchase Order 1 for Demand 1
        $po1 = PurchaseOrder::updateOrCreate(
            ['ref' => 'PO-2082-0001'],
            [
                'fiscal_year' => $fy,
                'demand_id' => $d1->id,
                'vendor_id' => $v3->id,
                'order_amount' => 145000.00,
                'expected_date' => Carbon::now()->subDays(5),
                'note' => 'Deliver directly to Block B storage gate.',
                'status' => OrderStatus::RECEIVED,
                'ordered_by_id' => $purchaseOfficer->id,
                'ordered_at' => Carbon::now()->subDays(10),
            ]
        );

        // Goods Receipt for PO 1 (Separation of duties: received_by != ordered_by)
        $gr1 = GoodsReceipt::updateOrCreate(
            ['purchase_order_id' => $po1->id],
            [
                'ordered_by_id' => $purchaseOfficer->id,
                'received_by_id' => $receivingOfficer->id,
                'location_id' => $blockB->id,
                'condition' => 'GOOD',
                'discrepancy_note' => null,
                'challan_no' => 'CH-8821',
                'received_at' => Carbon::now()->subDays(4),
            ]
        );
        $d1Lines = $d1->lines;
        if ($d1Lines->count() >= 2) {
            GoodsReceiptLine::firstOrCreate(['receipt_id' => $gr1->id, 'demand_line_id' => $d1Lines[0]->id], ['qty_ordered' => 10, 'qty_received' => 10, 'remark' => 'Verified condition']);
            GoodsReceiptLine::firstOrCreate(['receipt_id' => $gr1->id, 'demand_line_id' => $d1Lines[1]->id], ['qty_ordered' => 20, 'qty_received' => 20, 'remark' => 'Verified condition']);
        }

        // Bill for PO 1 (3-Way Match 100% Exact)
        Bill::updateOrCreate(
            ['bill_no' => 'EV-2082-901'],
            [
                'fiscal_year' => $fy,
                'purchase_order_id' => $po1->id,
                'vendor_id' => $v3->id,
                'bill_date' => Carbon::now()->subDays(3),
                'bill_amount' => 145000.00,
                'vat_amount' => 16681.42,
                'approved_amount' => 145000.00,
                'ordered_amount' => 145000.00,
                'variance_amount' => 0.00,
                'match_status' => MatchStatus::MATCHED,
                'entered_by_id' => $accountsOfficer->id,
                'entered_at' => Carbon::now()->subDays(2),
            ]
        );

        // Demand 2: In-Review Demand (Pending Tier 3 MD Signature)
        $d2 = DemandForm::updateOrCreate(
            ['ref' => 'DF-2082-0002'],
            [
                'fiscal_year' => $fy,
                'raised_by_id' => $teacher->id,
                'department' => 'Science Department',
                'justification' => 'Annual replenishment of examination answer booklets, A4 papers, and whiteboard markers.',
                'need_by_date' => Carbon::now()->addDays(5),
                'total_amount' => 78000.00,
                'status' => DemandStatus::PENDING,
                'current_tier' => 3,
                'final_tier' => 3,
                'created_at' => Carbon::now()->subDays(3),
            ]
        );
        DemandLine::firstOrCreate(
            ['demand_id' => $d2->id, 'item_name' => 'A4 Paper 75 GSM (Carton of 5 Reams)'],
            ['item_type_id' => $paperItem?->id, 'quantity' => 20, 'unit_rate' => 2800.00, 'line_total' => 56000.00, 'specification' => 'Double A / B2B Bright White']
        );
        DemandLine::firstOrCreate(
            ['demand_id' => $d2->id, 'item_name' => 'Black Board Marker (Box of 12)'],
            ['item_type_id' => $markerItem?->id, 'quantity' => 20, 'unit_rate' => 1100.00, 'line_total' => 22000.00, 'specification' => 'Refillable bullet tip']
        );
        DemandApproval::firstOrCreate(['demand_id' => $d2->id, 'tier_no' => 1], ['actor_id' => $hod->id, 'action' => 'APPROVE', 'reason' => 'Required for terminal examinations', 'acted_at' => Carbon::now()->subDays(2)]);
        DemandApproval::firstOrCreate(['demand_id' => $d2->id, 'tier_no' => 2], ['actor_id' => $adminOfficer->id, 'action' => 'APPROVE', 'reason' => 'Stationery stock verified below minimum threshold', 'acted_at' => Carbon::now()->subDay()]);

        // Demand 3: Approved & In-Transit PO (DF-2082-0003)
        $d3 = DemandForm::updateOrCreate(
            ['ref' => 'DF-2082-0003'],
            [
                'fiscal_year' => $fy,
                'raised_by_id' => $teacher->id,
                'department' => 'Computer Lab',
                'justification' => 'Network patch cords and Cat6 RJ45 connectors for IT lab expansion.',
                'need_by_date' => Carbon::now()->addDays(7),
                'total_amount' => 18500.00,
                'status' => DemandStatus::APPROVED,
                'current_tier' => null,
                'final_tier' => 2,
                'created_at' => Carbon::now()->subDays(4),
                'closed_at' => Carbon::now()->subDays(2),
            ]
        );
        DemandLine::firstOrCreate(
            ['demand_id' => $d3->id, 'item_name' => 'Cat6 UTP Cable Roll (305m)'],
            ['quantity' => 1, 'unit_rate' => 14500.00, 'line_total' => 14500.00, 'specification' => 'D-Link Pure Copper']
        );
        DemandLine::firstOrCreate(
            ['demand_id' => $d3->id, 'item_name' => 'RJ45 Modular Plugs (Box of 100)'],
            ['quantity' => 2, 'unit_rate' => 2000.00, 'line_total' => 4000.00, 'specification' => 'Gold plated pins']
        );
        DemandApproval::firstOrCreate(['demand_id' => $d3->id, 'tier_no' => 1], ['actor_id' => $hod->id, 'action' => 'APPROVE', 'reason' => 'Lab networking setup', 'acted_at' => Carbon::now()->subDays(3)]);
        DemandApproval::firstOrCreate(['demand_id' => $d3->id, 'tier_no' => 2], ['actor_id' => $adminOfficer->id, 'action' => 'APPROVE', 'reason' => 'Approved under IT budget', 'acted_at' => Carbon::now()->subDays(2)]);

        PurchaseOrder::updateOrCreate(
            ['ref' => 'PO-2082-0002'],
            [
                'fiscal_year' => $fy,
                'demand_id' => $d3->id,
                'vendor_id' => $v1->id,
                'order_amount' => 18500.00,
                'expected_date' => Carbon::now()->addDays(2),
                'note' => 'Delivery expected tomorrow.',
                'status' => OrderStatus::PLACED,
                'ordered_by_id' => $purchaseOfficer->id,
                'ordered_at' => Carbon::now()->subDay(),
            ]
        );

        // Demand 4: Executive Committee Requisition (DF-2082-0004) - Tier 4 Pending
        $d4 = DemandForm::updateOrCreate(
            ['ref' => 'DF-2082-0004'],
            [
                'fiscal_year' => $fy,
                'raised_by_id' => $teacher->id,
                'department' => 'Multimedia Auditorium',
                'justification' => '75-inch 4K Interactive Flat Panel Display for digital smart auditorium.',
                'need_by_date' => Carbon::now()->addDays(15),
                'total_amount' => 240000.00,
                'status' => DemandStatus::PENDING,
                'current_tier' => 4,
                'final_tier' => 4,
                'created_at' => Carbon::now()->subDays(2),
            ]
        );
        DemandLine::firstOrCreate(
            ['demand_id' => $d4->id, 'item_name' => '75" Interactive Flat Panel 4K UHD'],
            ['quantity' => 1, 'unit_rate' => 240000.00, 'line_total' => 240000.00, 'specification' => 'Android 13 + Windows OPS i5 16GB']
        );
        DemandApproval::firstOrCreate(['demand_id' => $d4->id, 'tier_no' => 1], ['actor_id' => $hod->id, 'action' => 'APPROVE', 'reason' => 'Smart classroom upgrade', 'acted_at' => Carbon::now()->subDays(2)]);
        DemandApproval::firstOrCreate(['demand_id' => $d4->id, 'tier_no' => 2], ['actor_id' => $adminOfficer->id, 'action' => 'APPROVE', 'reason' => 'Technical specifications verified', 'acted_at' => Carbon::now()->subDay()]);
        DemandApproval::firstOrCreate(['demand_id' => $d4->id, 'tier_no' => 3], ['actor_id' => $md->id, 'action' => 'APPROVE', 'reason' => 'Forwarded to Chairman & Board', 'acted_at' => Carbon::now()->subDay()]);

        // 3. Seed Petty Cash Tokens
        PettyCashToken::updateOrCreate(
            ['serial' => 'PC-2082-0001'],
            [
                'fiscal_year' => $fy,
                'bill_no' => 'SB-9912',
                'bill_date' => Carbon::now()->subDays(5),
                'vendor_name' => 'Sajha Pustak Bhandar',
                'amount' => 4500.00,
                'ceiling_at_issue' => 15000.00,
                'claimant_name' => 'G. Dahal (Staff)',
                'purpose' => 'Urgent student report card jackets and gold seal stickers',
                'bill_sighted' => true,
                'status' => TokenStatus::PAID,
                'issued_by_id' => $accountsOfficer->id,
                'issued_at' => Carbon::now()->subDays(5),
                'paid_by_id' => $accountsOfficer->id,
                'paid_at' => Carbon::now()->subDays(5),
            ]
        );

        PettyCashToken::updateOrCreate(
            ['serial' => 'PC-2082-0002'],
            [
                'fiscal_year' => $fy,
                'bill_no' => 'HW-4412',
                'bill_date' => Carbon::now()->subDay(),
                'vendor_name' => 'Laxmi Hardware & Sanitary',
                'amount' => 3200.00,
                'ceiling_at_issue' => 15000.00,
                'claimant_name' => 'B. Thapa (Admin)',
                'purpose' => 'Emergency science lab tap replacement and Teflon sealing tapes',
                'bill_sighted' => true,
                'status' => TokenStatus::ISSUED,
                'issued_by_id' => $accountsOfficer->id,
                'issued_at' => Carbon::now()->subDay(),
            ]
        );

        // 4. Seed Audit Logs
        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'SIGN_IN',
            'entity' => 'users',
            'entity_id' => $admin->id,
            'detail' => 'Administrator logged into the inventory management console',
            'ip' => '127.0.0.1',
            'at' => Carbon::now()->subMinutes(10),
        ]);

        AuditLog::create([
            'actor_id' => $md->id,
            'action' => 'APPROVE_DEMAND',
            'entity' => 'demand_forms',
            'entity_id' => $d1->id,
            'detail' => "Signed Level 3 approval for {$d1->ref} (Rs. 145,000)",
            'ip' => '127.0.0.1',
            'at' => Carbon::now()->subDays(12),
        ]);
    }
}

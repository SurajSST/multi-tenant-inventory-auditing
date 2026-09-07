<?php

namespace Database\Seeders;

use App\Enums\CountSource;
use App\Enums\DemandStatus;
use App\Enums\Lifespan;
use App\Enums\MatchStatus;
use App\Enums\OrderStatus;
use App\Enums\ReceiptCondition;
use App\Enums\Role;
use App\Enums\TokenStatus;
use App\Enums\UnitStatus;
use App\Models\Account;
use App\Models\Category;
use App\Models\ItemType;
use App\Models\Location;
use App\Models\Subcategory;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\Vendor;
use App\Support\FiscalYear;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LargeScaleDataSeeder extends Seeder
{
    private string $fy;

    private string $startYear;

    public function run(): void
    {
        $this->fy = FiscalYear::label();
        $this->startYear = FiscalYear::startYear();

        $prativa = Tenant::firstOrCreate(
            ['slug' => 'prativa'],
            [
                'name' => 'Prativa Secondary School',
                'short_name' => 'Prativa',
                'logo_url' => null,
                'is_active' => true,
            ]
        );

        $everest = Tenant::firstOrCreate(
            ['slug' => 'everest'],
            [
                'name' => 'Everest English Academy',
                'short_name' => 'Everest',
                'logo_url' => null,
                'is_active' => true,
            ]
        );

        $this->command?->info("Seeding large-scale test dataset for {$prativa->name} and {$everest->name}...");

        $this->seedTenantData($prativa, 'PRAT');
        $this->seedTenantData($everest, 'EVER');

        $this->command?->info('Large scale test data generation completed successfully!');
    }

    private function seedTenantData(Tenant $tenant, string $prefixCode): void
    {
        $this->command?->line("--- Seeding {$tenant->name} ({$tenant->slug}) ---");

        app(TenantContext::class)->runFor($tenant, function () use ($tenant, $prefixCode) {
            $context = app(TenantContext::class);
            $tenantId = $tenant->id;

            // 1. Staff Members & Roles
            $staff = $this->ensureStaffMembers($tenant);

            // 2. Locations (10 locations)
            $locations = $this->ensureLocations($tenantId);

            // 3. Categories & Subcategories
            $this->ensureCategoriesAndSubcategories($tenantId);

            // 4. Item Types (Durable & Consumable, ~60 item types)
            $itemTypes = $this->ensureItemTypes($tenantId);

            // 5. Chart of Accounts
            $accounts = $this->ensureAccounts($tenantId);

            // 6. Vendors (~25 suppliers)
            $vendors = $this->ensureVendors($tenantId, $prefixCode);

            // 7. Asset Units (~1,250 units per school)
            $this->seedAssetUnits($tenantId, $itemTypes, $locations, $prefixCode);

            // 8. Procurement Pipeline (Demands, Approvals, POs, Receipts, Bills, Payments, Allocations)
            $this->seedProcurementAndFinance($tenantId, $staff, $vendors, $itemTypes, $locations, $accounts, $prefixCode);

            // 9. Petty Cash Tokens (~130 tokens)
            $this->seedPettyCashTokens($tenantId, $staff, $prefixCode);

            // 10. Stock Count Entries (~250 historical counts)
            $this->seedStockCountEntries($tenantId, $staff, $itemTypes, $locations);

            // 11. Supplier Returns (~25 returns with lines)
            $this->seedSupplierReturns($tenantId, $staff, $vendors, $itemTypes, $locations, $prefixCode);

            // 12. Audit Logs (~900 logs per school)
            $this->seedAuditLogs($tenantId, $staff);

            // 13. Notifications (~100 notifications)
            $this->seedNotifications($tenantId, $staff);
        });
    }

    /**
     * Ensure staff members exist and have proper roles in this tenant.
     *
     * @return array<string, User>
     */
    private function ensureStaffMembers(Tenant $tenant): array
    {
        $domain = ($tenant->slug ?? 'prativa').'.edu.np';
        $password = Hash::make(config('prativa.seed_password', 'Prativa@2026'));

        $staffDefs = [
            'md' => ['name' => 'S. Sharma', 'designation' => 'Managing Director', 'roles' => ['SUPER_ADMIN', 'APPROVER', 'INITIATOR'], 'tier' => 3, 'code' => 'STF-001'],
            'chairman' => ['name' => 'R. Gurung', 'designation' => 'Chairman', 'roles' => ['CHAIRMAN'], 'tier' => 4, 'code' => 'STF-002'],
            'admin_officer' => ['name' => 'B. Thapa', 'designation' => 'Administrative Officer', 'roles' => ['APPROVER', 'INITIATOR'], 'tier' => 2, 'code' => 'STF-003'],
            'hod_science' => ['name' => 'M. Adhikari', 'designation' => 'Head of Science Dept', 'roles' => ['APPROVER', 'INITIATOR'], 'tier' => 1, 'code' => 'STF-004'],
            'purchase_officer' => ['name' => 'K. Poudel', 'designation' => 'Purchase Officer', 'roles' => ['PURCHASE_OFFICER', 'INITIATOR'], 'tier' => 0, 'code' => 'STF-005'],
            'receiving_officer' => ['name' => 'S. Lama', 'designation' => 'Store Keeper / Receiver', 'roles' => ['RECEIVING_OFFICER', 'INITIATOR'], 'tier' => 0, 'code' => 'STF-006'],
            'accounts_officer' => ['name' => 'A. Shrestha', 'designation' => 'Accounts Officer', 'roles' => ['ACCOUNTS', 'INITIATOR'], 'tier' => 0, 'code' => 'STF-007'],
            'accounts_assistant' => ['name' => 'N. Rai', 'designation' => 'Accounts Assistant', 'roles' => ['ACCOUNTS', 'INITIATOR'], 'tier' => 0, 'code' => 'STF-008'],
            'auditor' => ['name' => 'D. Bhattarai', 'designation' => 'Stock Auditor', 'roles' => ['AUDITOR', 'INITIATOR'], 'tier' => 0, 'code' => 'STF-009'],
            'teacher1' => ['name' => 'P. Karki', 'designation' => 'Senior Teacher Grade 8', 'roles' => ['INITIATOR'], 'tier' => 0, 'code' => 'STF-010'],
            'teacher2' => ['name' => 'T. Sharma', 'designation' => 'Mathematics Faculty', 'roles' => ['INITIATOR'], 'tier' => 0, 'code' => 'STF-011'],
            'teacher3' => ['name' => 'S. Adhikari', 'designation' => 'IT & Computer Teacher', 'roles' => ['INITIATOR'], 'tier' => 0, 'code' => 'STF-012'],
        ];

        $users = [];
        foreach ($staffDefs as $key => $def) {
            $user = User::firstOrCreate(
                ['email' => "{$key}@{$domain}"],
                [
                    'full_name' => $def['name'],
                    'password' => $password,
                    'is_active' => true,
                    'must_reset_password' => false,
                ]
            );

            $membership = TenantUser::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $user->id],
                [
                    'staff_code' => $def['code'],
                    'designation' => $def['designation'],
                    'approval_tier' => $def['tier'],
                    'is_active' => true,
                ]
            );

            $membership->syncRoles($def['roles']);
            $users[$key] = $user;
        }

        // Attach platform owner as super admin
        $owner = User::where('email', 'admin@gmail.com')->first();
        if ($owner) {
            $ownerMembership = TenantUser::firstOrCreate(
                ['tenant_id' => $tenant->id, 'user_id' => $owner->id],
                [
                    'staff_code' => 'ADMIN-SYS',
                    'designation' => 'Platform Administrator',
                    'approval_tier' => 4,
                    'is_active' => true,
                ]
            );
            $ownerMembership->syncRoles(array_map(fn (Role $r) => $r->value, Role::cases()));
        }

        return $users;
    }

    /**
     * Ensure 10 diverse locations per school.
     */
    private function ensureLocations(string $tenantId): array
    {
        $locs = [
            ['code' => 'A', 'name' => 'Block A (Junior Wing)'],
            ['code' => 'B', 'name' => 'Block B (Middle Wing)'],
            ['code' => 'C', 'name' => 'Block C (Senior Wing)'],
            ['code' => 'D', 'name' => 'Block D (Hostel & Quarters)'],
            ['code' => 'E', 'name' => 'Block E (Administration)'],
            ['code' => 'F', 'name' => 'Block F (Auditorium & Sports)'],
            ['code' => 'SCI', 'name' => 'Science & Discovery Lab'],
            ['code' => 'ICT', 'name' => 'Computer & Robotics Lab'],
            ['code' => 'LIB', 'name' => 'Central School Library'],
            ['code' => 'STR', 'name' => 'Central Warehouse & Store'],
        ];

        $results = [];
        foreach ($locs as $loc) {
            $obj = Location::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $loc['code']],
                ['name' => $loc['name'], 'is_active' => true]
            );
            $results[] = $obj;
        }

        return $results;
    }

    /**
     * Categories & Subcategories.
     */
    private function ensureCategoriesAndSubcategories(string $tenantId): void
    {
        $cats = [
            ['Furniture', 'FUR', ['Student Desks & Benches', 'Chairs', 'Tables & Storage', 'Classroom Podiums']],
            ['Computers & IT', 'ICT', ['Lab Systems', 'Office Systems', 'Peripherals', 'Network Hardware']],
            ['Teaching Aids', 'TCH', ['Boards', 'Projection', 'Audio Visual', 'Lab Demonstration Kits']],
            ['Electronics & Appliances', 'ELE', ['Power & Cooling', 'Security & Surveillance', 'Public Address']],
            ['Science Lab Equipment', 'LAB', ['Glassware & Instruments', 'Measurement & Meters', 'Optics & Microscopes']],
            ['Sports & Physical Ed', 'SPT', ['Indoor Sports', 'Outdoor Athletic', 'Gymnastics & Fitness']],
            ['Hostel & Canteen', 'HST', ['Beds & Wardrobes', 'Kitchen & Dining', 'Linens & Bedding']],
            ['Consumables & Stationery', 'CON', ['Stationery & Paper', 'Cleaning & Sanitation', 'Lab Consumables', 'Printer Toners']],
        ];

        foreach ($cats as $idx => [$name, $code, $subs]) {
            $c = Category::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $code],
                ['name' => $name, 'sort_order' => $idx, 'is_active' => true]
            );

            foreach ($subs as $subName) {
                Subcategory::firstOrCreate(
                    ['tenant_id' => $tenantId, 'category_id' => $c->id, 'name' => $subName],
                    ['is_active' => true]
                );
            }
        }
    }

    /**
     * Ensure ~60 realistic item types (Durables and Consumables).
     */
    private function ensureItemTypes(string $tenantId): array
    {
        $categories = Category::where('tenant_id', $tenantId)->pluck('id', 'name');
        $subcategories = Subcategory::where('tenant_id', $tenantId)->get()->keyBy(fn ($s) => $s->category_id.'|'.$s->name);

        $items = [
            // Furniture (Durable)
            ['3-Seater Student Study Desk', '3ST.DSK', 'Furniture', 'Student Desks & Benches', 'DURABLE', 0],
            ['2-Seater Wooden Bench', '2ST.BCH', 'Furniture', 'Student Desks & Benches', 'DURABLE', 0],
            ['Senior High Student Chair', 'CHAIR.SR', 'Furniture', 'Chairs', 'DURABLE', 0],
            ['Junior Primary School Chair', 'CHAIR.JR', 'Furniture', 'Chairs', 'DURABLE', 0],
            ['Executive Principal Revolving Chair', 'EXEC.CHR', 'Furniture', 'Chairs', 'DURABLE', 0],
            ['Staff Room Ergonomic Chair', 'STF.CHR', 'Furniture', 'Chairs', 'DURABLE', 0],
            ['Library Reading Table (6-Seater)', 'LIB.TBL', 'Furniture', 'Tables & Storage', 'DURABLE', 0],
            ['Steel Filing Cabinet (4-Drawer)', 'STL.CAB', 'Furniture', 'Tables & Storage', 'DURABLE', 0],
            ['Teacher Classroom Podium Table', 'TCH.POD', 'Furniture', 'Classroom Podiums', 'DURABLE', 0],

            // Computers & IT (Durable)
            ['Core i7 Computer System (Lab)', 'PC.LAB', 'Computers & IT', 'Lab Systems', 'DURABLE', 0],
            ['Core i5 Administrative Desktop', 'PC.ADM', 'Computers & IT', 'Office Systems', 'DURABLE', 0],
            ['Faculty Core i5 Laptop 16GB', 'LPT.FAC', 'Computers & IT', 'Office Systems', 'DURABLE', 0],
            ['LaserJet Monochrome Multifunction Printer', 'PRN.MFP', 'Computers & IT', 'Peripherals', 'DURABLE', 0],
            ['High-Speed Flatbed Scanner', 'SCN.DOC', 'Computers & IT', 'Peripherals', 'DURABLE', 0],
            ['Heavy-Duty Digital Photocopier', 'COP.HD', 'Computers & IT', 'Peripherals', 'DURABLE', 0],
            ['24-Port Gigabit Managed Network Switch', 'NET.SW24', 'Computers & IT', 'Network Hardware', 'DURABLE', 0],
            ['Dual-Band Wireless Enterprise Access Point', 'NET.WAP', 'Computers & IT', 'Network Hardware', 'DURABLE', 0],

            // Teaching Aids (Durable)
            ['Magnetic Ceramic Whiteboard (6x4)', 'WB.6X4', 'Teaching Aids', 'Boards', 'DURABLE', 0],
            ['75-inch 4K Interactive Flat Panel Display', 'IFP.75', 'Teaching Aids', 'Boards', 'DURABLE', 0],
            ['Short Throw Laser Classroom Projector', 'PJ.SHRT', 'Teaching Aids', 'Projection', 'DURABLE', 0],
            ['Motorised Projection Screen (8x6)', 'SCR.MOT', 'Teaching Aids', 'Projection', 'DURABLE', 0],
            ['Classroom PA Speaker & Mic System', 'PA.SYS', 'Teaching Aids', 'Audio Visual', 'DURABLE', 0],
            ['Human Anatomy Demonstration Model', 'MDL.BIO', 'Teaching Aids', 'Lab Demonstration Kits', 'DURABLE', 0],

            // Electronics & Appliances (Durable)
            ['Split Air Conditioner 2.0 Ton Inverter', 'AC.2TN', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE', 0],
            ['High Capacity Pure Sine Wave Inverter 5kVA', 'INV.5K', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE', 0],
            ['Heavy Duty Classroom Ceiling Fan', 'FAN.CLG', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE', 0],
            ['55-inch Smart TV for Staff Room', 'TV.55', 'Electronics & Appliances', 'Public Address', 'DURABLE', 0],
            ['Outdoor Dome CCTV Camera 4MP IP', 'CAM.DOME', 'Electronics & Appliances', 'Security & Surveillance', 'DURABLE', 0],
            ['16-Channel Network Video Recorder (NVR)', 'NVR.16', 'Electronics & Appliances', 'Security & Surveillance', 'DURABLE', 0],

            // Science Lab Equipment (Durable)
            ['Binocular Compound Biological Microscope', 'MIC.BIO', 'Science Lab Equipment', 'Optics & Microscopes', 'DURABLE', 0],
            ['Digital Analytical Precision Balance (0.001g)', 'BAL.DIG', 'Science Lab Equipment', 'Measurement & Meters', 'DURABLE', 0],
            ['Bunsen Burner with Needle Valve', 'BUN.BRN', 'Science Lab Equipment', 'Glassware & Instruments', 'DURABLE', 0],
            ['Borosilicate Glassware Set (50-Piece)', 'GLS.SET', 'Science Lab Equipment', 'Glassware & Instruments', 'DURABLE', 0],

            // Sports & Physical Ed (Durable)
            ['Tournament Grade Table Tennis Board', 'SPT.TTB', 'Sports & Physical Ed', 'Indoor Sports', 'DURABLE', 0],
            ['Official Size Basketball Ring & Board System', 'SPT.BB', 'Sports & Physical Ed', 'Outdoor Athletic', 'DURABLE', 0],
            ['Badminton Post Set with Steel Base', 'SPT.BDM', 'Sports & Physical Ed', 'Outdoor Athletic', 'DURABLE', 0],

            // Hostel & Canteen (Durable)
            ['Hostel Steel Double Bunk Bed', 'HST.BNK', 'Hostel & Canteen', 'Beds & Wardrobes', 'DURABLE', 0],
            ['Hostel Individual Metal Locker Almirah', 'HST.ALM', 'Hostel & Canteen', 'Beds & Wardrobes', 'DURABLE', 0],
            ['Stainless Steel 6-Seater Dining Bench Set', 'CNT.DIN', 'Hostel & Canteen', 'Kitchen & Dining', 'DURABLE', 0],

            // Consumables (Stationery, Cleaning, Lab, Toners)
            ['A4 Photocopy Paper 75GSM (Ream of 500)', 'CON.A4', 'Consumables & Stationery', 'Stationery & Paper', 'CONSUMABLE', 50],
            ['Whiteboard Dry Erase Marker (Box of 12)', 'CON.MKR', 'Consumables & Stationery', 'Stationery & Paper', 'CONSUMABLE', 100],
            ['School Examination Answer Booklet (Pack 100)', 'CON.ANS', 'Consumables & Stationery', 'Stationery & Paper', 'CONSUMABLE', 80],
            ['Dustless White Chalk Sticks (Box of 100)', 'CON.CHK', 'Consumables & Stationery', 'Stationery & Paper', 'CONSUMABLE', 60],
            ['Staff & Student Daily Attendance Register', 'CON.REG', 'Consumables & Stationery', 'Stationery & Paper', 'CONSUMABLE', 30],
            ['HP LaserJet Original Toner Cartridge 88A', 'CON.TNR88', 'Consumables & Stationery', 'Printer Toners', 'CONSUMABLE', 10],
            ['Canon Black Photocopier NPG Toner', 'CON.TNRCAN', 'Consumables & Stationery', 'Printer Toners', 'CONSUMABLE', 8],
            ['Floor Disinfectant Phenyl Concentrated (5L)', 'CON.PHN5L', 'Consumables & Stationery', 'Cleaning & Sanitation', 'CONSUMABLE', 20],
            ['Liquid Anti-Bacterial Hand Wash (5L Can)', 'CON.SOAP5', 'Consumables & Stationery', 'Cleaning & Sanitation', 'CONSUMABLE', 15],
            ['Cotton Floor Cleaning Wet Mop with Handle', 'CON.MOP', 'Consumables & Stationery', 'Cleaning & Sanitation', 'CONSUMABLE', 25],
            ['Laboratory Grade Hydrochloric Acid HCl (2.5L)', 'CON.HCL', 'Consumables & Stationery', 'Lab Consumables', 'CONSUMABLE', 6],
            ['Sodium Hydroxide NaOH Pellets Analytical (500g)', 'CON.NAOH', 'Consumables & Stationery', 'Lab Consumables', 'CONSUMABLE', 8],
        ];

        $results = [];
        foreach ($items as [$name, $prefix, $catName, $subName, $lifespan, $reorder]) {
            $catId = $categories[$catName] ?? null;
            if (! $catId) {
                continue;
            }
            $subId = $subcategories->get("{$catId}|{$subName}")?->id;

            $item = ItemType::firstOrCreate(
                ['tenant_id' => $tenantId, 'code_prefix' => $prefix],
                [
                    'name' => $name,
                    'category_id' => $catId,
                    'subcategory_id' => $subId,
                    'lifespan' => Lifespan::from($lifespan),
                    'unit_of_measure' => 'PCS',
                    'reorder_level' => $reorder > 0 ? $reorder : null,
                    'is_active' => true,
                ]
            );
            $results[] = $item;
        }

        return $results;
    }

    /**
     * Chart of accounts for the tenant.
     */
    private function ensureAccounts(string $tenantId): array
    {
        (new ChartOfAccountsSeeder)->run();

        return Account::where('tenant_id', $tenantId)->get()->keyBy('code')->all();
    }

    /**
     * Seed ~25 realistic vendors.
     */
    private function ensureVendors(string $tenantId, string $prefixCode): array
    {
        $vendorList = [
            ['Nepal IT Solutions Pvt. Ltd.', '601234567', '01-4433221', 'Putalisadak, Kathmandu'],
            ['Sajha Pustak Bhandar', '300456789', '01-4221199', 'Bagbazar, Kathmandu'],
            ['Everest Educational Furniture', '609876543', '01-5544332', 'Patan Industrial Estate, Lalitpur'],
            ['Himalayan Scientific Suppliers', '605554443', '01-4788990', 'Tripureshwor, Kathmandu'],
            ['Kantipur Stationery & Printing', '602345678', '01-4267890', 'New Road, Kathmandu'],
            ['Quality Lab Equipments Nepal', '603456789', '01-5523456', 'Lagankhel, Lalitpur'],
            ['Subha Labh Electronics & Audio', '604567890', '01-4109876', 'Maha Boudha, Kathmandu'],
            ['United Sports Nepal', '605678901', '01-4254321', 'Tripureshwor, Kathmandu'],
            ['Global Tech Network Solutions', '606789012', '01-4412345', 'Durbarmarg, Kathmandu'],
            ['Premier School Furniture Udhyog', '607890123', '01-6612345', 'Suryabinayak, Bhaktapur'],
            ['Annapurna Cleaning & Sanitary', '608901234', '01-4356789', 'Kalanki, Kathmandu'],
            ['Shakti Power & Invertors', '609012345', '01-5534567', 'Pulchowk, Lalitpur'],
            ['Sagarmatha Scientific Instruments', '601122334', '01-4487654', 'Baneshwor, Kathmandu'],
            ['Laxmi Hardware & Tools Center', '602233445', '01-4498765', 'Koteshwor, Kathmandu'],
            ['Kathmandu Modern Office Systems', '603344556', '01-4234567', 'Sundhara, Kathmandu'],
            ['National Educational Media', '604455667', '01-4723456', 'Sinamangal, Kathmandu'],
            ['Patan Steel Works Pvt. Ltd.', '605566778', '01-5545678', 'Mangalbazar, Lalitpur'],
            ['Bhaktapur Ceramic & Craft Supplies', '606677889', '01-6623456', 'Byasi, Bhaktapur'],
            ['Reliable Toners & Printers Depot', '607788990', '01-4445678', 'Putalisadak, Kathmandu'],
            ['Apex Chemical Distributors', '608899001', '01-4219876', 'Teku, Kathmandu'],
        ];

        $results = [];
        foreach ($vendorList as [$name, $pan, $phone, $address]) {
            $v = Vendor::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $name],
                [
                    'pan_vat' => $pan,
                    'phone' => $phone,
                    'address' => $address,
                    'is_active' => true,
                ]
            );
            $results[] = $v;
        }

        return $results;
    }

    /**
     * Seed ~1,250 Asset Units for durable items across locations.
     */
    private function seedAssetUnits(string $tenantId, array $itemTypes, array $locations, string $prefixCode): void
    {
        $durableItems = array_filter($itemTypes, fn (ItemType $i) => $i->lifespan === Lifespan::DURABLE);
        if (empty($durableItems)) {
            return;
        }

        $existingCount = DB::table('asset_units')->where('tenant_id', $tenantId)->count();
        if ($existingCount >= 1000) {
            $this->command?->line("  Asset units already seeded ({$existingCount} units).");

            return;
        }

        $this->command?->line('  Generating ~1,250 serialized asset units...');

        $statuses = [UnitStatus::ACTIVE, UnitStatus::ACTIVE, UnitStatus::ACTIVE, UnitStatus::ACTIVE, UnitStatus::ACTIVE, UnitStatus::DAMAGED, UnitStatus::UNDER_REPAIR, UnitStatus::DISPOSED];
        $locCount = count($locations);
        $unitsToInsert = [];
        $unitCounterPerItem = [];

        // Distribute units across durable items
        foreach ($durableItems as $item) {
            // Give 30 - 60 units per item type
            $qty = in_array($item->code_prefix, ['3ST.DSK', '2ST.BCH', 'CHAIR.SR', 'CHAIR.JR']) ? 120 : (in_array($item->code_prefix, ['FAN.CLG', 'CAM.DOME', 'PC.LAB', 'HST.BNK']) ? 60 : 25);

            for ($n = 1; $n <= $qty; $n++) {
                $loc = $locations[($n + crc32($item->id)) % $locCount];
                $status = $statuses[($n + crc32($item->code_prefix)) % count($statuses)];
                $unitCode = "{$item->code_prefix}.{$n}";
                $serialNo = "SN-{$prefixCode}-{$item->code_prefix}-".str_pad((string) $n, 4, '0', STR_PAD_LEFT);
                $cost = match ($item->code_prefix) {
                    'IFP.75' => 245000.00,
                    'PJ.SHRT' => 85000.00,
                    'PC.LAB', 'PC.ADM' => 65000.00,
                    'LPT.FAC' => 78000.00,
                    'COP.HD' => 185000.00,
                    'AC.2TN' => 92000.00,
                    '3ST.DSK' => 8500.00,
                    'CHAIR.SR', 'CHAIR.JR' => 2800.00,
                    default => 12500.00,
                };

                $unitsToInsert[] = [
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenantId,
                    'item_type_id' => $item->id,
                    'location_id' => $loc->id,
                    'unit_no' => $n,
                    'unit_code' => $unitCode,
                    'serial_no' => $serialNo,
                    'status' => $status->value,
                    'acquired_on' => Carbon::now()->subDays(rand(30, 400))->toDateString(),
                    'purchase_cost' => $cost,
                    'note' => 'Asset verified during institutional audit inventory verification',
                    'created_at' => Carbon::now()->subDays(rand(30, 400)),
                ];

                if (count($unitsToInsert) >= 200) {
                    DB::table('asset_units')->insert($unitsToInsert);
                    $unitsToInsert = [];
                }
            }
        }

        if (! empty($unitsToInsert)) {
            DB::table('asset_units')->insert($unitsToInsert);
        }

        $totalUnits = DB::table('asset_units')->where('tenant_id', $tenantId)->count();
        $this->command?->line("  Asset units complete: {$totalUnits} units.");
    }

    /**
     * Seed complete Procurement Pipeline:
     * - ~200 Demands
     * - ~600 Demand Lines
     * - ~350 Approvals (strictly actor != raised_by)
     * - ~120 Purchase Orders
     * - ~300 PO Lines
     * - ~90 Goods Receipts (strictly received_by != ordered_by)
     * - ~225 GR Lines (qty_received <= qty_ordered)
     * - ~90 Bills (Entered by accounts, cleared by accounts2/MD with variance_note)
     * - ~225 Bill Lines
     * - ~60 Payments & Allocations
     * - ~150 Journal Entries & ~375 Lines (strictly balanced debits = credits)
     */
    private function seedProcurementAndFinance(
        string $tenantId,
        array $staff,
        array $vendors,
        array $itemTypes,
        array $locations,
        array $accounts,
        string $prefixCode
    ): void {
        $existingDemands = DB::table('demand_forms')->where('tenant_id', $tenantId)->count();
        if ($existingDemands >= 150) {
            $this->command?->line("  Procurement pipeline already populated ({$existingDemands} demands).");

            return;
        }

        $this->command?->line('  Generating ~200 demands, POs, receipts, bills, payments, and journals...');

        $teacherUsers = [$staff['teacher1'], $staff['teacher2'], $staff['teacher3']];
        $hod = $staff['hod_science'];
        $adminOfficer = $staff['admin_officer'];
        $md = $staff['md'];
        $chairman = $staff['chairman'];
        $purchaseOfficer = $staff['purchase_officer'];
        $receivingOfficer = $staff['receiving_officer'];
        $accountsOfficer = $staff['accounts_officer'];
        $accountsAssistant = $staff['accounts_assistant'];

        $vendorCount = count($vendors);
        $itemCount = count($itemTypes);
        $locCount = count($locations);

        $departments = [
            'Science & Technology Faculty',
            'Mathematics & Statistics Wing',
            'Computer & Robotics Department',
            'Senior Secondary Block C',
            'Junior Wing Administration',
            'Physical Education & Athletics',
            'Central Library & Archives',
            'Hostel Residence Section',
            'Examination & Records Division',
            'Campus Facilities & Maintenance',
        ];

        // Target: 200 Demand Forms
        // Distribution:
        // - 110 APPROVED (leads to POs, Receipts, Bills, Payments)
        // - 45 PENDING (various tiers: 15 at T1, 15 at T2, 10 at T3, 5 at T4)
        // - 30 REJECTED (with valid reason >= 5 chars)
        // - 15 CANCELLED

        $poCounter = 0;
        $billCounter = 0;
        $paymentCounter = 0;
        $journalCounter = 0;

        for ($d = 1; $d <= 200; $d++) {
            $demandRef = "DF-{$this->startYear}-".str_pad((string) $d, 4, '0', STR_PAD_LEFT);
            $initiator = $teacherUsers[($d - 1) % count($teacherUsers)];
            $dept = $departments[($d - 1) % count($departments)];
            $dateAgo = Carbon::now()->subDays(max(5, 220 - $d));

            // Select 2 to 4 items for this demand
            $lineCount = ($d % 3) + 2;
            $linesData = [];
            $demandTotal = 0.0;

            for ($l = 1; $l <= $lineCount; $l++) {
                $item = $itemTypes[($d * 3 + $l) % $itemCount];
                $qty = $item->lifespan === Lifespan::CONSUMABLE ? rand(5, 40) : rand(2, 15);
                $rate = match ($item->lifespan) {
                    Lifespan::CONSUMABLE => rand(300, 2500) * 1.0,
                    Lifespan::DURABLE => rand(3500, 45000) * 1.0,
                };
                $lineTotal = round($qty * $rate, 2);
                $demandTotal += $lineTotal;

                $linesData[] = [
                    'id' => (string) Str::orderedUuid(),
                    'item_type_id' => $item->id,
                    'item_name' => $item->name,
                    'quantity' => $qty,
                    'unit_rate' => $rate,
                    'line_total' => $lineTotal,
                    'specification' => "Standard institutional grade specification for {$item->name}",
                ];
            }

            // Determine final tier based on total amount
            $finalTier = 1;
            if ($demandTotal > 200000) {
                $finalTier = 4;
            } elseif ($demandTotal > 50000) {
                $finalTier = 3;
            } elseif ($demandTotal > 15000) {
                $finalTier = 2;
            }

            // Determine status for this demand
            $status = DemandStatus::APPROVED;
            $currentTier = null;
            $closedAt = $dateAgo->copy()->addDays(2);

            if ($d > 185) {
                $status = DemandStatus::CANCELLED;
                $closedAt = $dateAgo->copy()->addDay();
            } elseif ($d > 155) {
                $status = DemandStatus::REJECTED;
                $closedAt = $dateAgo->copy()->addDays(2);
            } elseif ($d > 110) {
                $status = DemandStatus::PENDING;
                $currentTier = min($finalTier, (($d % 4) + 1));
                $closedAt = null;
            }

            // Insert Demand Form
            $demandId = (string) Str::orderedUuid();
            DB::table('demand_forms')->insert([
                'id' => $demandId,
                'tenant_id' => $tenantId,
                'ref' => $demandRef,
                'fiscal_year' => $this->fy,
                'raised_by_id' => $initiator->id,
                'department' => $dept,
                'justification' => "Requisition of required academic and administrative materials for {$dept}.",
                'need_by_date' => $dateAgo->copy()->addDays(15)->toDateString(),
                'total_amount' => $demandTotal,
                'status' => $status->value,
                'current_tier' => $currentTier,
                'final_tier' => $finalTier,
                'created_at' => $dateAgo,
                'closed_at' => $closedAt,
            ]);

            // Insert Demand Lines
            $insertedDemandLines = [];
            foreach ($linesData as $ld) {
                $ld['demand_id'] = $demandId;
                $ld['tenant_id'] = $tenantId;
                DB::table('demand_lines')->insert($ld);
                $insertedDemandLines[] = $ld;
            }

            // Insert Approvals based on status and tiers
            // SEPARATION OF DUTIES: decider != initiator ($initiator->id)
            if ($status === DemandStatus::APPROVED) {
                for ($t = 1; $t <= $finalTier; $t++) {
                    $decider = match ($t) {
                        1 => $hod,
                        2 => $adminOfficer,
                        3 => $md,
                        4 => $chairman,
                    };
                    DB::table('demand_approvals')->insert([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenantId,
                        'demand_id' => $demandId,
                        'tier_no' => $t,
                        'actor_id' => $decider->id,
                        'action' => 'APPROVE',
                        'reason' => "Verified and approved at Tier {$t} for {$dept}",
                        'acted_at' => $dateAgo->copy()->addHours($t * 8),
                    ]);
                }
            } elseif ($status === DemandStatus::REJECTED) {
                // Approved up to rejected tier, then rejected with reason >= 5 chars
                $rejectTier = min($finalTier, 2);
                if ($rejectTier > 1) {
                    DB::table('demand_approvals')->insert([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenantId,
                        'demand_id' => $demandId,
                        'tier_no' => 1,
                        'actor_id' => $hod->id,
                        'action' => 'APPROVE',
                        'reason' => 'Forwarded with initial department endorsement',
                        'acted_at' => $dateAgo->copy()->addHours(6),
                    ]);
                }
                $rejector = $rejectTier === 1 ? $hod : $adminOfficer;
                DB::table('demand_approvals')->insert([
                    'id' => (string) Str::orderedUuid(),
                    'tenant_id' => $tenantId,
                    'demand_id' => $demandId,
                    'tier_no' => $rejectTier,
                    'actor_id' => $rejector->id,
                    'action' => 'REJECT',
                    'reason' => 'Exceeds current allocated budget quota for this quarterly cycle',
                    'acted_at' => $dateAgo->copy()->addHours(18),
                ]);
            } elseif ($status === DemandStatus::PENDING && $currentTier > 1) {
                // Has approved earlier tiers
                for ($t = 1; $t < $currentTier; $t++) {
                    $decider = match ($t) {
                        1 => $hod,
                        2 => $adminOfficer,
                        3 => $md,
                        default => $hod,
                    };
                    DB::table('demand_approvals')->insert([
                        'id' => (string) Str::orderedUuid(),
                        'tenant_id' => $tenantId,
                        'demand_id' => $demandId,
                        'tier_no' => $t,
                        'actor_id' => $decider->id,
                        'action' => 'APPROVE',
                        'reason' => "Verified specifications at Tier {$t}",
                        'acted_at' => $dateAgo->copy()->addHours($t * 6),
                    ]);
                }
            }

            // If APPROVED, create Purchase Order for ~90% of them
            if ($status === DemandStatus::APPROVED && $d <= 100) {
                $poCounter++;
                $poRef = "PO-{$this->startYear}-".str_pad((string) $poCounter, 4, '0', STR_PAD_LEFT);
                $vendor = $vendors[($d + 5) % $vendorCount];
                $poDate = $dateAgo->copy()->addDays(3);

                $poStatus = OrderStatus::RECEIVED;
                if ($poCounter > 85) {
                    $poStatus = OrderStatus::PLACED;
                } elseif ($poCounter > 70) {
                    $poStatus = OrderStatus::PART_RECEIVED;
                }

                $poId = (string) Str::orderedUuid();
                DB::table('purchase_orders')->insert([
                    'id' => $poId,
                    'tenant_id' => $tenantId,
                    'ref' => $poRef,
                    'fiscal_year' => $this->fy,
                    'demand_id' => $demandId,
                    'vendor_id' => $vendor->id,
                    'order_amount' => $demandTotal,
                    'expected_date' => $poDate->copy()->addDays(10)->toDateString(),
                    'note' => "Supply as per agreed institutional catalog specifications to {$dept}",
                    'status' => $poStatus->value,
                    'ordered_by_id' => $purchaseOfficer->id,
                    'ordered_at' => $poDate,
                ]);

                // PO Lines
                $insertedPoLines = [];
                foreach ($insertedDemandLines as $dl) {
                    $poLineId = (string) Str::orderedUuid();
                    DB::table('purchase_order_lines')->insert([
                        'id' => $poLineId,
                        'tenant_id' => $tenantId,
                        'purchase_order_id' => $poId,
                        'demand_line_id' => $dl['id'],
                        'item_type_id' => $dl['item_type_id'],
                        'description' => $dl['item_name'],
                        'quantity_ordered' => $dl['quantity'],
                        'unit' => 'PCS',
                        'unit_price' => $dl['unit_rate'],
                        'discount' => 0.00,
                        'tax' => round($dl['line_total'] * 0.13, 2),
                        'line_total' => $dl['line_total'],
                        'created_at' => $poDate,
                    ]);
                    $insertedPoLines[] = array_merge($dl, ['id' => $poLineId, 'demand_line_id' => $dl['id']]);
                }

                // If RECEIVED or PART_RECEIVED, create Goods Receipt
                // SEPARATION OF DUTIES:
                // - received_by_id ($receivingOfficer->id) != ordered_by_id ($purchaseOfficer->id)
                // - ordered_by_id on receipt == purchase_orders.ordered_by_id
                if (in_array($poStatus, [OrderStatus::RECEIVED, OrderStatus::PART_RECEIVED])) {
                    $grId = (string) Str::orderedUuid();
                    $grDate = $poDate->copy()->addDays(5);
                    $loc = $locations[$d % $locCount];

                    DB::table('goods_receipts')->insert([
                        'id' => $grId,
                        'tenant_id' => $tenantId,
                        'purchase_order_id' => $poId,
                        'ordered_by_id' => $purchaseOfficer->id,
                        'received_by_id' => $receivingOfficer->id,
                        'location_id' => $loc->id,
                        'condition' => ReceiptCondition::GOOD->value,
                        'discrepancy_note' => null,
                        'challan_no' => "CH-{$prefixCode}-".str_pad((string) $poCounter, 4, '0', STR_PAD_LEFT),
                        'received_at' => $grDate,
                    ]);

                    // GR Lines: qty_received <= qty_ordered
                    $insertedGrLines = [];
                    foreach ($insertedPoLines as $pol) {
                        $qtyOrdered = $pol['quantity'];
                        $qtyReceived = ($poStatus === OrderStatus::PART_RECEIVED) ? max(1, (int) ($qtyOrdered * 0.7)) : $qtyOrdered;

                        $grLineId = (string) Str::orderedUuid();
                        DB::table('goods_receipt_lines')->insert([
                            'id' => $grLineId,
                            'tenant_id' => $tenantId,
                            'receipt_id' => $grId,
                            'purchase_order_line_id' => $pol['id'],
                            'demand_line_id' => $pol['demand_line_id'],
                            'location_id' => $loc->id,
                            'qty_ordered' => $qtyOrdered,
                            'qty_received' => $qtyReceived,
                            'remark' => 'Physical inspection passed and goods verified against challan',
                        ]);

                        $insertedGrLines[] = [
                            'id' => $grLineId,
                            'po_line_id' => $pol['id'],
                            'item_type_id' => $pol['item_type_id'],
                            'qty_received' => $qtyReceived,
                            'unit_price' => $pol['unit_rate'],
                            'line_total' => round($qtyReceived * $pol['unit_rate'], 2),
                        ];
                    }

                    // For received POs, create Bills (~85 bills)
                    if ($poCounter <= 85) {
                        $billCounter++;
                        $billNo = "BILL-{$prefixCode}-{$this->startYear}-".str_pad((string) $billCounter, 4, '0', STR_PAD_LEFT);
                        $billDate = $grDate->copy()->addDays(2);
                        $billAmount = $demandTotal;
                        $vatAmount = round($billAmount * 0.13, 2);

                        // Match Status: MATCHED (70), MISMATCH (8), VARIANCE_CLEARED (7)
                        $matchStatus = MatchStatus::MATCHED;
                        $clearedBy = null;
                        $clearedAt = null;
                        $varianceNote = null;
                        $varianceAmount = 0.00;

                        if ($billCounter > 78) {
                            $matchStatus = MatchStatus::VARIANCE_CLEARED;
                            $varianceAmount = 1500.00;
                            $billAmount += $varianceAmount;
                            // SEPARATION OF DUTIES: cleared_by_id != entered_by_id
                            $clearedBy = $accountsAssistant->id;
                            $clearedAt = $billDate->copy()->addDay();
                            $varianceNote = 'Management verified and cleared freight and loading charge variance of Rs. 1500.';
                        } elseif ($billCounter > 70) {
                            $matchStatus = MatchStatus::MISMATCH;
                            $varianceAmount = 2500.00;
                            $billAmount += $varianceAmount;
                        }

                        // Payment status
                        $paymentStatus = 'UNPAID';
                        $paidAmount = 0.00;
                        if ($billCounter <= 45) {
                            $paymentStatus = 'PAID';
                            $paidAmount = $billAmount;
                        } elseif ($billCounter <= 60) {
                            $paymentStatus = 'PARTIALLY_PAID';
                            $paidAmount = round($billAmount * 0.5, 2);
                        }

                        $billId = (string) Str::orderedUuid();
                        DB::table('bills')->insert([
                            'id' => $billId,
                            'tenant_id' => $tenantId,
                            'bill_no' => $billNo,
                            'fiscal_year' => $this->fy,
                            'purchase_order_id' => $poId,
                            'vendor_id' => $vendor->id,
                            'bill_date' => $billDate,
                            'bill_amount' => $billAmount,
                            'vat_amount' => $vatAmount,
                            'approved_amount' => $demandTotal,
                            'ordered_amount' => $demandTotal,
                            'variance_amount' => $varianceAmount,
                            'match_status' => $matchStatus->value,
                            'payment_status' => $paymentStatus,
                            'paid_amount' => $paidAmount,
                            'entered_by_id' => $accountsOfficer->id,
                            'cleared_by_id' => $clearedBy,
                            'cleared_at' => $clearedAt,
                            'variance_note' => $varianceNote,
                            'entered_at' => $billDate,
                        ]);

                        // Bill Lines
                        foreach ($insertedPoLines as $pol) {
                            DB::table('bill_lines')->insert([
                                'id' => (string) Str::orderedUuid(),
                                'tenant_id' => $tenantId,
                                'bill_id' => $billId,
                                'purchase_order_line_id' => $pol['id'],
                                'item_type_id' => $pol['item_type_id'],
                                'description' => $pol['item_name'],
                                'quantity' => $pol['quantity'],
                                'unit_price' => $pol['unit_rate'],
                                'discount' => 0.00,
                                'tax' => round($pol['line_total'] * 0.13, 2),
                                'line_total' => $pol['line_total'],
                                'created_at' => $billDate,
                            ]);
                        }

                        // Payments & Allocations for PAID & PARTIALLY_PAID
                        if ($paidAmount > 0) {
                            $paymentCounter++;
                            $voucherNo = "PV-{$this->startYear}-".str_pad((string) $paymentCounter, 4, '0', STR_PAD_LEFT);
                            $payDate = $billDate->copy()->addDays(4);
                            $methods = ['BANK_TRANSFER', 'CHEQUE', 'BANK_TRANSFER'];
                            $method = $methods[$paymentCounter % count($methods)];

                            $paymentId = (string) Str::orderedUuid();
                            DB::table('payments')->insert([
                                'id' => $paymentId,
                                'tenant_id' => $tenantId,
                                'voucher_no' => $voucherNo,
                                'fiscal_year' => $this->fy,
                                'vendor_id' => $vendor->id,
                                'payment_date' => $payDate,
                                'amount' => $paidAmount,
                                'payment_method' => $method,
                                'reference_no' => "CHQ-{$prefixCode}-".(88000 + $paymentCounter),
                                'paid_by_id' => $accountsOfficer->id,
                                'notes' => "Settlement payment against bill {$billNo} for {$vendor->name}",
                                'created_at' => $payDate,
                            ]);

                            DB::table('payment_allocations')->insert([
                                'id' => (string) Str::orderedUuid(),
                                'tenant_id' => $tenantId,
                                'payment_id' => $paymentId,
                                'bill_id' => $billId,
                                'amount_allocated' => $paidAmount,
                                'created_at' => $payDate,
                            ]);

                            // Create balanced Journal Entry for Payment:
                            // Debit Accounts Payable (2000), Credit Operating Bank Account (1020)
                            if (isset($accounts['2000'], $accounts['1020'])) {
                                $journalCounter++;
                                $jvNo = "JV-{$this->startYear}-".str_pad((string) $journalCounter, 4, '0', STR_PAD_LEFT);
                                $jvId = (string) Str::orderedUuid();

                                DB::table('journal_entries')->insert([
                                    'id' => $jvId,
                                    'tenant_id' => $tenantId,
                                    'entry_no' => $jvNo,
                                    'fiscal_year' => $this->fy,
                                    'entry_date' => $payDate,
                                    'reference_type' => 'PAYMENT',
                                    'reference_id' => $paymentId,
                                    'memo' => "Payment settlement voucher {$voucherNo} to {$vendor->name}",
                                    'posted_by_id' => $accountsOfficer->id,
                                    'created_at' => $payDate,
                                ]);

                                // Debit AP
                                DB::table('journal_entry_lines')->insert([
                                    'id' => (string) Str::orderedUuid(),
                                    'tenant_id' => $tenantId,
                                    'journal_entry_id' => $jvId,
                                    'account_id' => $accounts['2000']->id,
                                    'debit' => $paidAmount,
                                    'credit' => 0.00,
                                    'description' => "Discharge of accounts payable for {$vendor->name}",
                                ]);

                                // Credit Bank
                                DB::table('journal_entry_lines')->insert([
                                    'id' => (string) Str::orderedUuid(),
                                    'tenant_id' => $tenantId,
                                    'journal_entry_id' => $jvId,
                                    'account_id' => $accounts['1020']->id,
                                    'debit' => 0.00,
                                    'credit' => $paidAmount,
                                    'description' => 'Disbursement from school operating account',
                                ]);
                            }
                        }

                        // Also create balanced Journal Entry for Bill receipt:
                        // Debit Inventory Asset (1200) or Supplies Expense (5010), Credit Accounts Payable (2000)
                        if (isset($accounts['1200'], $accounts['2000'])) {
                            $journalCounter++;
                            $jvNo = "JV-{$this->startYear}-".str_pad((string) $journalCounter, 4, '0', STR_PAD_LEFT);
                            $jvId = (string) Str::orderedUuid();

                            DB::table('journal_entries')->insert([
                                'id' => $jvId,
                                'tenant_id' => $tenantId,
                                'entry_no' => $jvNo,
                                'fiscal_year' => $this->fy,
                                'entry_date' => $billDate,
                                'reference_type' => 'BILL',
                                'reference_id' => $billId,
                                'memo' => "Invoice recognition for {$vendor->name} under {$billNo}",
                                'posted_by_id' => $accountsOfficer->id,
                                'created_at' => $billDate,
                            ]);

                            DB::table('journal_entry_lines')->insert([
                                'id' => (string) Str::orderedUuid(),
                                'tenant_id' => $tenantId,
                                'journal_entry_id' => $jvId,
                                'account_id' => $accounts['1200']->id,
                                'debit' => $billAmount,
                                'credit' => 0.00,
                                'description' => 'Inventory / materials received into stock',
                            ]);

                            DB::table('journal_entry_lines')->insert([
                                'id' => (string) Str::orderedUuid(),
                                'tenant_id' => $tenantId,
                                'journal_entry_id' => $jvId,
                                'account_id' => $accounts['2000']->id,
                                'debit' => 0.00,
                                'credit' => $billAmount,
                                'description' => "Payable liability booked for {$vendor->name}",
                            ]);
                        }
                    }
                }
            }
        }

        // Update ref_counters to match highest generated numbers
        $this->updateRefCounter($tenantId, 'DF', 200);
        $this->updateRefCounter($tenantId, 'PO', $poCounter);
        $this->updateRefCounter($tenantId, 'PV', $paymentCounter);
        $this->updateRefCounter($tenantId, 'JV', $journalCounter);

        $this->command?->line("  Procurement complete: 200 demands, {$poCounter} POs, {$billCounter} bills, {$paymentCounter} payments, {$journalCounter} JVs.");
    }

    /**
     * Seed ~130 Petty Cash Tokens (PAID, ISSUED, VOIDED).
     * Strictly:
     * - bill_sighted = 1
     * - amount <= ceiling_at_issue (15,000)
     * - bill_no unique and distinct from bills table
     */
    private function seedPettyCashTokens(string $tenantId, array $staff, string $prefixCode): void
    {
        $existing = DB::table('petty_cash_tokens')->where('tenant_id', $tenantId)->count();
        if ($existing >= 100) {
            $this->command?->line("  Petty cash tokens already seeded ({$existing} tokens).");

            return;
        }

        $this->command?->line('  Generating ~130 petty cash tokens...');

        $accountsOfficer = $staff['accounts_officer'];
        $claimants = [
            'P. Karki (Primary Head)',
            'T. Sharma (Math Dept)',
            'M. Adhikari (Science HOD)',
            'S. Lama (Store Caretaker)',
            'R. Maharjan (Admin Assistant)',
            'B. Giri (Hostel Warden)',
            'K. Shakya (Library In-charge)',
            'D. Thapa (Campus Security)',
        ];

        $purposes = [
            'Urgent photocopy paper reams for midterm examinations',
            'Emergency replacement of science laboratory water tap & Teflon tape',
            'Postage, courier stamps, and official communication envelopes',
            'Refreshments for visiting parent-teacher executive committee meeting',
            'Sanitary supplies and disinfectant spray bottles for restrooms',
            'Replacement dry erase markers and whiteboard dusters for Block B',
            'Emergency hardware tools, screwdriver set, and extension socket',
            'First aid medical refills (cotton, Dettol, bandage rolls, paracetamol)',
        ];

        $vendors = [
            'Sajha Pustak Bhandar',
            'Laxmi Hardware & Sanitary',
            'Patan Medical Hall',
            'New Road Stationery Depot',
            'Himalayan Electricals',
            'Baluwatar Fresh Bakery & Tea',
        ];

        $tokensToInsert = [];
        for ($i = 1; $i <= 130; $i++) {
            $serial = "PC-{$this->startYear}-".str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $billNo = "PETTY-{$prefixCode}-{$this->startYear}-".str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $amount = rand(450, 9500) * 1.0;
            $ceiling = 15000.00;
            $dateAgo = Carbon::now()->subDays(max(1, 180 - $i));

            $status = TokenStatus::PAID;
            $paidAt = $dateAgo->copy()->addHours(2);
            $paidById = $accountsOfficer->id;

            if ($i > 120) {
                $status = TokenStatus::ISSUED;
                $paidAt = null;
                $paidById = null;
            } elseif ($i > 110) {
                $status = TokenStatus::VOIDED;
                $paidAt = null;
                $paidById = null;
            }

            $tokensToInsert[] = [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'serial' => $serial,
                'fiscal_year' => $this->fy,
                'bill_no' => $billNo,
                'bill_date' => $dateAgo->toDateString(),
                'vendor_name' => $vendors[$i % count($vendors)],
                'amount' => $amount,
                'ceiling_at_issue' => $ceiling,
                'claimant_name' => $claimants[$i % count($claimants)],
                'purpose' => $purposes[$i % count($purposes)],
                'bill_sighted' => 1,
                'status' => $status->value,
                'issued_by_id' => $accountsOfficer->id,
                'issued_at' => $dateAgo,
                'paid_by_id' => $paidById,
                'paid_at' => $paidAt,
            ];

            if (count($tokensToInsert) >= 50) {
                DB::table('petty_cash_tokens')->insert($tokensToInsert);
                $tokensToInsert = [];
            }
        }

        if (! empty($tokensToInsert)) {
            DB::table('petty_cash_tokens')->insert($tokensToInsert);
        }

        $this->updateRefCounter($tenantId, 'PC', 130);
        $this->command?->line('  Petty cash complete: 130 tokens.');
    }

    /**
     * Seed ~250 Stock Count Entries across items and locations.
     * Append-only table.
     */
    private function seedStockCountEntries(string $tenantId, array $staff, array $itemTypes, array $locations): void
    {
        $existing = DB::table('stock_count_entries')->where('tenant_id', $tenantId)->count();
        if ($existing >= 200) {
            $this->command?->line("  Stock count entries already seeded ({$existing} entries).");

            return;
        }

        $this->command?->line('  Generating ~250 stock count ledger entries...');

        $auditor = $staff['auditor'];
        $itemCount = count($itemTypes);
        $locCount = count($locations);
        $sources = [CountSource::PHYSICAL_AUDIT, CountSource::PHYSICAL_AUDIT, CountSource::ADJUSTMENT, CountSource::OPENING_BALANCE];

        $countsToInsert = [];
        for ($k = 1; $k <= 250; $k++) {
            $item = $itemTypes[$k % $itemCount];
            $loc = $locations[($k * 2) % $locCount];
            $source = $sources[$k % count($sources)];
            $prevQty = rand(5, 50);
            $qty = ($source === CountSource::ADJUSTMENT) ? max(0, $prevQty - rand(1, 4)) : $prevQty + rand(0, 10);
            $countedAt = Carbon::now()->subDays(max(1, 150 - $k));

            $countsToInsert[] = [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'item_type_id' => $item->id,
                'location_id' => $loc->id,
                'quantity' => $qty,
                'previous_qty' => $prevQty,
                'source' => $source->value,
                'reference_id' => null,
                'note' => "Regular institutional stock count & physical ledger audit by {$auditor->full_name}",
                'counted_by_id' => $auditor->id,
                'counted_at' => $countedAt,
            ];

            if (count($countsToInsert) >= 50) {
                DB::table('stock_count_entries')->insert($countsToInsert);
                $countsToInsert = [];
            }
        }

        if (! empty($countsToInsert)) {
            DB::table('stock_count_entries')->insert($countsToInsert);
        }

        $this->command?->line('  Stock count entries complete: 250 rows.');
    }

    /**
     * Seed ~25 Supplier Returns and lines.
     */
    private function seedSupplierReturns(
        string $tenantId,
        array $staff,
        array $vendors,
        array $itemTypes,
        array $locations,
        string $prefixCode
    ): void {
        $existing = DB::table('supplier_returns')->where('tenant_id', $tenantId)->count();
        if ($existing >= 20) {
            $this->command?->line("  Supplier returns already seeded ({$existing} returns).");

            return;
        }

        $this->command?->line('  Generating ~25 supplier returns and lines...');

        $receivingOfficer = $staff['receiving_officer'];
        $vendorCount = count($vendors);
        $itemCount = count($itemTypes);
        $locCount = count($locations);

        $reasons = [
            'Defective batch received with paint chipping and misaligned frames',
            'Specification mismatch with institutional purchase order requirements',
            'Damaged during vendor freight transit to campus storage gates',
            'Reagent chemical bottle seal found leaking upon delivery inspection',
        ];

        for ($r = 1; $r <= 25; $r++) {
            $srRef = "SR-{$this->startYear}-".str_pad((string) $r, 4, '0', STR_PAD_LEFT);
            $vendor = $vendors[$r % $vendorCount];
            $item = $itemTypes[($r * 3) % $itemCount];
            $loc = $locations[$r % $locCount];
            $qty = rand(2, 6);
            $unitPrice = 4500.00;
            $lineTotal = round($qty * $unitPrice, 2);
            $dateAgo = Carbon::now()->subDays(max(2, 80 - $r));

            $srId = (string) Str::orderedUuid();
            DB::table('supplier_returns')->insert([
                'id' => $srId,
                'tenant_id' => $tenantId,
                'ref' => $srRef,
                'fiscal_year' => $this->fy,
                'vendor_id' => $vendor->id,
                'purchase_order_id' => null,
                'goods_receipt_id' => null,
                'total_amount' => $lineTotal,
                'reason' => $reasons[$r % count($reasons)],
                'status' => 'POSTED',
                'returned_by_id' => $receivingOfficer->id,
                'returned_at' => $dateAgo,
            ]);

            DB::table('supplier_return_lines')->insert([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'supplier_return_id' => $srId,
                'goods_receipt_line_id' => null,
                'purchase_order_line_id' => null,
                'item_type_id' => $item->id,
                'location_id' => $loc->id,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'reason' => 'Defective item returned for immediate replacement credit',
                'created_at' => $dateAgo,
            ]);
        }

        $this->updateRefCounter($tenantId, 'SR', 25);
        $this->command?->line('  Supplier returns complete: 25 returns.');
    }

    /**
     * Seed ~900 Audit Logs per school.
     */
    private function seedAuditLogs(string $tenantId, array $staff): void
    {
        $existing = DB::table('audit_log')->where('tenant_id', $tenantId)->count();
        if ($existing >= 800) {
            $this->command?->line("  Audit log already populated ({$existing} entries).");

            return;
        }

        $this->command?->line('  Generating ~900 audit log trail records...');

        $staffList = array_values($staff);
        $actions = [
            ['SIGN_IN', 'users', 'Signed in successfully via web console session'],
            ['RAISE_DEMAND', 'demand_forms', 'Initiated new procurement requisition'],
            ['APPROVE_DEMAND', 'demand_forms', 'Formally signed sign-off approval tier'],
            ['PLACE_ORDER', 'purchase_orders', 'Issued and transmitted purchase order to supplier'],
            ['RECEIVE_GOODS', 'goods_receipts', 'Verified physical delivery receipt and signed challan'],
            ['ENTER_BILL', 'bills', 'Entered vendor commercial tax invoice into billing register'],
            ['RECORD_PAYMENT', 'payments', 'Issued disbursement payment voucher'],
            ['ISSUE_TOKEN', 'petty_cash_tokens', 'Approved and disbursed petty cash token'],
            ['COUNT_ENTERED', 'stock_count_entries', 'Completed physical inventory count audit verification'],
        ];

        $logsToInsert = [];
        for ($a = 1; $a <= 900; $a++) {
            $user = $staffList[$a % count($staffList)];
            [$action, $entity, $detail] = $actions[$a % count($actions)];
            $at = Carbon::now()->subMinutes(rand(10, 200000));

            $logsToInsert[] = [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'actor_id' => $user->id,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => (string) Str::orderedUuid(),
                'detail' => "{$user->full_name}: {$detail}",
                'before' => null,
                'after' => null,
                'ip' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0.0.0',
                'at' => $at,
            ];

            if (count($logsToInsert) >= 100) {
                DB::table('audit_log')->insert($logsToInsert);
                $logsToInsert = [];
            }
        }

        if (! empty($logsToInsert)) {
            DB::table('audit_log')->insert($logsToInsert);
        }

        $this->command?->line('  Audit logs complete: 900 rows.');
    }

    /**
     * Seed ~100 in-app Notifications.
     */
    private function seedNotifications(string $tenantId, array $staff): void
    {
        $existing = DB::table('notifications')->where('tenant_id', $tenantId)->count();
        if ($existing >= 80) {
            $this->command?->line("  Notifications already seeded ({$existing} rows).");

            return;
        }

        $this->command?->line('  Generating ~100 user notifications...');

        $staffList = array_values($staff);
        $notifsToInsert = [];

        for ($n = 1; $n <= 100; $n++) {
            $user = $staffList[$n % count($staffList)];
            $createdAt = Carbon::now()->subHours(rand(1, 300));
            $readAt = ($n % 2 === 0) ? $createdAt->copy()->addMinutes(15) : null;

            $notifsToInsert[] = [
                'id' => (string) Str::orderedUuid(),
                'type' => 'App\\Notifications\\ProcurementActionRequired',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'tenant_id' => $tenantId,
                'data' => json_encode([
                    'title' => 'Procurement Action Required',
                    'message' => "Requisition form DF-{$this->startYear}-".str_pad((string) ($n + 5), 4, '0', STR_PAD_LEFT).' is awaiting your review and sign-off.',
                    'link' => '/demands',
                ]),
                'read_at' => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($notifsToInsert) >= 50) {
                DB::table('notifications')->insert($notifsToInsert);
                $notifsToInsert = [];
            }
        }

        if (! empty($notifsToInsert)) {
            DB::table('notifications')->insert($notifsToInsert);
        }

        $this->command?->line('  Notifications complete: 100 rows.');
    }

    /**
     * Update ref_counters so future UI generation doesn't collide with seeded numbers.
     */
    private function updateRefCounter(string $tenantId, string $prefix, int $maxNumber): void
    {
        DB::statement('
            INSERT INTO ref_counters (tenant_id, prefix, fiscal_year, last_number)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE last_number = GREATEST(last_number, VALUES(last_number))
        ', [$tenantId, $prefix, $this->fy, $maxNumber]);
    }
}

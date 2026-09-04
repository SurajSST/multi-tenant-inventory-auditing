<?php

namespace Database\Seeders;

use App\Enums\Lifespan;
use App\Models\Category;
use App\Models\ItemType;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class ItemTypeSeeder extends Seeder
{
    /**
     * name, code prefix, category, subcategory, lifespan
     *
     * The 44 durable code prefixes are taken verbatim from the school's
     * LIST_OF_INV.xlsx and are unique across the school. Individual units are
     * the prefix plus a running number: CHAIR.S.1, CHAIR.S.2, CHAIR.S.3.
     *
     * ONE CORRECTION TO THE SOURCE SHEET: LIST_OF_INV gives both "Canteen Table
     * (New)" and "Canteen Table (Old)" the code C.T.N.1. The old one is set to
     * C.T.O here so the two never collide. Confirm before going live.
     */
    private const ITEMS = [
        ['2024 · 3 Seater Table', '2024.3T', 'Furniture', 'Student Desks & Benches', 'DURABLE'],
        ['2026 · 3 Seater Table', '2026.3T', 'Furniture', 'Student Desks & Benches', 'DURABLE'],
        ['2024 · 2 Seater Table', '2024.2T', 'Furniture', 'Student Desks & Benches', 'DURABLE'],
        ['2026 · 2 Seater Table', '2026.2T', 'Furniture', 'Student Desks & Benches', 'DURABLE'],
        ['Montessori 2 Seater Table', 'MONT.2T', 'Furniture', 'Student Desks & Benches', 'DURABLE'],
        ['Old 3 Seater Furniture', 'O.3', 'Furniture', 'Student Desks & Benches', 'DURABLE'],
        ['Old 3 Seater Junior Furniture', 'O.J.3', 'Furniture', 'Student Desks & Benches', 'DURABLE'],
        ['Chair — Senior', 'CHAIR.S', 'Furniture', 'Chairs', 'DURABLE'],
        ['Chair — Junior', 'CHAIR.J', 'Furniture', 'Chairs', 'DURABLE'],
        ['Hall Chair', 'HC', 'Furniture', 'Chairs', 'DURABLE'],
        ['Office Chair', 'OC', 'Furniture', 'Chairs', 'DURABLE'],
        ['Sofa', 'SOF', 'Furniture', 'Chairs', 'DURABLE'],
        ['Office Table', 'OT', 'Furniture', 'Tables & Storage', 'DURABLE'],
        ['Library Table', 'LB', 'Furniture', 'Tables & Storage', 'DURABLE'],
        ['Round Table — Montessori', 'RT', 'Furniture', 'Tables & Storage', 'DURABLE'],
        ['Storage Montessori Drawer', 'SMB', 'Furniture', 'Tables & Storage', 'DURABLE'],
        ['Wooden Drawer', 'W.D', 'Furniture', 'Tables & Storage', 'DURABLE'],
        ['Metal Drawer', 'M.D', 'Furniture', 'Tables & Storage', 'DURABLE'],

        ['Computer — Lab (New)', 'CS', 'Computers & IT', 'Lab Systems', 'DURABLE'],
        ['Computer — Lab (Old)', 'CS.O', 'Computers & IT', 'Lab Systems', 'DURABLE'],
        ['Administration Computer', 'A.C', 'Computers & IT', 'Office Systems', 'DURABLE'],
        ['Laptop', 'LAPTOP', 'Computers & IT', 'Office Systems', 'DURABLE'],
        ['Printer', 'P', 'Computers & IT', 'Peripherals', 'DURABLE'],
        ['Scanner', 'S', 'Computers & IT', 'Peripherals', 'DURABLE'],
        ['Photocopy Machine', 'PTC', 'Computers & IT', 'Peripherals', 'DURABLE'],

        ['White Board', 'WB', 'Teaching Aids', 'Boards', 'DURABLE'],
        ['Smart Board', 'S.B', 'Teaching Aids', 'Boards', 'DURABLE'],
        ['Smart Board Stand', 'SBS', 'Teaching Aids', 'Boards', 'DURABLE'],
        ['Projector', 'PJCTR', 'Teaching Aids', 'Projection', 'DURABLE'],

        ['Television', 'TV', 'Electronics & Appliances', 'Audio Visual', 'DURABLE'],
        ['Speaker — Big', 'SPKR.B', 'Electronics & Appliances', 'Audio Visual', 'DURABLE'],
        ['Speaker — Small', 'SPKR.S', 'Electronics & Appliances', 'Audio Visual', 'DURABLE'],
        ['Telephone', 'TEL', 'Electronics & Appliances', 'Audio Visual', 'DURABLE'],
        ['Air Conditioner', 'AC', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE'],
        ['Invertor', 'INV', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE'],
        ['Ceiling Fan', 'CF', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE'],
        ['Table Fan', 'TB', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE'],
        ['Washing Machine', 'WM', 'Electronics & Appliances', 'Power & Cooling', 'DURABLE'],
        ['CCTV Camera', 'CCTV.C', 'Electronics & Appliances', 'Security', 'DURABLE'],

        ['Hostel Bunk Bed', 'HBB', 'Hostel', 'Beds', 'DURABLE'],

        ['Canteen Table (New)', 'C.T.N', 'Canteen', 'Seating', 'DURABLE'],
        ['Canteen Table (Old)', 'C.T.O', 'Canteen', 'Seating', 'DURABLE'],
        ['Canteen Chair (New)', 'C.C.N', 'Canteen', 'Seating', 'DURABLE'],
        ['Canteen Bench (Old)', 'C.B.O', 'Canteen', 'Seating', 'DURABLE'],

        // PLACEHOLDERS. The original sheet has no consumables — replace these
        // with the school's real consumable list before go-live.
        ['A4 Photocopy Paper (ream)', 'CON.A4', 'Consumables & Stationery', 'Stationery', 'CONSUMABLE'],
        ['Whiteboard Marker', 'CON.MKR', 'Consumables & Stationery', 'Stationery', 'CONSUMABLE'],
        ['Chalk (box)', 'CON.CHK', 'Consumables & Stationery', 'Stationery', 'CONSUMABLE'],
        ['Register / Exercise Book', 'CON.REG', 'Consumables & Stationery', 'Stationery', 'CONSUMABLE'],
        ['Printer Toner Cartridge', 'CON.TNR', 'Consumables & Stationery', 'Stationery', 'CONSUMABLE'],
        ['Phenyl / Floor Cleaner (litre)', 'CON.PHN', 'Consumables & Stationery', 'Cleaning & Sanitation', 'CONSUMABLE'],
        ['Hand Soap / Sanitiser', 'CON.SOP', 'Consumables & Stationery', 'Cleaning & Sanitation', 'CONSUMABLE'],
        ['Broom / Mop', 'CON.BRM', 'Consumables & Stationery', 'Cleaning & Sanitation', 'CONSUMABLE'],
        ['Chemistry Lab Reagent', 'CON.RGT', 'Consumables & Stationery', 'Lab Consumables', 'CONSUMABLE'],
        ['Biology Specimen Slide', 'CON.SLD', 'Consumables & Stationery', 'Lab Consumables', 'CONSUMABLE'],
    ];

    /** Reorder levels for the consumables, so the low-stock alert has something to say. */
    private const REORDER_LEVELS = [
        'CON.A4' => 20, 'CON.MKR' => 50, 'CON.CHK' => 30, 'CON.REG' => 40,
        'CON.TNR' => 4, 'CON.PHN' => 10, 'CON.SOP' => 15, 'CON.BRM' => 10,
        'CON.RGT' => 8, 'CON.SLD' => 20,
    ];

    public function run(): void
    {
        $categories = Category::pluck('id', 'name');
        $subcategories = Subcategory::get()->keyBy(fn ($s) => $s->category_id.'|'.$s->name);

        foreach (self::ITEMS as [$name, $prefix, $category, $subcategory, $lifespan]) {
            $categoryId = $categories[$category];

            ItemType::updateOrCreate(
                ['code_prefix' => $prefix],
                [
                    'name' => $name,
                    'category_id' => $categoryId,
                    'subcategory_id' => $subcategories->get("{$categoryId}|{$subcategory}")?->id,
                    'lifespan' => Lifespan::from($lifespan),
                    'unit_of_measure' => 'PCS',
                    'reorder_level' => self::REORDER_LEVELS[$prefix] ?? null,
                    'is_active' => true,
                ],
            );
        }
    }
}

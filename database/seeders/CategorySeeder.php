<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /** name, code, subcategories */
    private const CATEGORIES = [
        ['Furniture', 'FUR', ['Student Desks & Benches', 'Chairs', 'Tables & Storage']],
        ['Computers & IT', 'ICT', ['Lab Systems', 'Office Systems', 'Peripherals']],
        ['Teaching Aids', 'TCH', ['Boards', 'Projection']],
        ['Electronics & Appliances', 'ELE', ['Audio Visual', 'Power & Cooling', 'Security']],
        ['Hostel', 'HST', ['Beds']],
        ['Canteen', 'CAN', ['Seating']],
        ['Consumables & Stationery', 'CON', ['Stationery', 'Cleaning & Sanitation', 'Lab Consumables']],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $i => [$name, $code, $subs]) {
            $category = Category::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => $i, 'is_active' => true],
            );

            foreach ($subs as $sub) {
                Subcategory::updateOrCreate(
                    ['category_id' => $category->id, 'name' => $sub],
                    ['is_active' => true],
                );
            }
        }
    }
}

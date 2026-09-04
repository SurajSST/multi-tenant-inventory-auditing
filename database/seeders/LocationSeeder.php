<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /** Blocks A–F, as they appear in the school's LIST_OF_INV sheet. */
    public function run(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $code) {
            Location::updateOrCreate(
                ['code' => $code],
                ['name' => "Block {$code}", 'is_active' => true],
            );
        }
    }
}

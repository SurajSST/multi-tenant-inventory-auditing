<?php

use App\Support\IntegrityRules;
use Illuminate\Database\Migrations\Migration;

/**
 * The separation-of-duties rules, the append-only guarantees and the derived
 * read models. This is the part of the system that makes the controls real
 * rather than advisory — see App\Support\IntegrityRules.
 *
 * Re-apply at any time with: php artisan integrity:apply
 * Check it is all in place with: php artisan integrity:verify
 */
return new class extends Migration
{
    public function up(): void
    {
        IntegrityRules::apply();
    }

    public function down(): void
    {
        IntegrityRules::drop();
    }
};

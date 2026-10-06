<?php

use App\Support\IntegrityRules;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Existing triggers were created with variables that inherited the
        // database default collation. Rebuild them with the explicit collation
        // in IntegrityRules so they match the UUID columns on every database.
        IntegrityRules::apply();
    }

    public function down(): void
    {
        // Keep integrity controls installed during rollbacks as well.
        IntegrityRules::apply();
    }
};

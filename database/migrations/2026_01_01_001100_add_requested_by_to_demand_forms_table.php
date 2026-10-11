<?php

use App\Support\IntegrityRules;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_forms', function (Blueprint $table) {
            $table->foreignUuid('requested_by_id')
                ->nullable()
                ->after('raised_by_id')
                ->constrained('users')
                ->nullOnDelete();
        });

        // Reapply database triggers so trg_no_self_approval checks requested_by_id as well
        IntegrityRules::apply();
    }

    public function down(): void
    {
        Schema::table('demand_forms', function (Blueprint $table) {
            $table->dropForeign(['requested_by_id']);
            $table->dropColumn('requested_by_id');
        });

        IntegrityRules::apply();
    }
};

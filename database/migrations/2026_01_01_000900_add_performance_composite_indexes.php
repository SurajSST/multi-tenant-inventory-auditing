<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_forms', function (Blueprint $table) {
            $table->index(['tenant_id', 'raised_by_id', 'status'], 'idx_demands_user_status');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->index(['tenant_id', 'ordered_by_id'], 'idx_orders_orderer');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['notifiable_type', 'notifiable_id', 'tenant_id', 'read_at'], 'idx_notifications_morph_tenant');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_morph_tenant');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropIndex('idx_orders_orderer');
        });

        Schema::table('demand_forms', function (Blueprint $table) {
            $table->dropIndex('idx_demands_user_status');
        });
    }
};

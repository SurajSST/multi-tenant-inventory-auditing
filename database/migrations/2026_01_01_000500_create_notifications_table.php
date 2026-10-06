<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_id', 'tenant_id', 'read_at'], 'idx_notifications_bell');
            $table->index(['notifiable_type', 'notifiable_id', 'tenant_id', 'read_at'], 'idx_notifications_morph_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

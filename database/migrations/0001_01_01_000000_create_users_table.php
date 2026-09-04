<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WHO SOMEBODY IS — nothing here is specific to a school.
        //
        // There is no public registration. A school's Super Admin creates the
        // accounts for their own staff; the platform owner creates schools.
        // Every action in this system has to be attributable to a named person,
        // and no login is ever shared.
        //
        // What that person IS at a given school — staff code, designation,
        // approval tier, roles — lives in tenant_users. See the tenancy
        // migration.
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('full_name');
            // Globally unique: it is the login, and the login carries no school.
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('password');
            // The platform owner sits above every school. They administer, and
            // they read; they never take part in a school's workflow, because an
            // account that could approve anything anywhere would dissolve the
            // separation of duties this system exists to enforce.
            $table->boolean('is_platform_owner')->default(false);
            // Account-level kill switch. Ends every posting at once.
            $table->boolean('is_active')->default(true);
            $table->boolean('must_reset_password')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('is_platform_owner');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            // uuid, not foreignId — the users table is keyed by UUID.
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};

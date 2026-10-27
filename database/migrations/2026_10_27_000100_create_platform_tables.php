<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-wide tables, shared by every shop (db-schema 2.1). spatie/permission tables: task 4.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            // NULL = unlimited.
            $table->unsignedSmallInteger('max_branches')->nullable();
            $table->unsignedSmallInteger('max_staff')->nullable();
            $table->unsignedInteger('max_appointments_per_month')->nullable();
            $table->json('features')->nullable();
            TenantSchema::datetimes($table);
        });

        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email', 191)->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable(); // encrypted cast
            $table->dateTime('two_factor_confirmed_at')->nullable();
            $table->enum('role', ['super_admin', 'support']);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            TenantSchema::datetimes($table);
        });

        Schema::create('platform_admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->index();
            $table->enum('role', ['super_admin', 'support']);
            $table->char('token_hash', 64)->unique(); // sha256 of a 32-byte token
            $table->foreignId('invited_by_admin_id')->constrained('platform_admins');
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            TenantSchema::datetimes($table);
        });

        Schema::create('species', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            TenantSchema::datetimes($table);
        });

        Schema::create('breeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('species_id')->constrained('species');
            $table->string('name');
            $table->enum('default_size', ['xs', 's', 'm', 'l', 'xl'])->nullable();
            TenantSchema::datetimes($table);

            $table->unique(['species_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breeds');
        Schema::dropIfExists('species');
        Schema::dropIfExists('platform_admin_invitations');
        Schema::dropIfExists('platform_admins');
        Schema::dropIfExists('plans');
    }
};

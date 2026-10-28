<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customers (pet owners) and pets (db-schema 2.3). The phone number is the customer key,
 * shared by every branch of the shop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable(); // E.164; NULL only for the per-branch stray profile
            $table->char('phone_suffix', 4)->storedAs('RIGHT(phone, 4)'); // "last 4 digits" search
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_system')->default(false); // "Thú hoang / vô chủ"
            $table->unsignedBigInteger('system_branch_id')->nullable();
            $table->text('notes')->nullable();
            TenantSchema::datetimes($table);
            $table->dateTime('deleted_at')->nullable();

            TenantSchema::key($table);
            TenantSchema::unique($table, 'phone');
            TenantSchema::unique($table, 'system_branch_id');
            TenantSchema::index($table, 'name');
            TenantSchema::index($table, 'phone_suffix');
            TenantSchema::foreign($table, 'system_branch_id', 'branches');
        });

        DB::statement('ALTER TABLE customers ADD CONSTRAINT customers_phone_or_system_check
            CHECK (phone IS NOT NULL OR is_system = 1)');

        Schema::create('pets', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->foreignId('species_id')->constrained('species');
            $table->foreignId('breed_id')->nullable()->constrained('breeds');
            $table->string('name');
            $table->enum('sex', ['male', 'female', 'unknown'])->default('unknown');
            $table->date('birth_date')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl']);
            $table->boolean('is_neutered')->default(false);
            // Safety alerts: visible to every role that can see the pet.
            $table->text('allergies')->nullable();
            $table->text('chronic_conditions')->nullable();
            $table->string('behavior_notes')->nullable();
            $table->boolean('is_stray')->default(false);
            TenantSchema::datetimes($table);
            $table->dateTime('deleted_at')->nullable();

            TenantSchema::key($table);
            // Target of (tenant_id, customer_id, pet_id) FKs: a pet always belongs to *that* customer.
            // Also serves the (tenant_id, customer_id) lookup.
            TenantSchema::unique($table, ['customer_id', 'id']);
            TenantSchema::index($table, 'name');
            TenantSchema::foreign($table, 'customer_id', 'customers');
        });

        Schema::create('customer_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('phone', 20);
            $table->char('code_hash', 64);
            $table->enum('purpose', ['login', 'booking']);
            $table->dateTime('expires_at');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('consumed_at')->nullable();
            $table->string('ip', 45)->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, ['phone', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_otps');
        Schema::dropIfExists('pets');
        Schema::dropIfExists('customers');
    }
};

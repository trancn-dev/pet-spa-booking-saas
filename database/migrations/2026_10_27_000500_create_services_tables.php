<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Services, grooming combos, per-pet price/duration rules, per-branch availability (db-schema 2.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name');
            $table->enum('service_group', ['medical', 'grooming']);
            $table->boolean('is_combo')->default(false);
            $table->unsignedSmallInteger('duration_min');
            $table->unsignedSmallInteger('buffer_min')->default(0);
            $table->decimal('price', 12, 2);
            $table->boolean('is_public')->default(true); // shown on the shop Site
            $table->boolean('is_active')->default(true);
            TenantSchema::datetimes($table);
            $table->dateTime('deleted_at')->nullable();

            TenantSchema::key($table);
            TenantSchema::unique($table, 'code');
        });

        // Grooming combos only; combo and items must be `grooming`, no nesting (checked in the Action).
        Schema::create('service_combo_items', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('combo_service_id');
            $table->unsignedBigInteger('item_service_id');
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->primary(['tenant_id', 'combo_service_id', 'item_service_id']);
            TenantSchema::foreign($table, 'combo_service_id', 'services')->cascadeOnDelete();
            TenantSchema::foreign($table, 'item_service_id', 'services');
        });

        // Most specific wins: breed → species + size → species → service default.
        Schema::create('service_price_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('service_id');
            $table->foreignId('species_id')->nullable()->constrained('species');
            $table->foreignId('breed_id')->nullable()->constrained('breeds');
            $table->enum('size', ['xs', 's', 'm', 'l', 'xl'])->nullable();
            $table->unsignedSmallInteger('duration_min')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, ['service_id', 'species_id', 'breed_id', 'size']);
            TenantSchema::foreign($table, 'service_id', 'services')->cascadeOnDelete();
        });

        // No row for a service = available at every branch.
        Schema::create('branch_services', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('service_id');

            $table->primary(['tenant_id', 'branch_id', 'service_id']);
            TenantSchema::foreign($table, 'branch_id', 'branches')->cascadeOnDelete();
            TenantSchema::foreign($table, 'service_id', 'services')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_services');
        Schema::dropIfExists('service_price_rules');
        Schema::dropIfExists('service_combo_items');
        Schema::dropIfExists('services');
    }
};

<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atomic sequence counters (db-schema 2.10): never MAX()+1.
 *   INSERT … ON DUPLICATE KEY UPDATE `last_value` = LAST_INSERT_ID(`last_value` + 1)
 *
 * `last_value` is a reserved word in MySQL 8 (window function): always backtick it in raw SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_daily_counters', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->date('date'); // shop-local
            $table->enum('name', ['queue', 'invoice', 'return', 'receipt', 'adjustment', 'transfer']);
            $table->unsignedInteger('last_value')->default(0);

            $table->primary(['tenant_id', 'branch_id', 'date', 'name']);
            TenantSchema::foreign($table, 'branch_id', 'branches')->cascadeOnDelete();
        });

        Schema::create('tenant_counters', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30); // medical_record → HS-000123
            $table->unsignedInteger('last_value')->default(0);

            $table->primary(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_counters');
        Schema::dropIfExists('branch_daily_counters');
    }
};

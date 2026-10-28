<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cash drawers / sessions and invoice headers (db-schema 2.7). Created before visits,
 * which point at invoices. Lines and payments: migration 000900.
 *
 * MySQL forbids CASCADE / SET NULL on base columns of stored generated columns and on columns
 * used by CHECK constraints, so those FKs keep the default (RESTRICT).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_drawers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->string('name', 50);
            $table->boolean('is_active')->default(true);
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, ['branch_id', 'name']);
            TenantSchema::foreign($table, 'branch_id', 'branches');
        });

        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('drawer_id');
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->unsignedBigInteger('opened_by');
            $table->dateTime('opened_at');
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->decimal('expected_cash', 12, 2)->nullable();
            $table->decimal('counted_cash', 12, 2)->nullable();
            $table->decimal('difference', 12, 2)->nullable();
            $table->text('difference_reason')->nullable();
            // = drawer_id while open, NULL once closed: at most one open session per drawer.
            $table->unsignedBigInteger('open_key')->storedAs("IF(status = 'open', drawer_id, NULL)")->unique();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, ['branch_id', 'opened_at']);
            TenantSchema::foreign($table, 'branch_id', 'branches');
            TenantSchema::foreign($table, 'drawer_id', 'cash_drawers');
            TenantSchema::foreign($table, 'opened_by', 'staff');
            TenantSchema::foreign($table, 'closed_by', 'staff');
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->string('code', 30)->nullable(); // {branch.code}-{yyyymmdd}-{seq}, issued on completion
            $table->enum('kind', ['service', 'retail']);
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('payer_name')->nullable(); // strays: who pays
            $table->string('payer_phone', 20)->nullable();
            $table->enum('status', ['draft', 'completed', 'void'])->default('draft');
            $table->date('business_date'); // shop-local day the invoice was opened
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0); // always 0 in V1
            // One open service draft per customer, branch and day. Retail drafts and stray invoices
            // with a payer are left out (NULL), so they never collide.
            $table->string('open_draft_key', 64)->storedAs(
                "IF(status = 'draft' AND kind = 'service' AND payer_name IS NULL, CONCAT(customer_id, ':', branch_id, ':', business_date), NULL)"
            );
            $table->dateTime('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            $table->unsignedBigInteger('replaces_invoice_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, 'code');
            TenantSchema::unique($table, 'open_draft_key');
            TenantSchema::index($table, ['branch_id', 'status', 'business_date']);
            TenantSchema::index($table, ['branch_id', 'completed_at']);
            TenantSchema::foreign($table, 'branch_id', 'branches');
            TenantSchema::foreign($table, 'customer_id', 'customers');
            TenantSchema::foreign($table, 'completed_by', 'staff');
            TenantSchema::foreign($table, 'voided_by', 'staff');
            TenantSchema::foreign($table, 'created_by', 'staff');
            TenantSchema::foreign($table, 'replaces_invoice_id', 'invoices');
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_service_has_customer_check
            CHECK (kind <> 'service' OR customer_id IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_drawers');
    }
};

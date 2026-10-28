<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice lines and payments (db-schema 2.7). Payments are append-only and never change invoice_id;
 * amending a completed invoice moves money with transfer_out / transfer_in entries (ADR 0010).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_id');
            $table->enum('line_type', ['visit_item', 'product', 'custom']);
            $table->unsignedBigInteger('visit_item_id')->nullable(); // one-way sync while draft; read-only at the till
            $table->unsignedBigInteger('pet_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable(); // GĐ2
            $table->unsignedBigInteger('product_unit_id')->nullable(); // GĐ2
            $table->decimal('unit_factor', 12, 3)->default(1);
            $table->string('name'); // copied
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->decimal('unit_cost', 12, 2)->nullable(); // GĐ2, owner-only
            $table->unsignedBigInteger('created_by')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, 'invoice_id');
            TenantSchema::index($table, 'visit_item_id');
            TenantSchema::foreign($table, 'invoice_id', 'invoices');
            TenantSchema::foreign($table, 'visit_item_id', 'visit_items');
            TenantSchema::foreign($table, 'pet_id', 'pets');
            TenantSchema::foreign($table, 'created_by', 'staff');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('sales_return_id')->nullable(); // GĐ2, FK in phase 11
            $table->enum('kind', ['payment', 'refund', 'transfer_out', 'transfer_in']);
            $table->enum('method', ['cash', 'bank_transfer', 'qr', 'card']);
            $table->decimal('amount', 12, 2);
            $table->string('reference')->nullable(); // bank / QR transaction id
            $table->unsignedBigInteger('cash_session_id')->nullable();
            $table->unsignedBigInteger('transfer_payment_id')->nullable(); // the original entry being moved
            $table->unsignedBigInteger('received_by_staff_id');
            $table->dateTime('paid_at');
            $table->char('idempotency_key', 36);
            $table->dateTime('created_at'); // append-only

            TenantSchema::key($table);
            TenantSchema::unique($table, 'idempotency_key');
            TenantSchema::index($table, 'invoice_id');
            TenantSchema::index($table, 'cash_session_id');
            TenantSchema::foreign($table, 'invoice_id', 'invoices');
            TenantSchema::foreign($table, 'cash_session_id', 'cash_sessions');
            TenantSchema::foreign($table, 'transfer_payment_id', 'payments');
            TenantSchema::foreign($table, 'received_by_staff_id', 'staff');
        });

        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive_check CHECK (amount > 0)');
        // Cash taken or refunded needs an open till session; transfer entries never touch a session.
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_cash_session_check
            CHECK (kind NOT IN ('payment', 'refund') OR method <> 'cash' OR cash_session_id IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_lines');
    }
};

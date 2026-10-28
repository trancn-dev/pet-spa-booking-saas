<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appointments: one header per customer, one item per pet + service (db-schema 2.5).
 * Check-in links items to a Visit through appointment_items.visit_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('customer_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at'); // latest end of its items
            $table->enum('source', ['online', 'staff']);
            $table->enum('status', ['confirmed', 'checked_in', 'cancelled', 'no_show'])->default('confirmed');
            $table->unsignedBigInteger('preferred_staff_id')->nullable();
            $table->text('customer_note')->nullable();
            $table->boolean('send_reminders')->default(true);
            $table->char('idempotency_key', 36)->nullable();
            $table->unsignedBigInteger('created_by_staff_id')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->enum('cancelled_by_type', ['customer', 'staff', 'system'])->nullable();
            $table->text('cancel_reason')->nullable();
            $table->dateTime('no_show_at')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, 'idempotency_key');
            // Target of the items' composite FK: items cannot drift from the header's customer / branch.
            TenantSchema::unique($table, ['id', 'customer_id', 'branch_id']);
            TenantSchema::index($table, ['branch_id', 'starts_at']);
            TenantSchema::index($table, ['customer_id', 'starts_at']);
            TenantSchema::foreign($table, 'branch_id', 'branches');
            TenantSchema::foreign($table, 'customer_id', 'customers');
            TenantSchema::foreign($table, 'preferred_staff_id', 'staff');
            TenantSchema::foreign($table, 'created_by_staff_id', 'staff');
        });

        Schema::create('appointment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('branch_id'); // copied from the appointment
            $table->unsignedBigInteger('customer_id'); // copied from the appointment
            $table->unsignedBigInteger('pet_id');
            $table->unsignedBigInteger('service_id');
            $table->enum('service_group', ['medical', 'grooming']); // copied from the service
            $table->dateTime('starts_at');
            $table->dateTime('ends_at'); // buffer included
            $table->unsignedSmallInteger('duration_min'); // snapshot at booking time
            $table->decimal('reference_price', 12, 2); // display only; billing uses the Visit price
            $table->enum('status', ['booked', 'checked_in', 'cancelled', 'no_show'])->default('booked');
            $table->unsignedBigInteger('assigned_staff_id')->nullable();
            $table->unsignedBigInteger('visit_id')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, ['appointment_id', 'pet_id', 'service_id']);
            TenantSchema::index($table, ['branch_id', 'service_group', 'starts_at', 'ends_at', 'status'], 'appointment_items_capacity_index');
            TenantSchema::index($table, ['pet_id', 'starts_at']);
            TenantSchema::index($table, 'visit_id');
            TenantSchema::foreign($table, ['appointment_id', 'customer_id', 'branch_id'], 'appointments', ['id', 'customer_id', 'branch_id']);
            TenantSchema::foreign($table, ['customer_id', 'pet_id'], 'pets', ['customer_id', 'id']);
            TenantSchema::foreign($table, 'service_id', 'services');
            TenantSchema::foreign($table, 'assigned_staff_id', 'staff');
            TenantSchema::foreign($table, 'visit_id', 'visits');
        });

        // Append-only history (reschedule, cancel, check-in…).
        Schema::create('appointment_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('appointment_id');
            $table->unsignedBigInteger('appointment_item_id')->nullable(); // NULL = whole appointment
            $table->enum('action', ['rescheduled', 'cancelled', 'restored', 'checked_in', 'no_show', 'reassigned', 'item_added', 'item_removed']);
            $table->dateTime('old_starts_at')->nullable();
            $table->dateTime('new_starts_at')->nullable();
            $table->enum('actor_type', ['customer', 'staff', 'system']);
            $table->unsignedBigInteger('actor_staff_id')->nullable();
            $table->text('reason')->nullable();
            $table->dateTime('created_at');

            TenantSchema::key($table);
            TenantSchema::index($table, 'appointment_id');
            TenantSchema::foreign($table, 'appointment_id', 'appointments');
            TenantSchema::foreign($table, 'appointment_item_id', 'appointment_items');
            TenantSchema::foreign($table, 'actor_staff_id', 'staff');
        });

        // Branch × group ratio; rows with weekday / time override the default, most specific wins.
        // No row = that group is not bookable online.
        Schema::create('online_booking_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->enum('service_group', ['medical', 'grooming']);
            $table->decimal('ratio', 4, 3);
            $table->unsignedTinyInteger('weekday')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, ['branch_id', 'service_group']);
            TenantSchema::foreign($table, 'branch_id', 'branches')->cascadeOnDelete();
        });

        // A row to lock (FOR UPDATE) while booking; no business data (db-schema 5.1, ADR 0002).
        // Rows are inserted with INSERT IGNORE *outside* the booking transaction.
        Schema::create('booking_capacity_locks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->enum('service_group', ['medical', 'grooming']);
            $table->date('date');

            $table->primary(['tenant_id', 'branch_id', 'service_group', 'date']);
            TenantSchema::foreign($table, 'branch_id', 'branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_capacity_locks');
        Schema::dropIfExists('online_booking_rates');
        Schema::dropIfExists('appointment_changes');
        Schema::dropIfExists('appointment_items');
        Schema::dropIfExists('appointments');
    }
};

<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visits (one exam / grooming of one pet), queue, medical records, vaccinations (db-schema 2.6).
 * product_id / product_unit_id / batch_id are GĐ2 columns: created now, FKs added in phase 11.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            // NULL only for an emergency whose owner / pet profile is still being filled in.
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('pet_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->enum('service_group', ['medical', 'grooming']);
            $table->date('visit_date'); // shop-local queue day
            $table->unsignedSmallInteger('queue_no'); // printed on the ticket, never changes
            $table->enum('status', ['waiting', 'in_progress', 'done', 'cancelled'])->default('waiting');
            $table->boolean('is_emergency')->default(false);
            $table->string('emergency_label', 100)->nullable();
            $table->dateTime('priority_at'); // queue order: is_emergency DESC, priority_at
            $table->unsignedBigInteger('assigned_staff_id')->nullable();
            $table->text('reason')->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->dateTime('checked_in_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, ['branch_id', 'visit_date', 'queue_no']);
            TenantSchema::index($table, ['branch_id', 'visit_date', 'status', 'service_group']);
            TenantSchema::index($table, 'invoice_id');
            TenantSchema::index($table, ['pet_id', 'visit_date']);
            TenantSchema::foreign($table, 'branch_id', 'branches');
            TenantSchema::foreign($table, 'customer_id', 'customers');
            TenantSchema::foreign($table, ['customer_id', 'pet_id'], 'pets', ['customer_id', 'id']);
            TenantSchema::foreign($table, 'invoice_id', 'invoices');
            TenantSchema::foreign($table, 'assigned_staff_id', 'staff');
            TenantSchema::foreign($table, 'created_by', 'staff');
        });

        DB::statement('ALTER TABLE visits ADD CONSTRAINT visits_pet_or_emergency_check
            CHECK (is_emergency = 1 OR (customer_id IS NOT NULL AND pet_id IS NOT NULL))');

        Schema::create('visit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('visit_id');
            $table->enum('item_type', ['service', 'medication', 'consumable']);
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable(); // GĐ2
            $table->string('name'); // copied when added
            $table->decimal('quantity', 12, 3)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0); // price at the time it was added
            $table->boolean('is_billable')->default(true); // consumables inside a service: 0
            $table->decimal('suggested_quantity', 12, 3)->nullable(); // GĐ2
            $table->unsignedBigInteger('product_unit_id')->nullable(); // GĐ2
            $table->decimal('unit_factor', 12, 3)->default(1);
            $table->unsignedBigInteger('batch_id')->nullable(); // GĐ2, manual batch pick
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->text('notes')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, 'visit_id');
            TenantSchema::foreign($table, 'visit_id', 'visits');
            TenantSchema::foreign($table, 'service_id', 'services');
            TenantSchema::foreign($table, 'performed_by', 'staff');
        });

        DB::statement("ALTER TABLE visit_items ADD CONSTRAINT visit_items_item_type_check
            CHECK ((item_type = 'service' AND service_id IS NOT NULL)
                OR (item_type IN ('medication', 'consumable') AND product_id IS NOT NULL))");

        // One record per pet, shared by every branch; number issued at the first exam.
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pet_id');
            $table->string('record_no', 20); // HS-000123 (tenant_counters)
            $table->dateTime('opened_at');
            $table->unsignedBigInteger('opened_by')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, 'pet_id');
            TenantSchema::unique($table, 'record_no');
            TenantSchema::foreign($table, 'pet_id', 'pets');
            TenantSchema::foreign($table, 'opened_by', 'staff');
        });

        // One row per exam. Only the author edits it, within 24 h; afterwards only addenda.
        Schema::create('medical_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('medical_record_id');
            $table->unsignedBigInteger('visit_id')->nullable(); // NULL = history from another clinic
            $table->unsignedBigInteger('author_staff_id');
            $table->text('symptoms')->nullable();
            $table->json('vitals')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('treatment')->nullable();
            $table->text('care_instructions')->nullable(); // customer-visible
            $table->text('internal_notes')->nullable(); // never shown on the Site
            $table->date('follow_up_on')->nullable();
            $table->text('referral_note')->nullable();
            $table->dateTime('recorded_at');
            $table->dateTime('editable_until'); // recorded_at + 24 h
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, ['medical_record_id', 'recorded_at']);
            TenantSchema::unique($table, 'visit_id');
            TenantSchema::foreign($table, 'medical_record_id', 'medical_records');
            TenantSchema::foreign($table, 'visit_id', 'visits');
            TenantSchema::foreign($table, 'author_staff_id', 'staff');
        });

        Schema::create('medical_entry_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('medical_entry_id');
            $table->unsignedBigInteger('author_staff_id');
            $table->text('body');
            $table->dateTime('created_at'); // append-only

            TenantSchema::key($table);
            TenantSchema::foreign($table, 'medical_entry_id', 'medical_entries');
            TenantSchema::foreign($table, 'author_staff_id', 'staff');
        });

        // Polymorphic, so no FK: the Action checks the target belongs to the same tenant.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('attachable_type', ['medical_entry', 'visit']);
            $table->unsignedBigInteger('attachable_id');
            $table->enum('kind', ['lab_result', 'imaging', 'intake_photo', 'return_photo', 'other']);
            $table->boolean('is_shared_with_customer')->default(false);
            $table->string('disk', 32);
            $table->string('path'); // private, contains tenant_id
            $table->string('mime', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->string('original_name');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->dateTime('created_at');

            TenantSchema::key($table);
            TenantSchema::index($table, ['attachable_type', 'attachable_id']);
            TenantSchema::foreign($table, 'uploaded_by', 'staff');
        });

        Schema::create('vaccines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('kind', ['vaccine', 'dewormer']);
            $table->foreignId('species_id')->constrained('species');
            $table->string('manufacturer')->nullable();
            $table->unsignedBigInteger('product_id')->nullable(); // GĐ2 stock link
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, 'species_id');
        });

        Schema::create('care_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('species_id')->constrained('species');
            $table->unsignedBigInteger('vaccine_id');
            $table->boolean('is_active')->default(true);
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::foreign($table, 'vaccine_id', 'vaccines');
        });

        Schema::create('care_protocol_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('protocol_id');
            $table->unsignedTinyInteger('step_no');
            $table->unsignedSmallInteger('min_age_days')->nullable();
            $table->unsignedSmallInteger('offset_days')->default(0); // after the previous step
            $table->boolean('is_recurring')->default(false);
            $table->unsignedSmallInteger('repeat_every_days')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, ['protocol_id', 'step_no']);
            TenantSchema::foreign($table, 'protocol_id', 'care_protocols')->cascadeOnDelete();
        });

        // Vaccination book. Postponing = a `postponed` row with the new date in next_due_on.
        Schema::create('pet_vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('pet_id');
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('vaccine_id');
            $table->unsignedBigInteger('protocol_id')->nullable();
            $table->unsignedTinyInteger('step_no')->nullable();
            $table->date('administered_on')->nullable(); // NULL when postponed
            $table->unsignedBigInteger('administered_by')->nullable();
            $table->string('batch_no', 50)->nullable();
            $table->date('batch_expires_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->dateTime('superseded_at')->nullable();
            $table->enum('status', ['given', 'postponed'])->default('given');
            $table->string('postpone_reason')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, ['pet_id', 'vaccine_id']);
            TenantSchema::index($table, ['superseded_at', 'next_due_on']);
            TenantSchema::foreign($table, 'pet_id', 'pets');
            TenantSchema::foreign($table, 'visit_id', 'visits');
            TenantSchema::foreign($table, 'vaccine_id', 'vaccines');
            TenantSchema::foreign($table, 'protocol_id', 'care_protocols');
            TenantSchema::foreign($table, 'administered_by', 'staff');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pet_vaccinations');
        Schema::dropIfExists('care_protocol_steps');
        Schema::dropIfExists('care_protocols');
        Schema::dropIfExists('vaccines');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('medical_entry_addenda');
        Schema::dropIfExists('medical_entries');
        Schema::dropIfExists('medical_records');
        Schema::dropIfExists('visit_items');
        Schema::dropIfExists('visits');
    }
};

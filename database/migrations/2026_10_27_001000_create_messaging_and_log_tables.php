<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reminders, message deliveries, CMS notifications, activity log, plan usage (db-schema 2.8).
 */
return new class extends Migration
{
    public function up(): void
    {
        // What to remind and when (ADR 0007). Polymorphic: no FK on remindable_id.
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->enum('remindable_type', ['appointment', 'pet_vaccination', 'medical_entry', 'visit']);
            $table->unsignedBigInteger('remindable_id');
            $table->unsignedBigInteger('customer_id');
            $table->enum('purpose', ['appointment_confirm', 'appointment_24h', 'appointment_2h', 'vaccine_due', 'follow_up', 'pickup_ready']);
            $table->dateTime('send_at');
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'skipped'])->default('pending');
            $table->dateTime('sent_at')->nullable();
            $table->string('dedupe_key', 191);
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, 'dedupe_key');
            $table->index(['status', 'send_at']); // cross-tenant dispatcher scan (whitelisted)
            TenantSchema::foreign($table, 'customer_id', 'customers');
        });

        // One row per real send attempt: Zalo ZNS first, SMS fallback, never both. OTPs go by SMS.
        Schema::create('message_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reminder_id')->nullable();
            $table->unsignedBigInteger('customer_otp_id')->nullable();
            $table->enum('channel', ['zalo', 'sms']);
            $table->string('to_phone', 20);
            $table->string('template_code', 64);
            $table->enum('status', ['queued', 'sent', 'failed'])->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->dateTime('attempted_at')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, 'reminder_id');
            TenantSchema::foreign($table, 'reminder_id', 'reminders');
            TenantSchema::foreign($table, 'customer_otp_id', 'customer_otps');
        });

        // Laravel database notifications + tenant_id; notifiable is `staff`, never `users`,
        // so someone working at two shops never sees the other shop's notifications.
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->dateTime('read_at')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::index($table, ['notifiable_id', 'read_at']);
        });

        // Append-only. Polymorphic subject: no FK on subject_id.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('actor_staff_id')->nullable();
            $table->foreignId('impersonation_session_id')->nullable()->constrained('impersonation_sessions');
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at');

            TenantSchema::key($table);
            TenantSchema::index($table, ['subject_type', 'subject_id']);
            TenantSchema::index($table, 'created_at');
            TenantSchema::foreign($table, 'branch_id', 'branches');
            TenantSchema::foreign($table, 'actor_staff_id', 'staff');
        });

        // Plan quota: every appointment (online + staff) by month created, shop time.
        // Cancelling does not give the slot back. Incremented atomically as the last booking lock.
        Schema::create('tenant_usage', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7); // YYYY-MM
            $table->unsignedInteger('appointments_count')->default(0);

            $table->primary(['tenant_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('message_deliveries');
        Schema::dropIfExists('reminders');
    }
};

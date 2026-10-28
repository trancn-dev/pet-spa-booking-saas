<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shops and the platform's data *about* shops (db-schema 2.1).
 * tenant_subscriptions / impersonation_sessions / platform_audit_logs are not tenant-scoped:
 * they are only read from the /admin area.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->string('name');
            $table->string('slug', 63)->unique();
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh'); // IANA; every branch shares it
            $table->string('locale', 10)->default('vi');
            $table->char('currency', 3)->default('VND');
            $table->foreignId('plan_id')->constrained('plans');
            $table->enum('status', ['trial', 'active', 'past_due', 'suspended', 'cancelled'])->default('trial');
            $table->dateTime('plan_expires_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->string('suspended_reason')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            TenantSchema::datetimes($table);

            $table->index(['status', 'plan_expires_at']);
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->foreignId('tenant_id')->primary()->constrained('tenants')->cascadeOnDelete();
            $table->unsignedSmallInteger('booking_cancel_cutoff_hours')->default(24);
            $table->unsignedSmallInteger('booking_reschedule_cutoff_hours')->default(24);
            $table->unsignedSmallInteger('return_window_days')->default(7);
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('customer_session_days')->default(30);
            $table->unsignedSmallInteger('walk_in_priority_minutes')->default(15);
            $table->enum('payroll_period_kind', ['week', 'month'])->default('month'); // GĐ3
            $table->unsignedSmallInteger('expiry_alert_days')->default(30); // GĐ2
            $table->string('logo_path')->nullable();
            $table->string('site_cover_path')->nullable();
            $table->text('site_intro')->nullable();
            $table->boolean('site_show_products')->default(false); // GĐ2
            // `encrypted:array` stores ciphertext, which is not valid JSON: TEXT instead of JSON.
            $table->text('zalo_config')->nullable();
            $table->text('sms_config')->nullable();
            $table->mediumText('terms_content')->nullable();
            $table->dateTime('terms_updated_at')->nullable();
            TenantSchema::datetimes($table);
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('plans');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('amount', 12, 2);
            $table->dateTime('paid_at')->nullable();
            $table->string('payment_ref')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            TenantSchema::datetimes($table);

            $table->index(['tenant_id', 'ends_at']);
        });

        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->constrained('platform_admins');
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users');
            $table->string('reason');
            $table->char('token_hash', 64)->unique(); // one-time, 60 s
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->dateTime('expires_at'); // session max 30 min
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            TenantSchema::datetimes($table);
        });

        Schema::create('platform_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->foreignId('impersonation_session_id')->nullable()->constrained('impersonation_sessions')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('created_at'); // append-only

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_logs');
        Schema::dropIfExists('impersonation_sessions');
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('tenant_settings');
        Schema::dropIfExists('tenants');
    }
};

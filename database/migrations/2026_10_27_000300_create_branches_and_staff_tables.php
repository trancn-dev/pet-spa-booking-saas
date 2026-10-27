<?php

declare(strict_types=1);

use App\Database\TenantSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branches, staff, rosters (db-schema 2.2).
 * Weekdays are ISO-8601: 1 = Monday … 7 = Sunday. TIME columns are shop-local wall-clock times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20); // prefix of every document number
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->json('opening_hours')->nullable(); // per ISO weekday
            $table->json('allowed_ips')->nullable(); // timekeeping, GĐ3
            $table->boolean('is_active')->default(true);
            TenantSchema::datetimes($table);
            $table->dateTime('deleted_at')->nullable();

            TenantSchema::key($table);
            TenantSchema::unique($table, 'code');
        });

        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            // NULL = a person without an account (guard, cleaner). Permissions come from roles, not title.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('primary_branch_id');
            $table->string('display_name');
            $table->string('phone', 20)->nullable();
            $table->string('title', 50)->nullable();
            $table->string('license_no', 50)->nullable();
            $table->text('qualifications')->nullable();
            $table->string('pin_hash')->nullable(); // kiosk timekeeping, GĐ3
            $table->string('color', 7)->nullable();
            $table->enum('status', ['active', 'disabled'])->default('active');
            TenantSchema::datetimes($table);
            $table->dateTime('deleted_at')->nullable();

            TenantSchema::key($table);
            TenantSchema::unique($table, 'user_id');
            TenantSchema::foreign($table, 'primary_branch_id', 'branches');
        });

        Schema::create('staff_branches', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('branch_id');
            $table->boolean('is_primary')->default(false);

            $table->primary(['tenant_id', 'staff_id', 'branch_id']);
            TenantSchema::foreign($table, 'staff_id', 'staff')->cascadeOnDelete();
            TenantSchema::foreign($table, 'branch_id', 'branches')->cascadeOnDelete();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email', 191);
            $table->json('role_names');
            $table->unsignedBigInteger('primary_branch_id')->nullable();
            $table->json('branch_ids')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('invited_by_staff_id')->nullable();
            // No ON DELETE action on either inviter: both are used by the CHECK below.
            $table->foreignId('invited_by_admin_id')->nullable()->constrained('platform_admins');
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, 'email');
            TenantSchema::foreign($table, 'primary_branch_id', 'branches');
            TenantSchema::foreign($table, 'invited_by_staff_id', 'staff');
        });

        DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_one_inviter_check
            CHECK ((invited_by_staff_id IS NULL) <> (invited_by_admin_id IS NULL))');

        Schema::create('staff_working_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            // Which group this slot counts towards for online capacity; NULL = not counted (reception, cashier…).
            $table->enum('service_group', ['medical', 'grooming'])->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, ['branch_id', 'weekday']);
            TenantSchema::foreign($table, 'staff_id', 'staff')->cascadeOnDelete();
            TenantSchema::foreign($table, 'branch_id', 'branches')->cascadeOnDelete();
        });

        Schema::create('staff_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('staff_id');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::index($table, ['staff_id', 'starts_at']);
            TenantSchema::foreign($table, 'staff_id', 'staff')->cascadeOnDelete();
            TenantSchema::foreign($table, 'created_by', 'staff');
        });

        Schema::create('branch_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable(); // NULL = the whole shop
            $table->date('date');
            $table->string('reason')->nullable();
            TenantSchema::datetimes($table);

            TenantSchema::key($table);
            TenantSchema::unique($table, ['branch_id', 'date']);
            TenantSchema::foreign($table, 'branch_id', 'branches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_closures');
        Schema::dropIfExists('staff_time_off');
        Schema::dropIfExists('staff_working_hours');
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('staff_branches');
        Schema::dropIfExists('staff');
        Schema::dropIfExists('branches');
    }
};

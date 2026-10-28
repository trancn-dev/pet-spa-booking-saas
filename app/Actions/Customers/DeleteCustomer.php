<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a customer and their pets. Refused while they still have an upcoming appointment,
 * and for the per-branch stray profile.
 */
class DeleteCustomer
{
    public const HAS_UPCOMING = 'Khách còn lịch hẹn sắp tới. Hủy lịch trước khi xóa hồ sơ.';

    public function handle(Customer $customer): void
    {
        if ($customer->is_system) {
            throw ValidationException::withMessages(['customer' => 'Không xóa được hồ sơ hệ thống.']);
        }

        DB::transaction(function () use ($customer): void {
            // Same first lock as booking (customers row), so a booking racing this delete waits.
            Customer::query()->whereKey($customer->getKey())->lockForUpdate()->first();

            if (self::hasUpcoming($customer)) {
                throw ValidationException::withMessages(['customer' => self::HAS_UPCOMING]);
            }

            $customer->pets()->get()->each->delete();
            $customer->delete();
            ActivityLog::record('customer.deleted', $customer, ['name' => $customer->name, 'phone' => $customer->phone]);
        });
    }

    public static function hasUpcoming(Customer $customer): bool
    {
        return Appointment::query()->where('customer_id', $customer->getKey())
            ->whereIn('status', [AppointmentStatus::Confirmed->value, AppointmentStatus::CheckedIn->value])
            ->where('ends_at', '>', now())
            ->exists();
    }
}

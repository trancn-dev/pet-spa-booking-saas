<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use App\Support\ActivityLog;
use App\Support\Phone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a pet owner (S10). The phone is the customer's key, unique per shop and shared
 * by every branch (db-schema 2.3). Creating with the phone of a deleted profile brings that profile
 * back (its invoices and visits stay linked) instead of failing on UNIQUE(tenant_id, phone).
 */
class SaveCustomer
{
    public const TAKEN = 'SĐT này đã có hồ sơ khách.';

    public const TAKEN_BY_DELETED = 'SĐT này thuộc một hồ sơ khách đã xóa.';

    /**
     * @param  array{name: string, phone: string, email?: string|null, address?: string|null, notes?: string|null}  $data
     * @return array{0: Customer, 1: bool} the customer and whether a deleted profile was restored
     */
    public function handle(?Customer $customer, array $data): array
    {
        if ($customer?->is_system) {
            throw ValidationException::withMessages(['name' => 'Không sửa được hồ sơ hệ thống.']);
        }

        $phone = Phone::tryParse($data['phone']) ?? throw ValidationException::withMessages(['phone' => Phone::INVALID]);
        $attributes = [
            'name' => trim($data['name']),
            'phone' => $phone->e164(),
            'email' => self::blank($data['email'] ?? null),
            'address' => self::blank($data['address'] ?? null),
            'notes' => self::blank($data['notes'] ?? null),
        ];

        try {
            return DB::transaction(function () use ($customer, $attributes): array {
                $holder = Customer::query()->withTrashed()->where('phone', $attributes['phone'])
                    ->when($customer !== null, fn ($q) => $q->whereKeyNot($customer?->getKey()))
                    ->lockForUpdate()->first();

                if ($holder !== null && ($customer !== null || ! $holder->trashed())) {
                    throw ValidationException::withMessages(['phone' => $holder->trashed() ? self::TAKEN_BY_DELETED : self::TAKEN]);
                }

                if ($customer === null && $holder !== null) {
                    $holder->restore();
                    $holder->update($attributes);
                    ActivityLog::record('customer.restored', $holder);

                    return [$holder, true];
                }

                if ($customer === null) {
                    return [Customer::query()->create($attributes), false];
                }

                $customer->update($attributes);

                return [$customer, false];
            });
        } catch (UniqueConstraintViolationException) {
            // Two people saving the same phone at once: the other one won.
            throw ValidationException::withMessages(['phone' => self::TAKEN]);
        }
    }

    private static function blank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}

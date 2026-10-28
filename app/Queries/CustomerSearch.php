<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds customers by what the front desk types (S10, S2, S15). Phone lookups always hit an index,
 * never `LIKE '%…'`:
 * - a full phone (`0901234567`, `+84…`) → `phone =`               (UNIQUE tenant_id, phone)
 * - exactly 4 digits                    → `phone_suffix =`        (IDX tenant_id, phone_suffix)
 * - 5+ digits, a prefix (`0901 23`)     → `phone LIKE '+8490123%'` (prefix range on the same index)
 * - text                                → customer name contains it (accent-insensitive collation,
 *                                         within one shop), or a pet name starts with it
 * The stray profiles never show up.
 */
final class CustomerSearch
{
    /**
     * @param  Builder<Customer>  $query
     * @return Builder<Customer>
     */
    public static function apply(Builder $query, ?string $term): Builder
    {
        $query->where('customers.is_system', false);

        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $digits = (string) preg_replace('/\D/', '', $term);
        $isNumeric = preg_match('/^[\d\s+().\-]+$/', $term) === 1;

        if ($isNumeric && ($phone = Phone::tryParse($term)) !== null) {
            return $query->where('customers.phone', $phone->e164());
        }

        if ($isNumeric && strlen($digits) === 4) {
            return $query->where('customers.phone_suffix', $digits);
        }

        if ($isNumeric && strlen($digits) >= 5) {
            $national = str_starts_with($digits, '84') ? substr($digits, 2) : ltrim($digits, '0');

            return $query->where('customers.phone', 'like', '+84'.self::escape($national).'%');
        }

        $like = '%'.self::escape($term).'%';

        return $query->where(fn (Builder $q) => $q->where('customers.name', 'like', $like)
            ->orWhereHas('pets', fn (Builder $pets) => $pets->where('name', 'like', self::escape($term).'%')));
    }

    private static function escape(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\ServiceGroup;
use App\Models\Branch;
use App\Models\Service;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a service or a spa combo (S9, db-schema 2.4).
 *
 * Combos are spa only: the combo and each of its services are `grooming`, a combo never contains
 * another combo, and a service inside a combo cannot later become medical or a combo itself.
 * Exams and spa stay separate services (separate Visits), billed on one invoice.
 * No branch chosen = available at every branch.
 */
class SaveService
{
    public const COMBO_NOT_GROOMING = 'Combo chỉ dành cho dịch vụ spa.';

    public const ITEM_NOT_GROOMING = 'Combo chỉ gồm dịch vụ spa. Khám và spa là các dịch vụ riêng, gom chung hóa đơn.';

    public const NESTED_COMBO = 'Combo không chứa combo khác.';

    public const USED_IN_COMBO = 'Dịch vụ đang nằm trong combo :combos: không đổi được sang Khám hay thành combo.';

    /**
     * @param  array{code: string, name: string, service_group: string, is_combo: bool, duration_min: int, buffer_min: int, price: string, is_public: bool, is_active: bool, branch_ids: list<int>, item_ids: list<int>}  $data
     */
    public function handle(?Service $service, array $data): Service
    {
        $group = ServiceGroup::from($data['service_group']);
        $isCombo = $data['is_combo'];
        $itemIds = array_values(array_unique(array_map('intval', $data['item_ids'])));

        if ($isCombo && $group !== ServiceGroup::Grooming) {
            throw ValidationException::withMessages(['service_group' => self::COMBO_NOT_GROOMING]);
        }
        if ($isCombo && count($itemIds) < 2) {
            throw ValidationException::withMessages(['item_ids' => 'Combo cần ít nhất 2 dịch vụ spa.']);
        }

        return DB::transaction(function () use ($service, $data, $group, $isCombo, $itemIds): Service {
            $code = strtoupper(trim($data['code']));
            $taken = Service::query()->withTrashed()->where('code', $code)
                ->when($service !== null, fn ($q) => $q->whereKeyNot($service?->getKey()))->first();
            if ($taken !== null) {
                throw ValidationException::withMessages(['code' => $taken->trashed() ? 'Mã này thuộc một dịch vụ đã xóa.' : 'Mã này đã có dịch vụ dùng.']);
            }

            if ($service !== null) {
                // Lock it so a combo being saved at the same time sees its final group.
                Service::query()->whereKey($service->getKey())->lockForUpdate()->first();
                $this->ensureStillValidInCombos($service, $group, $isCombo);
            }

            if ($isCombo) {
                $this->ensureItems($service, $itemIds);
            }

            $branchIds = $this->branchIds($data['branch_ids']);

            $attributes = [
                'code' => $code,
                'name' => trim($data['name']),
                'service_group' => $group,
                'is_combo' => $isCombo,
                'duration_min' => $data['duration_min'],
                'buffer_min' => $data['buffer_min'],
                'price' => $data['price'],
                'is_public' => $data['is_public'],
                'is_active' => $data['is_active'],
            ];

            $creating = $service === null;
            if ($service === null) {
                $service = Service::query()->create($attributes);
            } else {
                $service->update($attributes);
            }

            $service->comboItems()->sync(
                $isCombo ? collect($itemIds)->mapWithKeys(fn (int $id, int $i) => [$id => ['sort_order' => $i + 1]])->all() : [],
            );
            $service->branches()->sync($branchIds);

            ActivityLog::record($creating ? 'service.created' : 'service.updated', $service, [
                'code' => $code, 'price' => $data['price'], 'duration_min' => $data['duration_min'],
            ]);

            return $service;
        });
    }

    /**
     * @param  list<int>  $itemIds
     */
    private function ensureItems(?Service $combo, array $itemIds): void
    {
        // Same shop only: the tenant scope hides other shops' rows (and the composite FK backs it).
        $items = Service::query()->whereKey($itemIds)->orderBy('id')->lockForUpdate()->get();

        if ($items->count() !== count($itemIds) || ($combo !== null && in_array($combo->getKey(), $itemIds, true))) {
            throw ValidationException::withMessages(['item_ids' => 'Có dịch vụ con không hợp lệ.']);
        }
        if ($items->contains(fn (Service $s) => $s->is_combo)) {
            throw ValidationException::withMessages(['item_ids' => self::NESTED_COMBO]);
        }
        if ($items->contains(fn (Service $s) => $s->service_group !== ServiceGroup::Grooming)) {
            throw ValidationException::withMessages(['item_ids' => self::ITEM_NOT_GROOMING]);
        }
    }

    private function ensureStillValidInCombos(Service $service, ServiceGroup $group, bool $isCombo): void
    {
        if ($group === ServiceGroup::Grooming && ! $isCombo) {
            return;
        }

        $combos = Service::query()->where('is_combo', true)
            ->whereHas('comboItems', fn ($q) => $q->where('service_combo_items.item_service_id', $service->getKey()))
            ->pluck('name');

        if ($combos->isNotEmpty()) {
            throw ValidationException::withMessages(['service_group' => str_replace(':combos', $combos->implode(', '), self::USED_IN_COMBO)]);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function branchIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $found = Branch::query()->whereKey($ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages(['branch_ids' => 'Có cơ sở không hợp lệ.']);
        }

        return $found;
    }
}

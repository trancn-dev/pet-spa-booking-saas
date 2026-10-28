<?php

declare(strict_types=1);

namespace App\Actions\Services;

use App\Enums\AppointmentItemStatus;
use App\Models\AppointmentItem;
use App\Models\Service;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft-deletes a service. Refused while a combo contains it or an upcoming appointment books it;
 * "Ngừng hoạt động" (is_active) is the usual way to retire one.
 */
class DeleteService
{
    public const HAS_UPCOMING = 'Dịch vụ còn lịch hẹn sắp tới. Tắt "Đang hoạt động" thay vì xóa.';

    public function handle(Service $service): void
    {
        DB::transaction(function () use ($service): void {
            Service::query()->whereKey($service->getKey())->lockForUpdate()->first();

            $combos = Service::query()->where('is_combo', true)
                ->whereHas('comboItems', fn ($q) => $q->where('service_combo_items.item_service_id', $service->getKey()))->pluck('name');
            if ($combos->isNotEmpty()) {
                throw ValidationException::withMessages(['service' => 'Dịch vụ đang nằm trong combo '.$combos->implode(', ').'. Bỏ khỏi combo trước.']);
            }

            $booked = AppointmentItem::query()->where('service_id', $service->getKey())
                ->where('status', AppointmentItemStatus::Booked->value)->where('ends_at', '>', now())->exists();
            if ($booked) {
                throw ValidationException::withMessages(['service' => self::HAS_UPCOMING]);
            }

            $service->delete();
            ActivityLog::record('service.deleted', $service, ['code' => $service->code, 'name' => $service->name]);
        });
    }
}

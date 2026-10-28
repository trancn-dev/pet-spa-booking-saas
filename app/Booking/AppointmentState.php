<?php

declare(strict_types=1);

namespace App\Booking;

use App\Enums\ActorType;
use App\Enums\AppointmentItemStatus as Item;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Lines move booked → checked_in | cancelled | no_show (and cancelled → booked only through
 * "Hoàn tác"). The appointment status is derived from its lines (db-schema 2.5): checked_in when
 * any line came, cancelled / no_show when every line is, confirmed otherwise. No "completed":
 * work is finished on the Visit.
 */
final class AppointmentState
{
    /** @var array<string, list<Item>> */
    private const ALLOWED = [
        'booked' => [Item::CheckedIn, Item::Cancelled, Item::NoShow],
        'cancelled' => [Item::Booked], // restore within the undo window
        'checked_in' => [],
        'no_show' => [],
    ];

    private const LABELS = [
        'booked' => 'đã đặt', 'checked_in' => 'đã đến', 'cancelled' => 'đã hủy', 'no_show' => 'không đến',
    ];

    public static function canMove(Item $from, Item $to): bool
    {
        return in_array($to, self::ALLOWED[$from->value], true);
    }

    public static function move(AppointmentItem $item, Item $to): void
    {
        if (! self::canMove($item->status, $to)) {
            throw ValidationException::withMessages(['items' => sprintf('Dòng %s không chuyển sang %s được.', self::LABELS[$item->status->value], self::LABELS[$to->value])]);
        }
        $item->update(['status' => $to]);
    }

    /** Recomputes the appointment status from its lines (lines must be fresh). */
    public static function sync(Appointment $appointment): AppointmentStatus
    {
        $statuses = AppointmentItem::query()->where('appointment_id', $appointment->id)->pluck('status')
            ->map(fn ($s) => $s instanceof Item ? $s : Item::from($s));

        $status = match (true) {
            $statuses->contains(Item::CheckedIn) => AppointmentStatus::CheckedIn,
            $statuses->isNotEmpty() && $statuses->every(fn (Item $s) => $s === Item::Cancelled) => AppointmentStatus::Cancelled,
            $statuses->isNotEmpty() && ! $statuses->contains(Item::Booked) => AppointmentStatus::NoShow,
            default => AppointmentStatus::Confirmed,
        };

        $changes = ['status' => $status];
        if ($status === AppointmentStatus::NoShow && $appointment->no_show_at === null) {
            $changes['no_show_at'] = now();
        }
        if ($status !== AppointmentStatus::Cancelled) {
            $changes += ['cancelled_at' => null, 'cancelled_by_type' => null, 'cancel_reason' => null];
        }
        $appointment->update($changes);

        return $status;
    }

    /** One history row (appointment_changes is append-only). */
    public static function log(Appointment $appointment, string $action, ActorType $actor, ?int $staffId = null, ?AppointmentItem $item = null, ?string $reason = null, ?CarbonImmutable $old = null, ?CarbonImmutable $new = null): void
    {
        AppointmentChange::query()->create([
            'appointment_id' => $appointment->id,
            'appointment_item_id' => $item?->id,
            'action' => $action,
            'old_starts_at' => $old,
            'new_starts_at' => $new,
            'actor_type' => $actor,
            'actor_staff_id' => $staffId,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}

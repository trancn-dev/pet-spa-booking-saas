<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Appointments\AddAppointmentItems;
use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CheckInAppointment;
use App\Actions\Appointments\MarkNoShow;
use App\Actions\Appointments\RescheduleAppointment;
use App\Actions\Appointments\RestoreCancellation;
use App\Booking\BookingItem;
use App\Booking\BookingResult;
use App\Enums\ActorType;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Scheduling\ShopClock;
use App\Tenancy\CurrentStaff;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Front-desk actions on an appointment (S3, task 5.6): check-in, cancel (whole or per line) and
 * undo, no-show, reschedule, add / remove lines. JSON for the drawer, redirect back otherwise.
 * Lines are addressed by id, always within the appointment.
 */
class AppointmentActionController extends Controller
{
    public function __construct(private readonly CurrentStaff $current) {}

    public function checkIn(Request $request, Appointment $appointment, CheckInAppointment $checkIn): JsonResponse|RedirectResponse
    {
        Gate::authorize('checkIn', $appointment);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
            'assign' => ['array'],
            'assign.*' => ['integer'],
        ]);

        $visits = $checkIn->handle($appointment, $this->lineIds($appointment, $data['items']), array_map('intval', $data['assign'] ?? []), $this->current->get());

        return $this->done($request, 'Đã check-in: số '.collect($visits)->pluck('queue_no')->map(fn ($n) => str_pad((string) $n, 3, '0', STR_PAD_LEFT))->implode(', ').'.', [
            'visits' => collect($visits)->map(fn ($v) => ['id' => $v->public_id, 'queue_no' => $v->queue_no, 'group' => $v->service_group->value])->all(),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, CancelAppointment $cancel): JsonResponse|RedirectResponse
    {
        Gate::authorize('cancel', $appointment);
        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => ['integer'],
            'reason' => ['required', 'string', 'max:500'],
            'notify' => ['boolean'],
            'remove' => ['boolean'],
        ], ['reason.required' => 'Chọn hoặc nhập lý do hủy.']);

        $cancel->handle(
            $appointment,
            isset($data['items']) ? $this->lineIds($appointment, $data['items']) : null,
            (string) $data['reason'],
            ActorType::Staff,
            $this->current->get()?->id,
            (bool) ($data['notify'] ?? false),
            ($data['remove'] ?? false) ? 'item_removed' : 'cancelled',
        );

        return $this->done($request, 'Đã hủy.', ['undo_seconds' => (int) config('booking.undo_seconds', 5)]);
    }

    public function restore(Request $request, Appointment $appointment, RestoreCancellation $restore): JsonResponse|RedirectResponse
    {
        Gate::authorize('cancel', $appointment);

        return $this->booking($request, $restore->handle($appointment, ActorType::Staff, $this->current->get()?->id), 'Đã hoàn tác.');
    }

    public function noShow(Request $request, Appointment $appointment, MarkNoShow $noShow): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $appointment);
        $data = $request->validate(['items' => ['nullable', 'array'], 'items.*' => ['integer']]);

        $count = $noShow->handle($appointment, isset($data['items']) ? $this->lineIds($appointment, $data['items']) : null, ActorType::Staff, $this->current->get()?->id);

        return $this->done($request, $count > 0 ? 'Đã đánh dấu không đến.' : 'Không còn dòng nào chờ khách.');
    }

    public function reschedule(Request $request, Appointment $appointment, RescheduleAppointment $reschedule): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $appointment);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $start = ShopClock::current()->toUtc((string) $data['date'], (string) $data['time']);
        $result = $reschedule->handle($appointment, $start, ActorType::Staff, $this->current->get()?->id, isset($data['reason']) ? (string) $data['reason'] : null);

        return $this->booking($request, $result, 'Đã dời lịch.');
    }

    public function addItems(Request $request, Appointment $appointment, AddAppointmentItems $add): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $appointment);
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.pet_id' => ['required', 'integer'],
            'items.*.service_id' => ['required', 'integer'],
            'items.*.assigned_staff_id' => ['nullable', 'integer'],
        ]);

        $items = array_map(fn (array $i) => new BookingItem((int) $i['pet_id'], (int) $i['service_id'], isset($i['assigned_staff_id']) ? (int) $i['assigned_staff_id'] : null), $data['items']);

        return $this->booking($request, $add->handle($appointment, array_values($items), ActorType::Staff, $this->current->get()?->id), 'Đã thêm dịch vụ.');
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private function lineIds(Appointment $appointment, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $found = AppointmentItem::query()->where('appointment_id', $appointment->id)->whereKey($ids)->count();
        abort_if($found !== count($ids), 404);

        return $ids;
    }

    private function booking(Request $request, BookingResult $result, string $message): JsonResponse|RedirectResponse
    {
        return $this->done($request, $message, ['warnings' => $result->warnings, 'starts_at' => CarbonImmutable::instance($result->appointment->starts_at)->toIso8601ZuluString()]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function done(Request $request, string $message, array $extra = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, ...$extra]);
        }

        $warnings = $extra['warnings'] ?? [];

        return back()->with('status', $warnings === [] ? $message : $message.' '.implode(' ', $warnings));
    }
}

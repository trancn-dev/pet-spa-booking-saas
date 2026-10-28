<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Appointments\BookAppointment;
use App\Authorization\CurrentBranch;
use App\Booking\BookingItem;
use App\Booking\BookingRequest;
use App\Enums\AppointmentSource;
use App\Enums\ServiceGroup;
use App\Http\Controllers\Controller;
use App\Http\Resources\PetSummary;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\MedicalEntry;
use App\Models\Pet;
use App\Models\Reminder;
use App\Models\Service;
use App\Models\Visit;
use App\Pricing\ServiceQuoteResolver;
use App\Queries\CalendarDay;
use App\Scheduling\ShopClock;
use App\Support\Phone;
use App\Tenancy\CurrentStaff;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S1 calendar, S2 booking drawer, S3 detail drawer (task 5.7), for the branch in the topbar.
 * The page loads one day (or week) of data; the drawers talk JSON.
 */
class AppointmentController extends Controller
{
    private const ACTIONS = [
        'rescheduled' => 'Dời lịch', 'cancelled' => 'Hủy', 'restored' => 'Hoàn tác hủy', 'checked_in' => 'Check-in',
        'no_show' => 'Không đến', 'reassigned' => 'Đổi người', 'item_added' => 'Thêm dịch vụ', 'item_removed' => 'Bỏ dịch vụ',
    ];

    public function __construct(
        private readonly CurrentStaff $current,
        private readonly TenantContext $context,
    ) {}

    public function index(Request $request, CalendarDay $calendar): Response
    {
        Gate::authorize('viewAny', Appointment::class);
        $branch = $this->branch();
        $clock = ShopClock::current();

        $date = $this->date($request->string('date')->value(), $clock);
        $view = in_array($request->string('view')->value(), ['day', 'week', 'staff'], true) ? $request->string('view')->value() : 'staff';
        $monday = CarbonImmutable::parse($date)->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d');

        return Inertia::render('App/Appointments/Index', [
            'date' => $date,
            'view' => $view,
            'today' => $clock->today()->format('Y-m-d'),
            'timezone' => $clock->timezone(),
            'range' => ['from' => CalendarDay::FROM, 'to' => CalendarDay::TO],
            'branch' => ['id' => $branch->id, 'public_id' => $branch->public_id, 'name' => $branch->name],
            'day' => $view === 'week' ? null : $calendar->day($branch, $date),
            'week' => $view === 'week' ? $calendar->week($branch, $monday) : null,
            'quota' => $this->quota($clock),
            'services' => Service::query()->where('is_active', true)->availableAt($branch)->orderBy('service_group')->orderBy('code')
                ->get(['id', 'code', 'name', 'service_group', 'is_combo', 'duration_min', 'price'])
                ->map(fn (Service $s) => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'group' => $s->service_group->value, 'is_combo' => $s->is_combo, 'price' => (string) $s->price])->values(),
            'species' => PetController::speciesOptions(),
            'can' => [
                'book' => Gate::allows('create', [Appointment::class, $branch]),
                'createCustomer' => Gate::allows('create', Customer::class),
                'createPet' => Gate::allows('create', Pet::class),
            ],
        ]);
    }

    /** S1 data for one day, to refresh after a change without reloading the page. */
    public function day(Request $request, CalendarDay $calendar): JsonResponse
    {
        Gate::authorize('viewAny', Appointment::class);

        return response()->json(['data' => $calendar->day($this->branch(), $this->date($request->string('date')->value(), ShopClock::current()))]);
    }

    /** S2 submit: the front desk books (never refused by capacity, warned instead). */
    public function store(Request $request, BookAppointment $book): JsonResponse
    {
        $branch = $this->branch();
        Gate::authorize('create', [Appointment::class, $branch]);

        $data = $request->validate([
            'customer_id' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.pet_id' => ['required', 'string'],
            'items.*.service_id' => ['required', 'integer'],
            'items.*.assigned_staff_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'send_reminders' => ['boolean'],
            'preferred_staff_id' => ['nullable', 'integer'],
            'idempotency_key' => ['required', 'uuid'],
        ], ['items.required' => 'Chọn ít nhất một bé và dịch vụ.']);

        $customer = Customer::query()->where('public_id', $data['customer_id'])->firstOrFail();
        $pets = Pet::query()->whereIn('public_id', array_column($data['items'], 'pet_id'))->pluck('id', 'public_id');

        $result = $book->handle(new BookingRequest(
            branchId: $branch->id,
            customerId: $customer->id,
            items: array_values(array_map(fn (array $i) => new BookingItem(
                (int) ($pets[$i['pet_id']] ?? abort(422, 'Thú cưng không hợp lệ.')),
                (int) $i['service_id'],
                isset($i['assigned_staff_id']) ? (int) $i['assigned_staff_id'] : null,
            ), $data['items'])),
            startsAt: ShopClock::current()->toUtc((string) $data['date'], (string) $data['time']),
            source: AppointmentSource::Staff,
            idempotencyKey: (string) $data['idempotency_key'],
            preferredStaffId: isset($data['preferred_staff_id']) ? (int) $data['preferred_staff_id'] : null,
            customerNote: isset($data['note']) ? (string) $data['note'] : null,
            createdByStaffId: $this->current->get()?->id,
            sendReminders: (bool) ($data['send_reminders'] ?? true),
        ));

        return response()->json([
            'message' => $result->replayed ? 'Lịch này đã được lưu trước đó.' : 'Đã đặt lịch.',
            'appointment' => $result->appointment->public_id,
            'warnings' => $result->warnings,
        ], $result->replayed ? 200 : 201);
    }

    /**
     * S2 lines: duration and reference price of services for one pet, with the reason.
     */
    public function quotes(Request $request, ServiceQuoteResolver $resolver): JsonResponse
    {
        Gate::authorize('viewAny', Appointment::class);
        $data = $request->validate(['pet' => ['required', 'string'], 'services' => ['required', 'array', 'max:30'], 'services.*' => ['integer']]);

        $pet = Pet::query()->where('public_id', $data['pet'])->firstOrFail();
        $services = Service::query()->whereKey($data['services'])->get();

        return response()->json(['data' => $services->mapWithKeys(fn (Service $s) => [$s->id => $resolver->resolve($s, $pet)->toArray()])]);
    }

    /** People on duty in a group at a time, for "Người làm" (S2) and check-in (S3). */
    public function onDuty(Request $request, CalendarDay $calendar): JsonResponse
    {
        Gate::authorize('viewAny', Appointment::class);
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $day = $calendar->day($this->branch(), (string) $data['date']);

        return response()->json(['data' => array_map(fn (array $s) => ['id' => $s['id'], 'name' => $s['name'], 'groups' => $s['groups'], 'slots' => $s['slots']], $day['staff'])]);
    }

    /** S3 drawer. */
    public function show(Appointment $appointment): JsonResponse
    {
        Gate::authorize('view', $appointment);
        $clock = ShopClock::current();

        $appointment->load([
            'customer' => fn ($q) => $q->withTrashed(),
            'branch:id,name',
            'preferredStaff:id,display_name',
            'items' => fn ($q) => $q->orderBy('id'),
            'items.pet' => fn ($q) => $q->withTrashed(),
            'items.service' => fn ($q) => $q->withTrashed(),
            'items.assignedStaff:id,display_name',
        ]);
        $changes = AppointmentChange::query()->where('appointment_id', $appointment->id)->with(['actorStaff:id,display_name', 'item.pet' => fn ($q) => $q->withTrashed()->select(['id', 'name'])])
            ->orderBy('created_at')->orderBy('id')->get();
        $reminders = Reminder::query()->where('remindable_type', 'appointment')->where('remindable_id', $appointment->id)
            ->with('deliveries')->orderBy('send_at')->get();

        $petNames = $appointment->items->mapWithKeys(fn (AppointmentItem $i) => [$i->pet_id => $i->pet->name])->all();

        return response()->json(['data' => [
            'id' => $appointment->public_id,
            'code' => strtoupper(substr($appointment->public_id, -6)),
            'status' => $appointment->status->value,
            'status_label' => $appointment->status->label(),
            'source' => $appointment->source->value,
            'send_reminders' => $appointment->send_reminders,
            'date' => $clock->toLocal($appointment->starts_at)->format('Y-m-d'),
            'start' => $clock->toLocal($appointment->starts_at)->format('H:i'),
            'end' => $clock->toLocal($appointment->ends_at)->format('H:i'),
            'branch' => $appointment->branch->name,
            'note' => $appointment->customer_note,
            'cancel_reason' => $appointment->cancel_reason,
            'preferred' => $appointment->preferredStaff?->display_name,
            'customer' => [
                'id' => $appointment->customer->public_id,
                'name' => $appointment->customer->name,
                'phone' => Phone::format($appointment->customer->phone),
                'tel' => $appointment->customer->phone,
            ],
            'lines' => $appointment->items->map(fn (AppointmentItem $i) => [
                'id' => $i->id,
                'pet' => ['id' => $i->pet->public_id, 'name' => $i->pet->name, 'alerts' => PetSummary::alerts($i->pet)],
                'service' => $i->service->name,
                'group' => $i->service_group->value,
                'group_label' => $i->service_group->label(),
                'duration_min' => $i->duration_min,
                'reference_price' => (string) $i->reference_price,
                'staff' => $i->assignedStaff?->display_name,
                'staff_id' => $i->assigned_staff_id,
                'status' => $i->status->value,
                'checked_in' => $i->visit_id !== null,
            ])->values(),
            'history' => $changes->map(fn (AppointmentChange $c) => [
                'action' => self::ACTIONS[$c->action->value],
                'at' => $clock->toLocal($c->created_at)->format('d/m H:i'),
                'by' => $c->actorStaff->display_name ?? ($c->actor_type->value === 'customer' ? 'Khách' : 'Hệ thống'),
                'pet' => $c->item?->pet?->name,
                'reason' => $c->reason,
                'from' => $c->old_starts_at === null ? null : $clock->toLocal($c->old_starts_at)->format('d/m H:i'),
                'to' => $c->new_starts_at === null || $c->action->value !== 'rescheduled' ? null : $clock->toLocal($c->new_starts_at)->format('d/m H:i'),
            ])->values(),
            'reminders' => $reminders->map(fn (Reminder $r) => [
                'purpose' => $r->purpose->value,
                'status' => $r->status->value,
                'send_at' => $clock->toLocal($r->send_at)->format('d/m H:i'),
                'deliveries' => $r->deliveries->map(fn ($d) => ['channel' => $d->channel->value, 'status' => $d->status->value, 'error' => $d->error])->values(),
            ])->values(),
            'medical' => $this->medical($petNames),
            'can' => [
                'checkIn' => Gate::allows('checkIn', $appointment),
                'cancel' => Gate::allows('cancel', $appointment),
                'update' => Gate::allows('update', $appointment),
            ],
            'undo_seconds' => (int) config('booking.undo_seconds', 5),
        ]]);
    }

    /**
     * S3 "khối y tế": vets see the latest diagnosis, other medical-alert readers only the date of the
     * last exam (the alerts are on every pet line already), others nothing.
     *
     * @param  array<int, string>  $petNames  pet id => name
     * @return list<array{pet: string, last_exam: string|null, diagnosis: string|null}>|null
     */
    private function medical(array $petNames): ?array
    {
        $petIds = array_keys($petNames);
        if (! Gate::allows('viewAlerts', MedicalEntry::class)) {
            return null;
        }
        $full = Gate::allows('viewFull', MedicalEntry::class);

        $last = Visit::query()->whereIn('pet_id', $petIds)->where('service_group', ServiceGroup::Medical->value)->where('status', 'done')
            ->select('pet_id', DB::raw('MAX(visit_date) AS last_exam'))->groupBy('pet_id')->pluck('last_exam', 'pet_id');
        $diagnoses = $full
            ? MedicalEntry::query()->join('medical_records', 'medical_records.id', '=', 'medical_entries.medical_record_id')
                ->whereIn('medical_records.pet_id', $petIds)->orderBy('medical_entries.recorded_at')
                ->get(['medical_records.pet_id', 'medical_entries.diagnosis'])->keyBy('pet_id')
            : collect();

        return array_map(fn (int $id) => [
            'pet' => $petNames[$id],
            'last_exam' => isset($last[$id]) ? CarbonImmutable::parse((string) $last[$id])->format('d/m/Y') : null,
            'diagnosis' => $full ? $diagnoses->get($id)?->getAttribute('diagnosis') : null,
        ], $petIds);
    }

    /**
     * @return array{used: int, max: int|null, full: bool}
     */
    private function quota(ShopClock $clock): array
    {
        $tenant = $this->context->require();
        $used = (int) DB::table('tenant_usage')->where('tenant_id', $tenant->getKey())->where('period', $clock->today()->format('Y-m'))->value('appointments_count');
        $max = $tenant->plan()->value('max_appointments_per_month');

        return ['used' => $used, 'max' => $max === null ? null : (int) $max, 'full' => $max !== null && $used >= (int) $max];
    }

    private function branch(): Branch
    {
        return CurrentBranch::get() ?? abort(403, 'Chưa chọn cơ sở.');
    }

    private function date(string $value, ShopClock $clock): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : $clock->today()->format('Y-m-d');
    }
}

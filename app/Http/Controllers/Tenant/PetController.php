<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Customers\CreateStrayPet;
use App\Actions\Customers\DeletePet;
use App\Actions\Customers\SavePet;
use App\Authorization\CurrentBranch;
use App\Enums\AppointmentItemStatus;
use App\Enums\PetSex;
use App\Enums\PetSize;
use App\Enums\VaccinationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PetSummary;
use App\Models\AppointmentItem;
use App\Models\Breed;
use App\Models\Customer;
use App\Models\MedicalEntry;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Species;
use App\Models\Visit;
use App\Support\PetSizeSuggester;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pets (task 5.1): added and edited inside their customer, S4 profile (Tổng quan, Lịch sử; the
 * Bệnh án and Tiêm phòng tabs come with phase 6), quick "Thú hoang #n".
 */
class PetController extends Controller
{
    public function create(Customer $customer): Response
    {
        Gate::authorize('create', Pet::class);

        return $this->form($customer, null);
    }

    public function store(Request $request, Customer $customer, SavePet $save): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Pet::class);

        $pet = $save->handle($customer, null, $this->validated($request));

        if ($request->expectsJson()) { // quick add from S2
            $pet->load('species:id,name');

            return response()->json(['data' => ['id' => $pet->public_id, 'name' => $pet->name, 'species' => $pet->species->name, 'size' => $pet->size->value, 'alerts' => PetSummary::alerts($pet)]], 201);
        }

        return to_route('app.pets.show', $pet)->with('status', 'Đã thêm '.$pet->name.'.');
    }

    public function storeStray(Request $request, CreateStrayPet $create): RedirectResponse
    {
        Gate::authorize('create', Pet::class);

        $branch = CurrentBranch::get();
        abort_if($branch === null, 403);
        CurrentBranch::authorize($branch);

        $data = $request->validate([
            'species_id' => ['required', 'integer', Rule::exists('species', 'id')],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['species_id.required' => 'Chọn loài.']);

        $pet = $create->handle($branch, (int) $data['species_id'], isset($data['note']) ? (string) $data['note'] : null);

        return to_route('app.pets.show', $pet)->with('status', 'Đã tạo hồ sơ '.$pet->name.' ở '.$branch->name.'.');
    }

    public function show(Request $request, Pet $pet): Response
    {
        Gate::authorize('view', $pet);

        $pet->load(['species:id,name', 'breed:id,name', 'customer:id,public_id,name,phone,is_system,system_branch_id']);
        $tz = tenant()->timezone ?? config('app.timezone');

        $upcoming = AppointmentItem::query()->where('pet_id', $pet->id)
            ->where('status', AppointmentItemStatus::Booked->value)->where('ends_at', '>', now())
            ->with(['service:id,name', 'appointment:id,branch_id', 'appointment.branch:id,name'])
            ->orderBy('starts_at')->limit(10)->get();

        $canSeeVaccinations = Gate::allows('viewAny', PetVaccination::class);
        $due = $canSeeVaccinations
            ? PetVaccination::query()->where('pet_id', $pet->id)->whereNull('superseded_at')->whereNotNull('next_due_on')
                ->with('vaccine:id,name')->orderBy('next_due_on')->limit(10)->get()
            : collect();

        $canSeeVisits = Gate::allows('viewAny', Visit::class);
        $visits = Visit::query()->where('pet_id', $pet->id)->with('branch:id,name')
            ->latest('visit_date')->latest('id')->limit(50)->get();

        return Inertia::render('App/Pets/Show', [
            'pet' => [
                ...PetSummary::make($pet),
                'birth_date' => $pet->birth_date?->toDateString(),
                'customer' => [
                    'id' => $pet->customer->public_id,
                    'name' => $pet->customer->name,
                    'phone' => Phone::format($pet->customer->phone),
                    'is_system' => $pet->customer->is_system,
                ],
            ],
            'tab' => in_array($request->string('tab')->value(), ['overview', 'history', 'medical', 'vaccinations'], true)
                ? $request->string('tab')->value() : 'overview',
            'upcoming' => $upcoming->map(fn (AppointmentItem $i): array => [
                'starts_at' => $i->starts_at->toIso8601String(),
                'service' => $i->service->name,
                'branch' => $i->appointment->branch->name,
            ])->values()->all(),
            'vaccinationsDue' => $due->map(fn (PetVaccination $v): array => [
                'vaccine' => $v->vaccine->name,
                'next_due_on' => $v->next_due_on?->toDateString(),
                'postponed' => $v->status === VaccinationStatus::Postponed,
                'overdue' => $v->next_due_on !== null && $v->next_due_on->lt(now($tz)->startOfDay()),
            ])->values()->all(),
            // Every visit shows its branch (task 5.1); the reason only to people who see visits.
            'history' => $visits->map(fn (Visit $v): array => [
                'date' => $v->visit_date->toDateString(),
                'branch' => $v->branch->name,
                'group' => $v->service_group->value,
                'group_label' => $v->service_group->label(),
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'summary' => $canSeeVisits ? $v->reason : null,
            ])->values()->all(),
            'timezone' => $tz,
            'can' => [
                'update' => Gate::allows('update', $pet),
                'delete' => Gate::allows('delete', $pet),
                'viewVisits' => $canSeeVisits,
                'medicalTab' => Gate::allows('viewAlerts', MedicalEntry::class),
                'vaccinationsTab' => $canSeeVaccinations,
            ],
        ]);
    }

    public function edit(Pet $pet): Response
    {
        Gate::authorize('update', $pet);

        $pet->load('customer');

        return $this->form($pet->customer, $pet);
    }

    public function update(Request $request, Pet $pet, SavePet $save): RedirectResponse
    {
        Gate::authorize('update', $pet);

        $save->handle($pet->customer()->firstOrFail(), $pet, $this->validated($request));

        return to_route('app.pets.show', $pet)->with('status', 'Đã lưu hồ sơ '.$pet->name.'.');
    }

    public function destroy(Pet $pet, DeletePet $delete): RedirectResponse
    {
        Gate::authorize('delete', $pet);

        $customer = $pet->customer()->firstOrFail();
        $delete->handle($pet);

        return ($customer->is_system ? to_route('app.customers.index', ['tab' => 'strays']) : to_route('app.customers.show', $customer))
            ->with('status', 'Đã xóa hồ sơ '.$pet->name.'.');
    }

    /**
     * The size hint next to the size field: from the weight if known, else the breed's usual size.
     */
    public function sizeSuggestion(Request $request, PetSizeSuggester $sizes): JsonResponse
    {
        Gate::authorize('create', Pet::class);

        $data = $request->validate([
            'species_id' => ['nullable', 'integer'],
            'breed_id' => ['nullable', 'integer'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999'],
        ]);

        $breed = isset($data['breed_id']) ? Breed::query()->find($data['breed_id']) : null;
        $code = Species::query()->whereKey($breed->species_id ?? $data['species_id'] ?? 0)->value('code');
        $hint = $sizes->suggest(is_string($code) ? $code : null, $breed?->default_size, $breed?->name, $data['weight_kg'] ?? null);

        return response()->json(['data' => $hint === null ? null : [
            'size' => $hint['size']->value,
            'label' => $hint['size']->label(),
            'reason' => $hint['reason'],
        ]]);
    }

    /**
     * @return list<array{id: int, name: string, breeds: list<array{id: int, name: string, default_size: string|null}>}>
     */
    public static function speciesOptions(): array
    {
        return Species::query()->with(['breeds' => fn ($q) => $q->orderBy('name')])->orderBy('id')->get()
            ->map(fn (Species $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'breeds' => $s->breeds->map(fn (Breed $b): array => ['id' => $b->id, 'name' => $b->name, 'default_size' => $b->default_size?->value])->values()->all(),
            ])->values()->all();
    }

    private function form(Customer $customer, ?Pet $pet): Response
    {
        return Inertia::render('App/Pets/Form', [
            'customer' => ['id' => $customer->public_id, 'name' => $customer->name, 'is_system' => $customer->is_system],
            'pet' => $pet === null ? null : [
                'id' => $pet->public_id,
                'name' => $pet->name,
                'species_id' => $pet->species_id,
                'breed_id' => $pet->breed_id,
                'sex' => $pet->sex->value,
                'birth_date' => $pet->birth_date?->toDateString(),
                'weight_kg' => $pet->weight_kg,
                'size' => $pet->size->value,
                'is_neutered' => $pet->is_neutered,
                'allergies' => $pet->allergies,
                'chronic_conditions' => $pet->chronic_conditions,
                'behavior_notes' => $pet->behavior_notes,
            ],
            'species' => self::speciesOptions(),
            'sizes' => array_map(fn (PetSize $s): array => ['value' => $s->value, 'label' => $s->label()], PetSize::cases()),
            'sexes' => array_map(fn (PetSex $s): array => ['value' => $s->value, 'label' => $s->label()], PetSex::cases()),
        ]);
    }

    /**
     * @return array{name: string, species_id: int, breed_id: int|null, sex: string|null, birth_date: string|null, weight_kg: string|null, size: string|null, is_neutered: bool, allergies: string|null, chronic_conditions: string|null, behavior_notes: string|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'species_id' => ['required', 'integer', Rule::exists('species', 'id')],
            'breed_id' => ['nullable', 'integer', Rule::exists('breeds', 'id')],
            'sex' => ['nullable', Rule::enum(PetSex::class)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'weight_kg' => ['nullable', 'numeric', 'min:0.01', 'max:999.99'],
            'size' => ['nullable', Rule::enum(PetSize::class)],
            'is_neutered' => ['boolean'],
            'allergies' => ['nullable', 'string', 'max:2000'],
            'chronic_conditions' => ['nullable', 'string', 'max:2000'],
            'behavior_notes' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nhập tên bé.',
            'species_id.required' => 'Chọn loài.',
            'birth_date.before_or_equal' => 'Ngày sinh không thể ở tương lai.',
        ]);

        $str = fn (string $key): ?string => isset($data[$key]) && $data[$key] !== '' ? (string) $data[$key] : null;

        return [
            'name' => (string) $data['name'],
            'species_id' => (int) $data['species_id'],
            'breed_id' => isset($data['breed_id']) ? (int) $data['breed_id'] : null,
            'sex' => $str('sex'),
            'birth_date' => $str('birth_date'),
            'weight_kg' => $str('weight_kg'),
            'size' => $str('size'),
            'is_neutered' => (bool) ($data['is_neutered'] ?? false),
            'allergies' => $str('allergies'),
            'chronic_conditions' => $str('chronic_conditions'),
            'behavior_notes' => $str('behavior_notes'),
        ];
    }
}

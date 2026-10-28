<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Services\DeleteService;
use App\Actions\Services\SavePriceRule;
use App\Actions\Services\SaveService;
use App\Enums\PetSize;
use App\Enums\ServiceGroup;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServicePriceRule;
use App\Pricing\PetProfile;
use App\Pricing\ServiceQuoteResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S9 Dịch vụ (task 5.2): services and spa combos, the branches offering them, duration / price
 * rules by species, breed and size, and the "Thử tính" quote box.
 */
class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Service::class);

        $group = ServiceGroup::tryFrom($request->string('group')->value());
        $services = Service::query()
            ->with(['branches:id,name', 'comboItems:id,name'])
            ->withCount('priceRules')
            ->when($group !== null, fn ($q) => $q->where('service_group', $group))
            ->orderBy('service_group')->orderBy('code')->get();
        $branchCount = Branch::query()->count();

        return Inertia::render('App/Services/Index', [
            'filters' => ['group' => $group?->value],
            'services' => $services->map(fn (Service $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'name' => $s->name,
                'group' => $s->service_group->value,
                'group_label' => $s->service_group->label(),
                'is_combo' => $s->is_combo,
                'items' => $s->comboItems->pluck('name')->all(),
                'duration_min' => $s->duration_min,
                'buffer_min' => $s->buffer_min,
                'price' => (string) $s->price,
                'is_public' => $s->is_public,
                'is_active' => $s->is_active,
                'branches' => $s->branches->isEmpty() || $s->branches->count() === $branchCount ? null : $s->branches->pluck('name')->all(),
                'rules_count' => (int) $s->getAttribute('price_rules_count'),
            ])->values()->all(),
            'can' => ['manage' => Gate::allows('create', Service::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Service::class);

        return $this->form(null);
    }

    public function store(Request $request, SaveService $save): RedirectResponse
    {
        Gate::authorize('create', Service::class);

        $service = $save->handle(null, $this->validated($request));

        return to_route('app.services.show', $service)->with('status', 'Đã thêm '.$service->name.'.');
    }

    public function show(Service $service): Response
    {
        Gate::authorize('viewAny', Service::class);

        $service->load(['branches:id,name', 'comboItems:id,code,name,price,duration_min', 'priceRules.species:id,name', 'priceRules.breed:id,name']);
        $rules = $service->priceRules->sortByDesc(fn (ServicePriceRule $r) => ($r->breed_id ? 4 : 0) + ($r->species_id ? 2 : 0) + ($r->size ? 1 : 0))->values();

        return Inertia::render('App/Services/Show', [
            'service' => [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
                'group' => $service->service_group->value,
                'group_label' => $service->service_group->label(),
                'is_combo' => $service->is_combo,
                'duration_min' => $service->duration_min,
                'buffer_min' => $service->buffer_min,
                'price' => (string) $service->price,
                'is_public' => $service->is_public,
                'is_active' => $service->is_active,
                'branches' => $service->branches->pluck('name')->all(),
                'items' => $service->comboItems->map(fn (Service $i): array => [
                    'code' => $i->code, 'name' => $i->name, 'price' => (string) $i->price, 'duration_min' => $i->duration_min,
                ])->values()->all(),
            ],
            'rules' => $rules->map(fn (ServicePriceRule $r): array => [
                'id' => $r->id,
                'species_id' => $r->species_id,
                'breed_id' => $r->breed_id,
                'size' => $r->size?->value,
                'label' => ServiceQuoteResolver::label($r),
                'duration_min' => $r->duration_min,
                'price' => $r->price === null ? null : (string) $r->price,
            ])->all(),
            'species' => PetController::speciesOptions(),
            'sizes' => array_map(fn (PetSize $s): array => ['value' => $s->value, 'label' => $s->label()], PetSize::cases()),
            'can' => [
                'manage' => Gate::allows('update', $service),
                'delete' => Gate::allows('delete', $service),
            ],
        ]);
    }

    public function edit(Service $service): Response
    {
        Gate::authorize('update', $service);

        return $this->form($service->load(['branches:id', 'comboItems:id']));
    }

    public function update(Request $request, Service $service, SaveService $save): RedirectResponse
    {
        Gate::authorize('update', $service);

        $save->handle($service, $this->validated($request));

        return to_route('app.services.show', $service)->with('status', 'Đã lưu dịch vụ.');
    }

    public function destroy(Service $service, DeleteService $delete): RedirectResponse
    {
        Gate::authorize('delete', $service);

        $delete->handle($service);

        return to_route('app.services.index')->with('status', 'Đã xóa '.$service->name.'.');
    }

    /**
     * "Thử tính": duration and reference price for a species / breed / size.
     */
    public function quote(Request $request, Service $service, ServiceQuoteResolver $resolver): JsonResponse
    {
        Gate::authorize('viewAny', Service::class);

        $data = $request->validate([
            'species_id' => ['nullable', 'integer'],
            'breed_id' => ['nullable', 'integer'],
            'size' => ['nullable', Rule::enum(PetSize::class)],
        ]);

        $quote = $resolver->resolve($service, new PetProfile(
            isset($data['species_id']) ? (int) $data['species_id'] : null,
            isset($data['breed_id']) ? (int) $data['breed_id'] : null,
            isset($data['size']) ? PetSize::from((string) $data['size']) : null,
        ));

        return response()->json(['data' => $quote->toArray()]);
    }

    public function storeRule(Request $request, Service $service, SavePriceRule $save): RedirectResponse
    {
        Gate::authorize('update', $service);

        $save->handle($service, null, $this->validatedRule($request));

        return back()->with('status', 'Đã thêm quy tắc.');
    }

    public function updateRule(Request $request, Service $service, ServicePriceRule $rule, SavePriceRule $save): RedirectResponse
    {
        Gate::authorize('update', $service);
        abort_unless($rule->service_id === $service->id, 404);

        $save->handle($service, $rule, $this->validatedRule($request));

        return back()->with('status', 'Đã lưu quy tắc.');
    }

    public function destroyRule(Service $service, ServicePriceRule $rule): RedirectResponse
    {
        Gate::authorize('update', $service);
        abort_unless($rule->service_id === $service->id, 404);

        $rule->delete();

        return back()->with('status', 'Đã xóa quy tắc.');
    }

    private function form(?Service $service): Response
    {
        $choices = Service::query()->where('service_group', ServiceGroup::Grooming)->where('is_combo', false)
            ->when($service !== null, fn ($q) => $q->whereKeyNot($service?->id))
            ->orderBy('code')->get(['id', 'code', 'name', 'price', 'duration_min']);

        return Inertia::render('App/Services/Form', [
            'service' => $service === null ? null : [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
                'service_group' => $service->service_group->value,
                'is_combo' => $service->is_combo,
                'duration_min' => $service->duration_min,
                'buffer_min' => $service->buffer_min,
                'price' => (string) $service->price,
                'is_public' => $service->is_public,
                'is_active' => $service->is_active,
                'branch_ids' => $service->branches->pluck('id')->all(),
                'item_ids' => $service->comboItems->pluck('id')->all(),
            ],
            'branches' => Branch::query()->orderBy('code')->get(['id', 'code', 'name']),
            'groomingServices' => $choices->map(fn (Service $s): array => [
                'id' => $s->id, 'code' => $s->code, 'name' => $s->name, 'price' => (string) $s->price, 'duration_min' => $s->duration_min,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array{code: string, name: string, service_group: string, is_combo: bool, duration_min: int, buffer_min: int, price: string, is_public: bool, is_active: bool, branch_ids: list<int>, item_ids: list<int>}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'name' => ['required', 'string', 'max:191'],
            'service_group' => ['required', Rule::enum(ServiceGroup::class)],
            'is_combo' => ['boolean'],
            'duration_min' => ['required', 'integer', 'min:5', 'max:1440'],
            'buffer_min' => ['nullable', 'integer', 'min:0', 'max:240'],
            'price' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer'],
            'item_ids' => ['array'],
            'item_ids.*' => ['integer'],
        ], [
            'code.regex' => 'Mã chỉ gồm chữ không dấu, số, dấu chấm, gạch ngang.',
            'duration_min.min' => 'Thời lượng tối thiểu 5 phút.',
            'price.decimal' => 'Giá là số tiền, tối đa 2 số lẻ.',
        ]);

        return [
            'code' => (string) $data['code'],
            'name' => (string) $data['name'],
            'service_group' => (string) $data['service_group'],
            'is_combo' => (bool) ($data['is_combo'] ?? false),
            'duration_min' => (int) $data['duration_min'],
            'buffer_min' => (int) ($data['buffer_min'] ?? 0),
            'price' => (string) $data['price'],
            'is_public' => (bool) ($data['is_public'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? false),
            'branch_ids' => array_values(array_map('intval', $data['branch_ids'] ?? [])),
            'item_ids' => array_values(array_map('intval', $data['item_ids'] ?? [])),
        ];
    }

    /**
     * @return array{species_id: int|null, breed_id: int|null, size: string|null, duration_min: int|null, price: string|null}
     */
    private function validatedRule(Request $request): array
    {
        $data = $request->validate([
            'species_id' => ['nullable', 'integer', Rule::exists('species', 'id')],
            'breed_id' => ['nullable', 'integer', Rule::exists('breeds', 'id')],
            'size' => ['nullable', Rule::enum(PetSize::class)],
            'duration_min' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'price' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
        ]);

        return [
            'species_id' => isset($data['species_id']) ? (int) $data['species_id'] : null,
            'breed_id' => isset($data['breed_id']) ? (int) $data['breed_id'] : null,
            'size' => isset($data['size']) && $data['size'] !== '' ? (string) $data['size'] : null,
            'duration_min' => isset($data['duration_min']) ? (int) $data['duration_min'] : null,
            'price' => isset($data['price']) && $data['price'] !== '' ? (string) $data['price'] : null,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\SaveCustomer;
use App\Authorization\CurrentBranch;
use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PetSummary;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pet;
use App\Queries\CustomerSearch;
use App\Support\Phone;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S10 Khách (task 5.1): list, search, profile with pets, appointments and invoices. The phone is
 * the customer's key, shared by every branch; the stray profiles are hidden here (tab "Thú hoang").
 */
class CustomerController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $tab = $request->string('tab')->value() === 'strays' ? 'strays' : 'customers';
        $term = $request->string('q')->trim()->value();

        $customers = CustomerSearch::apply(Customer::query(), $term)
            ->select(['id', 'public_id', 'name', 'phone'])
            ->withCount('pets')
            ->withMax(['visits as last_visit_on' => fn (Builder $q) => $q->where('status', '!=', VisitStatus::Cancelled->value)], 'visit_date')
            ->withSum(['invoices as total_spent' => fn (Builder $q) => $q->where('status', InvoiceStatus::Completed->value)], 'total')
            ->with(['pets' => fn ($q) => $q->select(['id', 'public_id', 'customer_id', 'name', 'allergies', 'chronic_conditions', 'behavior_notes'])->orderBy('name')])
            ->orderBy('name')->orderBy('id')
            ->paginate(self::PER_PAGE)->withQueryString();

        $branch = CurrentBranch::get();
        $strays = $tab === 'strays' && $branch !== null && Gate::allows('viewAny', Pet::class)
            ? Pet::query()->whereHas('customer', fn (Builder $q) => $q->where('is_system', true)->where('system_branch_id', $branch->id))
                ->with(['species:id,name', 'breed:id,name'])->latest('id')->limit(100)->get()
            : collect();

        return Inertia::render('App/Customers/Index', [
            'tab' => $tab,
            'filters' => ['q' => $term],
            'customers' => [
                'data' => collect($customers->items())->map(fn (Customer $c): array => [
                    'id' => $c->public_id,
                    'name' => $c->name,
                    'phone' => Phone::format($c->phone),
                    'pets_count' => (int) $c->getAttribute('pets_count'),
                    'pets' => $c->pets->map(fn (Pet $p): array => ['id' => $p->public_id, 'name' => $p->name, 'alerts' => PetSummary::alerts($p)])->values()->all(),
                    'last_visit_on' => $c->getAttribute('last_visit_on'),
                    'total_spent' => $c->getAttribute('total_spent') === null ? '0.00' : (string) $c->getAttribute('total_spent'),
                ])->values()->all(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
                'prev_page_url' => $customers->previousPageUrl(),
                'next_page_url' => $customers->nextPageUrl(),
            ],
            'strays' => $strays->map(fn (Pet $p): array => PetSummary::make($p))->values()->all(),
            'branch' => $branch?->name,
            'species' => PetController::speciesOptions(),
            'can' => [
                'create' => Gate::allows('create', Customer::class),
                'createStray' => Gate::allows('create', Pet::class) && $branch !== null,
            ],
        ]);
    }

    /**
     * Combobox for S2 (booking) and S15 (intake): at most 10 matches with their pets.
     */
    public function search(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $term = $request->string('q')->trim()->value();
        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $canSeePets = Gate::allows('viewAny', Pet::class);
        $customers = CustomerSearch::apply(Customer::query(), $term)
            ->select(['id', 'public_id', 'name', 'phone'])
            ->withCount('pets')
            ->when($canSeePets, fn (Builder $q) => $q->with(['pets' => fn ($p) => $p->with('species:id,name')->orderBy('name')]))
            ->orderBy('name')->limit(10)->get();

        return response()->json(['data' => $customers->map(fn (Customer $c): array => [
            'id' => $c->public_id,
            'name' => $c->name,
            'phone' => Phone::format($c->phone),
            'pets_count' => (int) $c->getAttribute('pets_count'),
            'pets' => $canSeePets ? $c->pets->map(fn (Pet $p): array => [
                'id' => $p->public_id,
                'name' => $p->name,
                'species' => $p->species->name,
                'size' => $p->size->value,
                'alerts' => PetSummary::alerts($p),
            ])->values()->all() : [],
        ])->values()]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('App/Customers/Form', ['customer' => null]);
    }

    public function store(Request $request, SaveCustomer $save): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Customer::class);

        [$customer, $restored] = $save->handle(null, $this->validated($request));

        if ($request->expectsJson()) { // quick create from S2
            return response()->json(['data' => ['id' => $customer->public_id, 'name' => $customer->name, 'phone' => Phone::format($customer->phone), 'pets_count' => 0, 'pets' => [], 'restored' => $restored]], 201);
        }

        return to_route('app.customers.show', $customer)
            ->with('status', $restored ? 'SĐT này từng có hồ sơ đã xóa: đã khôi phục và cập nhật hồ sơ đó.' : 'Đã thêm khách '.$customer->name.'.');
    }

    public function show(Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        $canSeePets = Gate::allows('viewAny', Pet::class);
        $pets = $canSeePets
            ? $customer->pets()->with(['species:id,name', 'breed:id,name'])->orderBy('name')->get()
            : collect();

        $appointments = Gate::allows('viewAny', Appointment::class)
            ? Appointment::query()->where('customer_id', $customer->id)->with('branch:id,name')->withCount('items')
                ->latest('starts_at')->limit(20)->get()
            : collect();

        $invoices = Gate::allows('viewAny', Invoice::class)
            ? Invoice::query()->where('customer_id', $customer->id)->with('branch:id,name')
                ->latest('id')->limit(20)->get()
            : collect();

        return Inertia::render('App/Customers/Show', [
            'customer' => [
                'id' => $customer->public_id,
                'name' => $customer->name,
                'phone' => Phone::format($customer->phone),
                'email' => $customer->email,
                'address' => $customer->address,
                'notes' => $customer->notes,
                'is_system' => $customer->is_system,
            ],
            'pets' => $pets->map(fn (Pet $p): array => PetSummary::make($p))->values()->all(),
            'appointments' => $appointments->map(fn (Appointment $a): array => [
                'id' => $a->public_id,
                'starts_at' => $a->starts_at->toIso8601String(),
                'branch' => $a->branch->name,
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'items_count' => (int) $a->getAttribute('items_count'),
            ])->values()->all(),
            'invoices' => $invoices->map(fn (Invoice $i): array => [
                'id' => $i->public_id,
                'code' => $i->code,
                'business_date' => $i->business_date->toDateString(),
                'branch' => $i->branch->name,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'total' => (string) $i->total,
            ])->values()->all(),
            'timezone' => tenant()?->timezone,
            'can' => [
                'update' => Gate::allows('update', $customer),
                'delete' => Gate::allows('delete', $customer),
                'viewPets' => $canSeePets,
                'createPet' => Gate::allows('create', Pet::class),
                'viewAppointments' => Gate::allows('viewAny', Appointment::class),
                'viewInvoices' => Gate::allows('viewAny', Invoice::class),
            ],
        ]);
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        return Inertia::render('App/Customers/Form', ['customer' => [
            'id' => $customer->public_id,
            'name' => $customer->name,
            'phone' => Phone::format($customer->phone),
            'email' => $customer->email,
            'address' => $customer->address,
            'notes' => $customer->notes,
        ]]);
    }

    public function update(Request $request, Customer $customer, SaveCustomer $save): RedirectResponse
    {
        Gate::authorize('update', $customer);

        $save->handle($customer, $this->validated($request));

        return to_route('app.customers.show', $customer)->with('status', 'Đã lưu hồ sơ khách.');
    }

    public function destroy(Customer $customer, DeleteCustomer $delete): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $delete->handle($customer);

        return to_route('app.customers.index')->with('status', 'Đã xóa hồ sơ '.$customer->name.'.');
    }

    /**
     * @return array{name: string, phone: string, email: string|null, address: string|null, notes: string|null}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['required', 'string', 'max:30', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Phone::isValid(is_string($value) ? $value : null)) {
                    $fail(Phone::INVALID);
                }
            }],
            'email' => ['nullable', 'email', 'max:191'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'name.required' => 'Nhập tên khách.',
            'phone.required' => 'Nhập số điện thoại.',
        ]);

        return [
            'name' => (string) $data['name'],
            'phone' => (string) $data['phone'],
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'address' => isset($data['address']) ? (string) $data['address'] : null,
            'notes' => isset($data['notes']) ? (string) $data['notes'] : null,
        ];
    }
}

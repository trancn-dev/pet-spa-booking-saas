<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Branches\AddBranch;
use App\Actions\Branches\CreateBranch;
use App\Actions\Branches\DeleteBranch;
use App\Actions\Branches\UpdateBranch;
use App\Authorization\CurrentBranch;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S12.2 Cài đặt › Cơ sở (`branches.manage`) and the topbar branch switcher.
 */
class BranchController extends Controller
{
    public function index(TenantContext $context): Response
    {
        Gate::authorize('viewAny', Branch::class);

        $tenant = $context->require()->loadMissing('plan');
        $branches = Branch::query()->orderBy('code')->get();

        return Inertia::render('App/Settings/Branches/Index', [
            'branches' => $branches->map(fn (Branch $branch): array => [
                'id' => $branch->public_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_active' => $branch->is_active,
            ])->values(),
            'limit' => ['used' => $branches->count(), 'max' => $tenant->plan?->max_branches],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Branch::class);

        return Inertia::render('App/Settings/Branches/Edit', [
            'branch' => null,
            'defaults' => ['opening_hours' => CreateBranch::defaultHours()],
        ]);
    }

    public function store(Request $request, AddBranch $add): RedirectResponse
    {
        Gate::authorize('create', Branch::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/', TenantRule::unique('branches', 'code')],
            ...$this->rules(),
        ], $this->messages());

        $branch = $add->handle([
            'code' => strtoupper((string) $data['code']),
            'name' => (string) $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'opening_hours' => $this->hours($data['opening_hours']),
        ]);

        return to_route('app.settings.branches')->with('status', 'Đã thêm cơ sở '.$branch->name.'.');
    }

    public function edit(Branch $branch): Response
    {
        Gate::authorize('update', $branch);

        return Inertia::render('App/Settings/Branches/Edit', [
            'branch' => [
                'id' => $branch->public_id,
                'code' => $branch->code,
                'name' => $branch->name,
                'address' => $branch->address,
                'phone' => $branch->phone,
                'is_active' => $branch->is_active,
                'opening_hours' => $branch->opening_hours ?? CreateBranch::defaultHours(),
            ],
            'defaults' => ['opening_hours' => CreateBranch::defaultHours()],
        ]);
    }

    public function update(Request $request, Branch $branch, UpdateBranch $update): RedirectResponse
    {
        Gate::authorize('update', $branch);

        $data = $request->validate([...$this->rules(), 'is_active' => ['required', 'boolean']], $this->messages());

        $update->handle($branch, [
            'name' => (string) $data['name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'opening_hours' => $this->hours($data['opening_hours']),
            'is_active' => (bool) $data['is_active'],
        ]);

        return to_route('app.settings.branches')->with('status', 'Đã lưu cơ sở '.$branch->name.'.');
    }

    public function destroy(Branch $branch, DeleteBranch $delete): RedirectResponse
    {
        Gate::authorize('delete', $branch);

        $delete->handle($branch);

        return to_route('app.settings.branches')->with('status', 'Đã xóa cơ sở '.$branch->name.'.');
    }

    /**
     * Topbar "Cơ sở ▾": only branches the person works at (or all with `branches.all`).
     */
    public function switch(Branch $branch): RedirectResponse
    {
        CurrentBranch::select($branch);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            // ISO weekday "1".."7" => list of [open, close] ranges; an empty list = closed that day.
            'opening_hours' => ['required', 'array:1,2,3,4,5,6,7'],
            'opening_hours.*' => ['present', 'array', 'max:3'],
            'opening_hours.*.*' => ['array', 'size:2'],
            'opening_hours.*.*.0' => ['required', 'date_format:H:i'],
            'opening_hours.*.*.1' => ['required', 'date_format:H:i', 'after:opening_hours.*.*.0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.regex' => 'Mã cơ sở chỉ gồm chữ và số (vd Q3).',
            'code.unique' => 'Mã cơ sở đã có.',
            'opening_hours.*.*.1.after' => 'Giờ đóng cửa phải sau giờ mở cửa.',
        ];
    }

    /**
     * @return array<int|string, list<array{0: string, 1: string}>>
     */
    private function hours(mixed $input): array
    {
        $hours = [];
        foreach (range(1, 7) as $day) {
            $ranges = is_array($input) ? ($input[$day] ?? $input[(string) $day] ?? []) : [];
            $hours[(string) $day] = array_values(array_map(
                fn (array $range): array => [(string) $range[0], (string) $range[1]],
                is_array($ranges) ? array_filter($ranges, 'is_array') : [],
            ));
        }

        return $hours;
    }
}

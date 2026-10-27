<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\SaveRole;
use App\Authorization\PermissionCatalog;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S12.3 Cài đặt › Phân quyền (owner only, `roles.manage`).
 */
class RoleController extends Controller
{
    public function index(TenantContext $context): Response
    {
        Gate::authorize('viewAny', Role::class);

        $tenantId = $context->require()->getKey();
        $members = DB::table('model_has_roles')->where('team_id', $tenantId)
            ->selectRaw('role_id, COUNT(*) AS c')->groupBy('role_id')->pluck('c', 'role_id');

        $roles = Role::query()->where('team_id', $tenantId)->with('permissions:id,name')->orderBy('id')->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'is_owner' => $role->isOwner(),
                'is_template' => in_array($role->name, Role::TEMPLATES, true),
                'members_count' => (int) ($members[$role->id] ?? 0),
                'permissions' => $role->permissions->pluck('name')->values()->all(),
            ]);

        $catalog = collect(PermissionCatalog::modules())->map(fn (string $label, string $module): array => [
            'key' => $module,
            'label' => $label,
            'permissions' => collect(PermissionCatalog::all())
                ->filter(fn (array $p): bool => $p['module'] === $module)
                ->map(fn (array $p, string $code): array => [
                    'code' => $code,
                    'label' => $p['label'],
                    'description' => $p['description'] ?? null,
                    'sensitive' => (bool) ($p['sensitive'] ?? false),
                    'assignable' => (bool) ($p['assignable'] ?? true),
                    'phase' => $p['phase'] ?? 'MVP',
                ])->values()->all(),
        ])->values()->all();

        return Inertia::render('App/Settings/Roles', ['roles' => $roles, 'catalog' => $catalog]);
    }

    public function store(Request $request, SaveRole $save): RedirectResponse
    {
        Gate::authorize('create', Role::class);
        $data = $this->validated($request);

        $role = $save->handle(null, $data['display_name'], $data['permissions'], $data['confirm_sensitive']);

        return to_route('app.settings.roles', ['role' => $role->id])->with('status', 'Đã tạo role '.$role->display_name.'.');
    }

    public function update(Request $request, Role $role, SaveRole $save): RedirectResponse
    {
        Gate::authorize('update', $role);
        $data = $this->validated($request);

        $save->handle($role, $data['display_name'], $data['permissions'], $data['confirm_sensitive']);

        return back()->with('status', 'Đã lưu role '.$data['display_name'].'.');
    }

    public function destroy(Role $role, DeleteRole $delete): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $delete->handle($role);

        return to_route('app.settings.roles')->with('status', 'Đã xóa role.');
    }

    /**
     * @return array{display_name: string, permissions: list<string>, confirm_sensitive: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:50'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
            'confirm_sensitive' => ['sometimes', 'boolean'],
        ]);

        return [
            'display_name' => trim((string) $data['display_name']),
            'permissions' => array_values(array_map('strval', (array) $data['permissions'])),
            'confirm_sensitive' => (bool) ($data['confirm_sensitive'] ?? false),
        ];
    }
}

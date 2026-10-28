<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Authorization\PermissionCatalog;
use App\Models\Role;
use App\Support\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S12.3: create a role or change a role's display name and permissions (db-schema 3.1).
 * - `owner` cannot be edited; `roles.manage` can never be granted to another role;
 * - granting a sensitive permission needs an explicit confirmation and is written to activity_logs;
 * - `name` never changes (custom roles get `custom_{id}`), only `display_name`.
 */
class SaveRole
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  list<string>  $permissions
     */
    public function handle(?Role $role, string $displayName, array $permissions, bool $sensitiveConfirmed = false): Role
    {
        if ($role?->isOwner()) {
            throw ValidationException::withMessages(['role' => 'Role Chủ shop không sửa được.']);
        }

        $permissions = array_values(array_unique($permissions));
        $this->validate($permissions);

        $before = $role === null ? [] : $role->permissions()->pluck('name')->all();
        $newSensitive = array_values(array_filter(
            array_diff($permissions, $before),
            fn (string $code): bool => PermissionCatalog::isSensitive($code),
        ));

        if ($newSensitive !== [] && ! $sensitiveConfirmed) {
            throw ValidationException::withMessages([
                'permissions' => 'Cần xác nhận khi giao quyền nhạy cảm: '.implode(', ', $newSensitive).'.',
            ]);
        }

        return DB::transaction(function () use ($role, $displayName, $permissions, $before, $newSensitive): Role {
            $tenantId = $this->context->require()->getKey();

            if ($role === null) {
                // Stable code from the id: insert with a placeholder, then rename.
                $role = Role::query()->create([
                    'team_id' => $tenantId,
                    'name' => 'custom_pending_'.bin2hex(random_bytes(6)),
                    'display_name' => $displayName,
                    'guard_name' => PermissionCatalog::GUARD,
                ]);
                $role->forceFill(['name' => 'custom_'.$role->id])->save();
                ActivityLog::record('role.created', $role, ['display_name' => $displayName]);
            } elseif ($role->display_name !== $displayName) {
                ActivityLog::record('role.renamed', $role, ['display_name' => [$role->display_name, $displayName]]);
                $role->forceFill(['display_name' => $displayName])->save();
            }

            $role->syncPermissions($permissions);

            $added = array_values(array_diff($permissions, $before));
            $removed = array_values(array_diff($before, $permissions));
            if ($added !== [] || $removed !== []) {
                ActivityLog::record('role.permissions_changed', $role, ['added' => $added, 'removed' => $removed]);
            }
            foreach ($newSensitive as $code) {
                ActivityLog::record('role.sensitive_granted', $role, ['permission' => $code]);
            }

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    private function validate(array $permissions): void
    {
        $unknown = array_values(array_filter($permissions, fn (string $code): bool => ! PermissionCatalog::exists($code)));
        if ($unknown !== []) {
            throw ValidationException::withMessages(['permissions' => 'Quyền không tồn tại: '.implode(', ', $unknown).'.']);
        }

        $locked = array_values(array_filter($permissions, fn (string $code): bool => ! PermissionCatalog::isAssignable($code)));
        if ($locked !== []) {
            throw ValidationException::withMessages(['permissions' => 'Chỉ Chủ shop có quyền: '.implode(', ', $locked).'.']);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Staff;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Support\ActivityLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * S11.1 staff profile: create a person WITHOUT an account (guard, cleaner: no invitation, not in
 * `max_staff`) or edit anyone. Roles only for account holders; `owner` is never given or taken
 * here. The professional block (license, qualifications) is kept only with the `vet` role.
 */
class SaveStaff
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @param  array{display_name: string, phone?: string|null, title?: string|null, color?: string|null, license_no?: string|null, qualifications?: string|null, roles?: list<string>, primary_branch_id: int, branch_ids?: list<int>}  $data
     */
    public function handle(?Staff $staff, array $data): Staff
    {
        return DB::transaction(function () use ($staff, $data): Staff {
            $user = $staff?->user_id === null ? null : User::query()->find($staff->user_id);
            [$primary, $extra] = $this->branches($data['primary_branch_id'], $data['branch_ids'] ?? []);
            $roles = $user === null ? [] : $this->roles($user, $data['roles'] ?? []);
            $isVet = in_array('vet', $roles, true);

            $attributes = [
                'display_name' => trim($data['display_name']),
                'phone' => $data['phone'] ?? null,
                'title' => $data['title'] ?? null,
                'color' => $data['color'] ?? null,
                'license_no' => $isVet ? ($data['license_no'] ?? null) : null,
                'qualifications' => $isVet ? ($data['qualifications'] ?? null) : null,
                'primary_branch_id' => $primary,
            ];

            if ($staff === null) {
                $staff = Staff::query()->create([...$attributes, 'user_id' => null]);
                ActivityLog::record('staff.created', $staff, ['display_name' => $staff->display_name, 'account' => false]);
            } else {
                $staff->fill($attributes);
                $changes = $staff->getDirty();
                $staff->save();
                if ($changes !== []) {
                    ActivityLog::record('staff.updated', $staff, $changes);
                }
            }

            $pivot = [$primary => ['tenant_id' => $staff->tenant_id, 'is_primary' => true]];
            foreach ($extra as $id) {
                $pivot[$id] = ['tenant_id' => $staff->tenant_id, 'is_primary' => false];
            }
            $staff->branches()->sync($pivot);

            if ($user !== null) {
                $before = $user->getRoleNames()->sort()->values()->all();
                $user->syncRoles($roles);
                $user->unsetRelation('roles')->unsetRelation('permissions');
                $after = collect($roles)->sort()->values()->all();
                if ($before !== $after) {
                    ActivityLog::record('staff.roles_changed', $staff, ['roles' => [$before, $after]]);
                }
            }

            return $staff;
        });
    }

    /**
     * @param  list<int>  $extra
     * @return array{0: int, 1: list<int>}
     */
    private function branches(int $primary, array $extra): array
    {
        $active = Branch::query()->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $extra = array_values(array_diff(array_unique(array_map('intval', $extra)), [$primary]));

        if (! in_array($primary, $active, true)) {
            throw ValidationException::withMessages(['primary_branch' => 'Chọn cơ sở chính đang hoạt động.']);
        }
        if (array_diff($extra, $active) !== []) {
            throw ValidationException::withMessages(['branches' => 'Cơ sở phụ không hợp lệ.']);
        }

        return [$primary, $extra];
    }

    /**
     * @param  list<string>  $wanted
     * @return list<string>
     */
    private function roles(User $user, array $wanted): array
    {
        $wanted = array_values(array_diff(array_unique($wanted), [Role::OWNER]));
        $known = Role::query()->where('team_id', $this->context->require()->getKey())->whereIn('name', $wanted)->pluck('name')->all();

        if (count($known) !== count($wanted)) {
            throw ValidationException::withMessages(['roles' => 'Role không tồn tại.']);
        }

        // The owner role stays as it is (only the platform gives it).
        $isOwner = $user->hasRole(Role::OWNER);
        if ($wanted === [] && ! $isOwner) {
            throw ValidationException::withMessages(['roles' => 'Chọn ít nhất một role.']);
        }

        return $isOwner ? [Role::OWNER, ...$wanted] : $wanted;
    }
}

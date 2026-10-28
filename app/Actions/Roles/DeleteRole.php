<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Models\Role;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deleting a role: never `owner`, never while someone still holds it.
 */
class DeleteRole
{
    public function handle(Role $role): void
    {
        if ($role->isOwner()) {
            throw ValidationException::withMessages(['role' => 'Role Chủ shop không xóa được.']);
        }

        $holders = DB::table('model_has_roles')->where('role_id', $role->id)->where('team_id', $role->team_id)->count();
        if ($holders > 0) {
            throw ValidationException::withMessages(['role' => "Còn {$holders} người đang có role này. Gỡ role khỏi họ trước."]);
        }

        DB::transaction(function () use ($role): void {
            ActivityLog::record('role.deleted', $role, ['name' => $role->name, 'display_name' => $role->display_name]);
            $role->delete();
        });
    }
}

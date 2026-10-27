<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Authorization\PermissionCatalog;
use App\Models\Role;
use App\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * The 7 template roles of a new shop (design.md section 8). Called by ProvisionTenant (task 4.3);
 * idempotent, so it is safe to call again for an existing shop.
 */
class CreateDefaultRoles
{
    public function handle(Tenant $tenant): void
    {
        $registrar = app(PermissionRegistrar::class);
        $previousTeam = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($tenant->getKey());

        try {
            foreach (PermissionCatalog::templates() as $name => $template) {
                $role = Role::query()->firstOrCreate(
                    ['team_id' => $tenant->getKey(), 'name' => $name, 'guard_name' => PermissionCatalog::GUARD],
                    ['display_name' => $template['display_name']],
                );

                if ($role->wasRecentlyCreated) {
                    $role->syncPermissions($template['permissions']);
                }
            }
        } finally {
            $registrar->setPermissionsTeamId($previousTeam);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Read access to config/permissions.php (ADR 0009) and its sync to the database.
 */
final class PermissionCatalog
{
    public const GUARD = 'web';

    /**
     * @return array<string, array{module: string, label: string, description?: string, sensitive?: bool, assignable?: bool, owner?: bool, phase?: string}>
     */
    public static function all(): array
    {
        /** @var array<string, array{module: string, label: string, description?: string, sensitive?: bool, assignable?: bool, owner?: bool, phase?: string}> */
        return config('permissions.permissions', []);
    }

    /**
     * @return array<string, string>
     */
    public static function modules(): array
    {
        /** @var array<string, string> */
        return config('permissions.modules', []);
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $code): bool
    {
        return array_key_exists($code, self::all());
    }

    public static function isSensitive(string $code): bool
    {
        return (bool) (self::all()[$code]['sensitive'] ?? false);
    }

    /**
     * False = only the owner role may ever hold it (`roles.manage`).
     */
    public static function isAssignable(string $code): bool
    {
        return (bool) (self::all()[$code]['assignable'] ?? true);
    }

    /**
     * @return list<string>
     */
    public static function ownerPermissions(): array
    {
        return array_keys(array_filter(self::all(), fn (array $p): bool => $p['owner'] ?? true));
    }

    /**
     * @return array<string, array{display_name: string, permissions: list<string>}>
     */
    public static function templates(): array
    {
        /** @var array<string, array{display_name: string, permissions: list<string>|string}> $roles */
        $roles = config('permissions.roles', []);

        return array_map(fn (array $role): array => [
            'display_name' => $role['display_name'],
            'permissions' => $role['permissions'] === '*' ? self::ownerPermissions() : $role['permissions'],
        ], $roles);
    }

    /**
     * Makes the `permissions` table match the catalogue. New permissions go to every shop's owner
     * role (and nowhere else); permissions removed from the catalogue are deleted.
     *
     * @return array{created: list<string>, deleted: list<string>}
     */
    public static function sync(): array
    {
        $codes = self::codes();
        $existing = Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all();

        $created = array_values(array_diff($codes, $existing));
        $deleted = array_values(array_diff($existing, $codes));

        DB::transaction(function () use ($created, $deleted): void {
            foreach ($created as $code) {
                Permission::query()->create(['name' => $code, 'guard_name' => self::GUARD]);
            }

            Permission::query()->where('guard_name', self::GUARD)->whereIn('name', $deleted)->delete();

            $forOwner = array_values(array_intersect($created, self::ownerPermissions()));
            if ($forOwner !== []) {
                $ids = Permission::query()->whereIn('name', $forOwner)->pluck('id');
                Role::query()->where('name', Role::OWNER)->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching($ids));
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['created' => $created, 'deleted' => $deleted];
    }
}

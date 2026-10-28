<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A shop's role (spatie teams mode, team_id = tenant_id). `name` is the stable code used by code
 * (`owner`, `vet`, …, `custom_{id}`); `display_name` is the label the shop may rename.
 *
 * @property int $team_id
 * @property string $name
 * @property string $display_name
 */
class Role extends SpatieRole
{
    public const OWNER = 'owner';

    public const TEMPLATES = ['owner', 'branch_manager', 'receptionist', 'cashier', 'vet', 'groomer', 'inventory'];

    public function isOwner(): bool
    {
        return $this->name === self::OWNER;
    }

    /**
     * `{role}` in a shop route only ever resolves to a role of the current shop.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return static::query()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('team_id', app(TenantContext::class)->require()->getKey())
            ->first();
    }
}

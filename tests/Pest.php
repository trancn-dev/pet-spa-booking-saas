<?php

declare(strict_types=1);

use App\Actions\Roles\CreateDefaultRoles;
use App\Authorization\PermissionCatalog;
use App\Models\Branch;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActsOnTenant;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Arch');
pest()->extend(TestCase::class)->in('Unit');
pest()->use(ActsOnTenant::class)->in('Feature/ExampleTest.php');

/**
 * Run a callback as the given tenant (factories for tenant-owned models need a tenant context).
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function inTenant(Tenant $tenant, Closure $callback): mixed
{
    return app(TenantContext::class)->runAs($tenant, $callback);
}

/**
 * A shop with the permission catalogue synced and its 7 template roles.
 *
 * @param  array<string, mixed>  $attributes
 */
function shopWithRoles(array $attributes = []): Tenant
{
    PermissionCatalog::sync();
    $tenant = Tenant::factory()->create($attributes);
    app(CreateDefaultRoles::class)->handle($tenant);

    return $tenant;
}

/**
 * A user who is an active staff member of the shop, working at the given branches (the first
 * is primary), holding the given role codes there.
 *
 * @param  list<string>  $roles
 * @param  list<Branch>  $branches
 */
function memberWithRoles(Tenant $tenant, array $roles, array $branches = []): User
{
    return memberAt($tenant, User::factory()->create(), $roles, $branches);
}

/**
 * Makes an existing user a staff member of another shop too.
 *
 * @param  list<string>  $roles
 * @param  list<Branch>  $branches
 */
function memberAt(Tenant $tenant, User $user, array $roles, array $branches = []): User
{
    inTenant($tenant, function () use ($user, $branches): void {
        $branches = $branches === [] ? [Branch::factory()->create()] : $branches;
        $staff = Staff::factory()->create(['user_id' => $user->id, 'primary_branch_id' => $branches[0]->id]);
        foreach (array_slice($branches, 1) as $branch) {
            $staff->branches()->attach($branch->id, ['tenant_id' => $tenant->id, 'is_primary' => false]);
        }
    });

    asTeam($tenant, fn () => $user->assignRole($roles));

    return $user;
}

/**
 * Run as the shop's permission team (what ResolveTenant does per request).
 *
 * @template TReturn
 *
 * @param  Closure(): TReturn  $callback
 * @return TReturn
 */
function asTeam(Tenant $tenant, Closure $callback): mixed
{
    $registrar = app(PermissionRegistrar::class);
    $previous = $registrar->getPermissionsTeamId();
    $registrar->setPermissionsTeamId($tenant->id);

    try {
        return $callback();
    } finally {
        $registrar->setPermissionsTeamId($previous);
    }
}

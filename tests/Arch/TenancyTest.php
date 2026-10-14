<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\BelongsToTenant;
use Illuminate\Support\Facades\Schema;

/*
| MANDATORY: every model whose table has a tenant_id column must use BelongsToTenant,
| so a new feature model can never ship unscoped.
*/
it('applies BelongsToTenant to every model with a tenant_id column', function () {

    $unscoped = collect(glob(app_path('Models/*.php')) ?: [])
        ->map(fn (string $f) => 'App\\Models\\'.basename($f, '.php'))
        ->reject(fn (string $c) => $c === Tenant::class)
        ->filter(fn (string $c) => Schema::hasColumn((new $c)->getTable(), 'tenant_id'))
        ->reject(fn (string $c) => in_array(BelongsToTenant::class, class_uses_recursive($c), true))
        ->values()->all();

    expect($unscoped)->toBe([]);
});

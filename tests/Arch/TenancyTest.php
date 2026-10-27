<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/*
| MANDATORY: every model whose table has a tenant_id column must use BelongsToTenant,
| so a new feature model can never ship unscoped.
|
| Exception, on purpose: App\Models\Platform\* is the platform's data *about* shops
| (subscriptions, impersonation, platform audit). It is only read from /admin and must
| never be filtered by the current shop (db-schema 2.1).
*/
it('applies BelongsToTenant to every model with a tenant_id column', function () {
    $unscoped = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Models'))))
        ->filter(fn (SplFileInfo $f) => $f->isFile() && $f->getExtension() === 'php')
        ->map(fn (SplFileInfo $f) => 'App\\Models\\'.str_replace(['/', '.php'], ['\\', ''], substr($f->getPathname(), strlen(app_path('Models')) + 1)))
        ->filter(fn (string $c) => class_exists($c) && is_subclass_of($c, Model::class) && ! (new ReflectionClass($c))->isAbstract())
        ->reject(fn (string $c) => $c === Tenant::class || str_starts_with($c, 'App\\Models\\Platform\\'))
        ->filter(fn (string $c) => Schema::hasColumn((new $c)->getTable(), 'tenant_id'))
        ->reject(fn (string $c) => in_array(BelongsToTenant::class, class_uses_recursive($c), true))
        ->values()->all();

    expect($unscoped)->toBe([]);
});

/*
| Global scopes are only ever bypassed through TenantContext::withoutTenancy(), never ad hoc.
*/
it('never calls withoutGlobalScope(s) outside the whitelist', function () {
    $whitelist = [];

    $offenders = collect(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())))
        ->filter(fn (SplFileInfo $f) => $f->isFile() && $f->getExtension() === 'php')
        ->filter(fn (SplFileInfo $f) => preg_match('/withoutGlobalScopes?\s*\(/', (string) file_get_contents($f->getPathname())) === 1)
        ->map(fn (SplFileInfo $f) => str_replace(base_path().'/', '', $f->getPathname()))
        ->reject(fn (string $path) => in_array($path, $whitelist, true))
        ->values()->all();

    expect($offenders)->toBe([]);
});

arch('no debugging helpers')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('strict types everywhere in app/')
    ->expect('App')
    ->toUseStrictTypes();

arch('models are Eloquent models')
    ->expect('App\Models')
    ->classes()
    ->toExtend(Model::class);

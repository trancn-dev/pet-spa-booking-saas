<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use Closure;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The tenant itself is intentionally NOT tenant-scoped.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected $fillable = ['name', 'slug', 'timezone', 'locale', 'currency', 'status'];

    /**
     * Run a callback once per tenant, each inside its own tenant context (scheduler, backfills).
     *
     * @param  Closure(Tenant): mixed  $callback
     */
    public static function forEach(Closure $callback): void
    {
        $context = app(TenantContext::class);

        static::query()->orderBy('id')->each(
            fn (Tenant $tenant) => $context->runAs($tenant, fn () => $callback($tenant))
        );
    }
}

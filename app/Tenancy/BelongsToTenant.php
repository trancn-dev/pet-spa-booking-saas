<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Add to every model whose table has a tenant_id column.
 * (tests/Arch enforces this.)
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            $context = app(TenantContext::class);
            $current = $context->id();

            if ($model->getAttribute('tenant_id') === null) {
                if ($current === null) {
                    throw TenantNotResolvedException::forModel($model::class);
                }
                $model->setAttribute('tenant_id', $current);

                return;
            }

            if ($current !== null && ! $context->isBypassed() && (string) $model->getAttribute('tenant_id') !== (string) $current) {
                throw new LogicException('Cannot create a record for a different tenant than the current one.');
            }
        });

        static::updating(function ($model): void {
            if ($model->isDirty('tenant_id')) {
                throw new LogicException('tenant_id is immutable.');
            }
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}

<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Add to every model whose table has a tenant_id column (tests/Arch enforces this).
 *
 * Fails closed on reads (TenantScope) and on writes:
 * - inside a tenant context: tenant_id is stamped from the context; another tenant's id is rejected;
 * - inside withoutTenancy(): tenant_id must be given explicitly;
 * - with no context at all: creating throws, even with an explicit tenant_id.
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);
            $current = $context->id();
            $given = $model->getAttribute('tenant_id');

            if ($context->isBypassed()) {
                if ($given === null) {
                    throw new LogicException('withoutTenancy(): tenant_id must be set explicitly when creating ['.$model::class.'].');
                }

                return;
            }

            if ($current === null) {
                throw TenantNotResolvedException::forModel($model::class);
            }

            if ($given === null) {
                $model->setAttribute('tenant_id', $current);

                return;
            }

            if ((string) $given !== (string) $current) {
                throw new LogicException('Cannot create a record for a different tenant than the current one.');
            }
        });

        static::updating(function (Model $model): void {
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

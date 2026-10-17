<?php

declare(strict_types=1);

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Fails closed: with no tenant in context (and no explicit bypass) a query throws
 * instead of silently returning every tenant's rows.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->isBypassed()) {
            return;
        }

        $id = $context->id();

        if ($id === null) {
            throw TenantNotResolvedException::forModel($model::class);
        }

        $builder->where($model->qualifyColumn('tenant_id'), $id);
    }
}

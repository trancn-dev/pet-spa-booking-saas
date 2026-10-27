<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenancy\StaffMembership;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantMembership;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(TenantContext::class);
        $this->app->bind(TenantMembership::class, StaffMembership::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lazy loading, silently discarded attributes and missing attributes throw outside production.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Every queued payload carries the dispatching tenant; TenantAware jobs restore it.
        Queue::createPayloadUsing(fn (): array => [
            'tenant_id' => $this->app->make(TenantContext::class)->id(),
        ]);
    }
}

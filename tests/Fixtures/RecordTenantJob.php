<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Tenancy\Jobs\TenantAware;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Records which tenant was current while the job ran.
 */
class RecordTenantJob implements ShouldQueue
{
    use Queueable, TenantAware;

    public static ?int $seenTenantId = null;

    public function handle(TenantContext $context): void
    {
        self::$seenTenantId = $context->id();
    }
}

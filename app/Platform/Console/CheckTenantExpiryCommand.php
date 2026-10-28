<?php

declare(strict_types=1);

namespace App\Platform\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Mail\PlanExpiringMail;
use App\Platform\TenantStatusChange;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Daily plan lifecycle (db-schema 2.1, F20):
 * - trial / active ending within N days → reminder mail to the owners, once per shop + expiry date;
 * - expired → past_due (grace, fully usable);
 * - past due longer than the grace period → suspended (reason "Hết hạn sử dụng").
 * Every change is compare-and-set, so an admin acting at the same moment wins or loses cleanly.
 */
class CheckTenantExpiryCommand extends Command
{
    protected $signature = 'tenants:check-expiry';

    protected $description = 'Remind, mark past due and suspend shops whose plan has expired';

    public function handle(): int
    {
        $reminded = $pastDue = $suspended = 0;
        $warnUntil = now()->addDays((int) config('platform.expiry_warning_days'));

        Tenant::query()
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value])
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<=', $warnUntil)
            ->orderBy('id')
            ->each(function (Tenant $tenant) use (&$reminded, &$pastDue): void {
                if ($tenant->plan_expires_at?->isPast()) {
                    $pastDue += (int) TenantStatusChange::apply($tenant, $tenant->status, TenantStatus::PastDue, 'tenant.past_due');
                } else {
                    $reminded += (int) $this->remind($tenant);
                }
            });

        Tenant::query()
            ->where('status', TenantStatus::PastDue->value)
            ->where('plan_expires_at', '<=', now()->subDays((int) config('platform.grace_days')))
            ->orderBy('id')
            ->each(function (Tenant $tenant) use (&$suspended): void {
                $suspended += (int) TenantStatusChange::apply($tenant, TenantStatus::PastDue, TenantStatus::Suspended, 'tenant.suspended', null, [
                    'suspended_at' => now(),
                    'suspended_reason' => TenantStatusChange::EXPIRED_REASON,
                ], ['reason' => TenantStatusChange::EXPIRED_REASON]);
            });

        $this->info("Reminded {$reminded}, past due {$pastDue}, suspended {$suspended}.");

        return self::SUCCESS;
    }

    private function remind(Tenant $tenant): bool
    {
        $expires = $tenant->plan_expires_at?->copy()->timezone($tenant->timezone);
        if ($expires === null) {
            return false;
        }

        // Once per shop and expiry date: an extension moves the date and re-arms the reminder.
        if (! Cache::add("plan-expiry-reminder:{$tenant->getKey()}:{$expires->toDateString()}", true, now()->addDays(60))) {
            return false;
        }

        $emails = $this->ownerEmails($tenant);
        if ($emails === []) {
            return false;
        }

        Mail::to($emails)->queue(new PlanExpiringMail(
            shopName: $tenant->name,
            expiresOn: $expires->format('d/m/Y'),
            daysLeft: max(1, (int) ceil(now()->diffInDays($expires, false))),
            trial: $tenant->status === TenantStatus::Trial,
        ));

        return true;
    }

    /**
     * Active staff holding the shop's `owner` role (plain query: this runs outside any shop).
     *
     * @return list<string>
     */
    private function ownerEmails(Tenant $tenant): array
    {
        return DB::table('users')
            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('staff', fn ($join) => $join->on('staff.user_id', '=', 'users.id')->on('staff.tenant_id', '=', 'model_has_roles.team_id'))
            ->where('model_has_roles.team_id', $tenant->getKey())
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', 'owner')
            ->where('staff.status', 'active')
            ->whereNull('staff.deleted_at')
            ->distinct()
            ->pluck('users.email')
            ->map(fn ($email): string => (string) $email)
            ->all();
    }
}

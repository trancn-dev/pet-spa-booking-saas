<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Authorization\CurrentBranch;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\OnlineBookingRate;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S5 Tổng quan. Until its widgets exist (task 9.1) it carries the owner's first-run checklist
 * (S13): shown while a setup step is still open.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'checklist' => $user instanceof User && $user->checkPermissionTo('settings.manage')
                ? $this->checklist($context)
                : null,
        ]);
    }

    /**
     * @return list<array{key: string, label: string, done: bool|null, href: string|null}>|null
     */
    private function checklist(TenantContext $context): ?array
    {
        $tenant = $context->require();

        $steps = [
            ['key' => 'branches', 'label' => 'Kiểm tra cơ sở và giờ mở cửa', 'done' => null, 'href' => route('app.settings.branches')],
            ['key' => 'services', 'label' => 'Thêm dịch vụ', 'done' => Service::query()->exists(), 'href' => route('app.services.index')],
            ['key' => 'rosters', 'label' => 'Cài lịch trực', 'done' => StaffWorkingHour::query()->exists(), 'href' => route('app.rosters.index')],
            ['key' => 'online', 'label' => 'Cài tỉ lệ đặt online', 'done' => OnlineBookingRate::query()->exists(), 'href' => ($branch = CurrentBranch::get()) ? route('app.settings.online-rates', $branch) : null],
            [
                'key' => 'staff',
                'label' => 'Mời nhân viên',
                'done' => Staff::query()->count() > 1 || Invitation::query()->whereNull('accepted_at')->exists(),
                'href' => route('app.staff.index'),
            ],
            ['key' => 'site', 'label' => 'Chia sẻ link Site: '.config('platform.site_host').'/'.$tenant->slug, 'done' => null, 'href' => null],
        ];

        // Steps without a done flag are reminders; the list goes away once every tracked step is done.
        $open = array_filter($steps, fn (array $step): bool => $step['done'] === false);

        return $open === [] || Branch::query()->doesntExist() ? null : $steps;
    }
}

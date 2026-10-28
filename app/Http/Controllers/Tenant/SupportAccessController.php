<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Platform\Http\Controllers\TenantController;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cài đặt › Lịch sử truy cập hỗ trợ (design S12): who from PawBook signed in as the owner, when and
 * why. Only this shop's sessions.
 */
class SupportAccessController extends Controller
{
    public function __invoke(Request $request, TenantContext $context): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->checkPermissionTo('settings.manage'), 403);

        return Inertia::render('App/Settings/SupportAccess', [
            'sessions' => TenantController::sessions($context->require()),
            'timezone' => $context->require()->timezone,
        ]);
    }
}

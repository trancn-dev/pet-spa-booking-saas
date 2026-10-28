<?php

declare(strict_types=1);

namespace App\Platform\Http\Middleware;

use App\Models\Platform\ImpersonationSession;
use App\Models\Tenant;
use App\Platform\Audit;
use App\Platform\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a platform admin acts as a shop owner (task 4.6):
 * - the CMS session ends at 30 minutes (or when ended from the banner / A4);
 * - only the supported shop can be opened;
 * - sensitive routes are refused (account, roles, staff, payroll, invoice corrections, deletions);
 * - every other write is recorded as `impersonation.write` in platform_audit_logs.
 */
class ImpersonationGuard
{
    /** Route names (wildcards allowed) never reachable while impersonating. */
    public const BLOCKED = [
        'profile.update', 'profile.destroy', 'password.update',
        'app.staff.invite', 'app.staff.status', 'app.staff.invitations.*', 'app.staff.update',
        'app.settings.roles.store', 'app.settings.roles.update', 'app.settings.roles.destroy',
        'app.payroll.*', 'app.invoices.amend', 'app.invoices.void*',
        'app.*.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(Impersonation::SESSION_KEY)) {
            return $next($request);
        }

        $session = Impersonation::current($request);

        if ($session === null || (int) Auth::guard('web')->id() !== $session->target_user_id) {
            return $this->finish($request, $session);
        }

        // Only the supported shop: another workspace of the owner is out of bounds.
        $slug = $this->slug($request);
        abort_if($slug !== null && $slug !== $session->tenant?->slug, 404);

        $route = (string) $request->route()?->getName();
        if ($route === 'impersonation.exit' || $route === 'logout') {
            return $next($request);
        }

        abort_if(Str::is(self::BLOCKED, $route), 403, 'Không thực hiện được trong phiên hỗ trợ.');

        if (! $request->isMethodSafe()) {
            Audit::record($session->admin, 'impersonation.write', null, [
                'method' => $request->method(),
                'route' => $route,
                'path' => '/'.$request->path(),
            ], $session->tenant_id, $session->getKey());
        }

        return $next($request);
    }

    private function finish(Request $request, ?ImpersonationSession $open): Response
    {
        $id = $request->session()->get(Impersonation::SESSION_KEY);
        $row = $open ?? ImpersonationSession::query()->find($id);
        if ($row !== null) {
            Impersonation::end($row, 'expired');
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Phiên hỗ trợ đã kết thúc.');
    }

    private function slug(Request $request): ?string
    {
        $param = $request->route('tenant');

        return match (true) {
            $param instanceof Tenant => $param->slug,
            is_string($param) => $param,
            default => tenant()?->slug,
        };
    }
}

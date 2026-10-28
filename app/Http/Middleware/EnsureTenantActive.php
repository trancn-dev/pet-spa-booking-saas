<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Suspended shop = read-only (db-schema 2.1, task 4.5): pages still open, every write gets 423 with a
 * notice. Runs right after ResolveTenant. Switching the topbar branch only touches the session.
 */
class EnsureTenantActive
{
    /** Writes that change nothing of the shop's data. */
    private const ALWAYS_ALLOWED = ['app.branch.switch'];

    public const MESSAGE = 'Shop đang tạm khóa: vẫn xem được dữ liệu nhưng không thao tác được. Liên hệ PawBook để gia hạn.';

    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->get();

        if ($tenant === null || ! $tenant->status->isReadOnly() || $request->isMethodSafe()
            || in_array($request->route()?->getName(), self::ALWAYS_ALLOWED, true)) {
            return $next($request);
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => self::MESSAGE], 423);
        }

        return Inertia::render('Errors/ShopSuspended', [
            'message' => self::MESSAGE,
            'reason' => $tenant->suspended_reason,
        ])->toResponse($request)->setStatusCode(423);
    }
}

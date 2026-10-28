<?php

declare(strict_types=1);

namespace App\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Platform\PlatformAuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A5 Nhật ký hoạt động: every platform audit row (append-only), newest first. Also the A4 "Nhật ký"
 * tab for one shop. Super-admin and support alike.
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $action = $request->string('action')->trim()->value();
        $search = $request->string('q')->trim()->value();

        $query = PlatformAuditLog::query()
            ->when($action !== '', fn (Builder $q) => $q->where('action', 'like', addcslashes($action, '%_\\').'%'))
            ->when($search !== '', fn (Builder $q) => $q->whereHas('tenant', function (Builder $t) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $t->where('name', 'like', $like)->orWhere('slug', 'like', $like);
            }));

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => self::rows($query),
            'filters' => ['action' => $action, 'q' => $search],
            'actions' => PlatformAuditLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /**
     * @param  Builder<PlatformAuditLog>  $query
     * @return LengthAwarePaginator<int, array{id: int, action: string, admin: string|null, tenant: array{id: string, name: string}|null, changes: array<string, mixed>|null, ip: string|null, created_at: string}>
     */
    public static function rows(Builder $query): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, PlatformAuditLog> $page */
        $page = $query->with(['admin:id,name', 'tenant:id,public_id,name,slug'])->latest('id')->paginate(30)->withQueryString();

        return $page->through(fn (PlatformAuditLog $log): array => [
            'id' => $log->id,
            'action' => $log->action,
            'admin' => $log->admin?->name,
            'tenant' => $log->tenant === null ? null : ['id' => $log->tenant->public_id, 'name' => $log->tenant->name],
            'changes' => $log->changes,
            'ip' => $log->ip,
            'created_at' => $log->created_at->toIso8601String(),
        ]);
    }
}

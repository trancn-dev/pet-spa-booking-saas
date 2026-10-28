<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Authorization\CurrentBranch;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Platform\Impersonation;
use App\Support\PlanNotice;
use App\Tenancy\TenantMembership;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => fn () => $request->user(),
            ],
            // Inside a shop (/app/{slug}): the shop and the user's permission codes there, so the
            // UI can hide / disable actions. The backend still checks every request (policies).
            'tenant' => fn (): ?array => ($tenant = tenant()) === null ? null : [
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ],
            'flash' => ['status' => fn () => $request->session()->get('status')],
            'permissions' => fn (): array => tenant() !== null && $request->user() instanceof User
                ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                : [],
            // Red banner with countdown + "Thoát" while a platform admin acts as the owner (task 4.6).
            'impersonating' => function () use ($request): ?array {
                $session = Impersonation::current($request);

                return $session === null ? null : [
                    'admin' => $session->admin?->name,
                    'shop' => $session->tenant?->name,
                    'expires_at' => $session->expires_at->toIso8601String(),
                    'blocked' => Impersonation::BLOCKED_HINT,
                ];
            },
            'planNotice' => fn (): ?array => ($tenant = tenant()) === null ? null : PlanNotice::for($tenant),
            'readOnly' => fn (): bool => tenant()?->status->isReadOnly() ?? false,
            // Topbar switchers (design 3.4): shops the user can open, branches they work at.
            'workspaces' => fn (): array => tenant() !== null && $request->user() instanceof User
                ? app(TenantMembership::class)->tenantsFor($request->user())
                    ->filter(fn (Tenant $t): bool => $t->status->isAccessible())
                    // While impersonating, only the supported shop.
                    ->filter(fn (Tenant $t): bool => ! $request->session()->has(Impersonation::SESSION_KEY) || $t->is(tenant()))
                    ->map(fn (Tenant $t): array => ['slug' => $t->slug, 'name' => $t->name])
                    ->values()->all()
                : [],
            'branches' => fn (): array => tenant() !== null
                ? CurrentBranch::available()->map(fn (Branch $b): array => self::branch($b))->values()->all()
                : [],
            'currentBranch' => fn (): ?array => tenant() !== null && ($branch = CurrentBranch::get()) !== null
                ? self::branch($branch)
                : null,
        ]);
    }

    /**
     * @return array{id: string, code: string, name: string}
     */
    private static function branch(Branch $branch): array
    {
        return ['id' => $branch->public_id, 'code' => $branch->code, 'name' => $branch->name];
    }
}

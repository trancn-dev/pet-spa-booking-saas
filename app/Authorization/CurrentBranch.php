<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Branch;
use App\Tenancy\CurrentStaff;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Branch scope (ADR 0004): branches are not an isolation boundary, so every action on a
 * branch's data checks here that the person works at that branch (or has `branches.all`).
 *
 * Also the branch picked in the topbar switcher, kept in the session per shop. It is only ever one
 * of `available()`; with no (valid) choice it falls back to the person's primary branch.
 */
final class CurrentBranch
{
    public static function allows(Branch|int|null $branch): bool
    {
        return app(CurrentStaff::class)->canAccessBranch($branch instanceof Branch ? $branch->id : $branch);
    }

    /**
     * @throws AuthorizationException
     */
    public static function authorize(Branch|int|null $branch): void
    {
        if (! self::allows($branch)) {
            throw new AuthorizationException('Bạn không làm việc ở cơ sở này.');
        }
    }

    /**
     * Active branches the person may switch to: their staff_branches, or every one with `branches.all`.
     *
     * @return Collection<int, Branch>
     */
    public static function available(): Collection
    {
        $staff = app(CurrentStaff::class);

        if ($staff->get() === null) {
            return new Collection;
        }

        return Branch::query()
            ->where('is_active', true)
            ->when(! $staff->hasAllBranches(), fn ($query) => $query->whereIn('id', $staff->branchIds()))
            ->orderBy('code')
            ->get();
    }

    public static function get(): ?Branch
    {
        $available = self::available();
        $chosen = session(self::key());

        return $available->firstWhere('id', $chosen)
            ?? $available->firstWhere('id', app(CurrentStaff::class)->get()?->primary_branch_id)
            ?? $available->first();
    }

    /**
     * @throws AuthorizationException
     */
    public static function select(Branch $branch): void
    {
        if (! self::available()->contains('id', $branch->id)) {
            throw new AuthorizationException('Bạn không làm việc ở cơ sở này.');
        }

        session([self::key() => $branch->id]);
    }

    private static function key(): string
    {
        return 'branch.'.app(TenantContext::class)->require()->getKey();
    }
}

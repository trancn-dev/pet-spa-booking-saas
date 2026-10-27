<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Branch;
use App\Tenancy\CurrentStaff;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Branch scope (ADR 0004): branches are not an isolation boundary, so every action on a
 * branch's data checks here that the person works at that branch (or has `branches.all`).
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
}

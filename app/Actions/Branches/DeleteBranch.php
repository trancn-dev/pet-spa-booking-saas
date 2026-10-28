<?php

declare(strict_types=1);

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Models\Staff;
use App\Support\ActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Soft delete (history keeps pointing at it). Refused for the last branch and while it is
 * someone's primary branch: deactivate it or move those people first.
 */
class DeleteBranch
{
    public function handle(Branch $branch): void
    {
        DB::transaction(function () use ($branch): void {
            if (Branch::query()->whereKeyNot($branch->getKey())->where('is_active', true)->doesntExist()) {
                throw ValidationException::withMessages(['branch' => 'Không xóa được cơ sở cuối cùng đang hoạt động.']);
            }

            $people = Staff::query()->where('primary_branch_id', $branch->getKey())->count();
            if ($people > 0) {
                throw ValidationException::withMessages(['branch' => "Còn {$people} nhân viên lấy đây làm cơ sở chính. Đổi cơ sở chính của họ trước."]);
            }

            ActivityLog::record('branch.deleted', $branch, ['code' => $branch->code, 'name' => $branch->name], branchId: $branch->id);
            $branch->delete();
        });
    }
}

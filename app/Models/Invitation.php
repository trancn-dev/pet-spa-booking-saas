<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Platform\PlatformAdmin;
use App\Tenancy\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation to join a shop (owner invited by a platform admin, staff by the owner).
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function primaryBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'primary_branch_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function invitedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'invited_by_staff_id');
    }

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function invitedByAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'invited_by_admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role_names' => 'array',
            'branch_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}

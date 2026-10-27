<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationState;
use App\Models\Platform\PlatformAdmin;
use App\Tenancy\BelongsToTenant;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Invitation to join a shop (owner invited by a platform admin, staff by the owner).
 *
 * @property string $email
 * @property list<string> $role_names
 * @property int|null $primary_branch_id
 * @property list<int>|null $branch_ids
 * @property int|null $invited_by_admin_id
 * @property int|null $invited_by_staff_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    public function state(): InvitationState
    {
        return match (true) {
            $this->accepted_at !== null => InvitationState::Accepted,
            $this->revoked_at !== null => InvitationState::Revoked,
            $this->expires_at === null || $this->expires_at->isPast() => InvitationState::Expired,
            default => InvitationState::Pending,
        };
    }

    /**
     * Only a platform admin may invite an owner (db-schema 2.2).
     */
    public function invitesOwner(): bool
    {
        return in_array('owner', $this->role_names, true) && $this->invited_by_admin_id !== null;
    }

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

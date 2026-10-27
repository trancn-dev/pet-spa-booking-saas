<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\PlatformAdminRole;
use Database\Factories\Platform\PlatformAdminInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformAdminInvitation extends Model
{
    /** @use HasFactory<PlatformAdminInvitationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'invited_by_admin_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PlatformAdminRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}

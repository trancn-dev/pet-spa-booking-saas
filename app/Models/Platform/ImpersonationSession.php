<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Models\Tenant;
use App\Models\User;
use Database\Factories\Platform\ImpersonationSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Admin acting as a shop user: one-time 60 s token, 30 min session.
 *
 * @property int $tenant_id
 * @property int $target_user_id
 * @property string $reason
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property Carbon $expires_at
 */
class ImpersonationSession extends Model
{
    /** @use HasFactory<ImpersonationSessionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}

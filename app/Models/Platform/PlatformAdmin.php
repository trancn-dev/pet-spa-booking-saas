<?php

declare(strict_types=1);

namespace App\Models\Platform;

use App\Enums\PlatformAdminRole;
use Database\Factories\Platform\PlatformAdminFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * Platform operator. Own table, guard and session cookie (ADR 0008; guard wired in task 4.1).
 * No platform permission ever lives on `users`.
 *
 * @property PlatformAdminRole $role
 * @property Carbon|null $last_login_at
 * @property Carbon|null $two_factor_confirmed_at
 */
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'remember_token' => null,
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === PlatformAdminRole::SuperAdmin;
    }

    public function hasTwoFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'datetime',
            'role' => PlatformAdminRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}

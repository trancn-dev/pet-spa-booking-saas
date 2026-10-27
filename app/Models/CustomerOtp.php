<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OtpPurpose;
use App\Tenancy\BelongsToTenant;
use Database\Factories\CustomerOtpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Site login / booking OTP: 6 digits, 5 minutes, 5 attempts.
 *
 * @property OtpPurpose $purpose
 */
class CustomerOtp extends Model
{
    /** @use HasFactory<CustomerOtpFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /** @var list<string> */
    protected $hidden = ['code_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}

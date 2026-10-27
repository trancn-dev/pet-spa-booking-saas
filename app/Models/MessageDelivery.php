<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\MessageChannel;
use App\Tenancy\BelongsToTenant;
use Database\Factories\MessageDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One real send attempt (Zalo first, SMS fallback).
 */
class MessageDelivery extends Model
{
    /** @use HasFactory<MessageDeliveryFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Reminder, $this>
     */
    public function reminder(): BelongsTo
    {
        return $this->belongsTo(Reminder::class);
    }

    /**
     * @return BelongsTo<CustomerOtp, $this>
     */
    public function otp(): BelongsTo
    {
        return $this->belongsTo(CustomerOtp::class, 'customer_otp_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'status' => DeliveryStatus::class,
            'cost' => 'decimal:2',
            'attempted_at' => 'datetime',
        ];
    }
}

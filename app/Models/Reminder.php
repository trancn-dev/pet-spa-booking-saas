<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RemindableType;
use App\Enums\ReminderPurpose;
use App\Enums\ReminderStatus;
use App\Tenancy\BelongsToTenant;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What to remind and when (ADR 0007). Actual sends are message_deliveries.
 */
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<MessageDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'remindable_type' => RemindableType::class,
            'purpose' => ReminderPurpose::class,
            'send_at' => 'datetime',
            'status' => ReminderStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}

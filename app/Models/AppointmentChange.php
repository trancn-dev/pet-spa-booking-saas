<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActorType;
use App\Enums\AppointmentChangeAction;
use App\Models\Concerns\AppendOnly;
use App\Tenancy\BelongsToTenant;
use Database\Factories\AppointmentChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Appointment history (append-only).
 *
 * @property AppointmentChangeAction $action
 * @property ActorType $actor_type
 */
class AppointmentChange extends Model
{
    /** @use HasFactory<AppointmentChangeFactory> */
    use AppendOnly, BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<AppointmentItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(AppointmentItem::class, 'appointment_item_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function actorStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'actor_staff_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AppointmentChangeAction::class,
            'old_starts_at' => 'datetime',
            'new_starts_at' => 'datetime',
            'actor_type' => ActorType::class,
            'created_at' => 'datetime',
        ];
    }
}

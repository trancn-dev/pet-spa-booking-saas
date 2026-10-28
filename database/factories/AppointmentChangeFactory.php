<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActorType;
use App\Enums\AppointmentChangeAction;
use App\Models\Appointment;
use App\Models\AppointmentChange;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentChange>
 */
class AppointmentChangeFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'appointment_id' => $this->sameTenant(Appointment::class),
            'action' => AppointmentChangeAction::Rescheduled,
            'actor_type' => ActorType::Staff,
            'created_at' => now(),
        ];
    }
}

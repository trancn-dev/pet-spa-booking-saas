<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentItemStatus;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Pet;
use App\Models\Service;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentItem>
 */
class AppointmentItemFactory extends Factory
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
            'branch_id' => fn (array $a): int => $this->appointment($a)->branch_id,
            'customer_id' => fn (array $a): int => $this->appointment($a)->customer_id,
            'pet_id' => fn (array $a) => Pet::factory()->state(['tenant_id' => $a['tenant_id'], 'customer_id' => $a['customer_id']]),
            'service_id' => $this->sameTenant(Service::class),
            'service_group' => ServiceGroup::Medical,
            'starts_at' => fn (array $a): mixed => $this->appointment($a)->starts_at,
            'ends_at' => fn (array $a): mixed => $this->appointment($a)->ends_at,
            'duration_min' => 30,
            'reference_price' => '200000.00',
            'status' => AppointmentItemStatus::Booked,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function appointment(array $attributes): Appointment
    {
        return Appointment::query()->findOrFail($attributes['appointment_id']);
    }
}

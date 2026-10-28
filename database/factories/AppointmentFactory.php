<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'branch_id' => $this->sameTenant(Branch::class),
            'customer_id' => $this->sameTenant(Customer::class),
            'starts_at' => now()->addDay()->setTime(2, 0),
            'ends_at' => now()->addDay()->setTime(2, 30),
            'source' => AppointmentSource::Staff,
            'status' => AppointmentStatus::Confirmed,
            'send_reminders' => true,
        ];
    }
}

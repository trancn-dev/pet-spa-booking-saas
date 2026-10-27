<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RemindableType;
use App\Enums\ReminderPurpose;
use App\Enums\ReminderStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Reminder;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reminder>
 */
class ReminderFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'customer_id' => $this->sameTenant(Customer::class),
            'remindable_type' => RemindableType::Appointment,
            'remindable_id' => fn (array $a) => Appointment::factory()->state(['tenant_id' => $a['tenant_id'], 'customer_id' => $a['customer_id']]),
            'purpose' => ReminderPurpose::Appointment24h,
            'send_at' => now()->addDay(),
            'status' => ReminderStatus::Pending,
            'dedupe_key' => fn (): string => (string) Str::uuid(),
        ];
    }
}

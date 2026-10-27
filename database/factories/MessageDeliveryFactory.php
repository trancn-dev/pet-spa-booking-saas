<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryStatus;
use App\Enums\MessageChannel;
use App\Models\MessageDelivery;
use App\Models\Reminder;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessageDelivery>
 */
class MessageDeliveryFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'reminder_id' => $this->sameTenant(Reminder::class),
            'channel' => MessageChannel::Zalo,
            'to_phone' => '+849'.fake()->numerify('########'),
            'template_code' => 'appointment_24h',
            'status' => DeliveryStatus::Queued,
        ];
    }
}

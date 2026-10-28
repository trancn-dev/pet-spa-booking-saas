<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OtpPurpose;
use App\Models\CustomerOtp;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerOtp>
 */
class CustomerOtpFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'phone' => '+849'.fake()->numerify('########'),
            'code_hash' => hash('sha256', '123456'),
            'purpose' => OtpPurpose::Login,
            'expires_at' => now()->addMinutes(5),
            'attempts' => 0,
        ];
    }
}

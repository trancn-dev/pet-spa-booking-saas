<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ActivityLog;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'action' => 'invoice.voided',
            'subject_type' => 'invoice',
            'subject_id' => 1,
            'changes' => [],
            'created_at' => now(),
        ];
    }
}

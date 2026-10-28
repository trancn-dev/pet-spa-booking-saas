<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MedicalEntry;
use App\Models\MedicalEntryAddendum;
use App\Models\Staff;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalEntryAddendum>
 */
class MedicalEntryAddendumFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'medical_entry_id' => $this->sameTenant(MedicalEntry::class),
            'author_staff_id' => $this->sameTenant(Staff::class),
            'body' => 'Tái khám ổn định',
            'created_at' => now(),
        ];
    }
}

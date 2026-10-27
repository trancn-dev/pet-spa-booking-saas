<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MedicalEntry;
use App\Models\MedicalRecord;
use App\Models\Staff;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalEntry>
 */
class MedicalEntryFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'medical_record_id' => $this->sameTenant(MedicalRecord::class),
            'visit_id' => null,
            'author_staff_id' => $this->sameTenant(Staff::class),
            'symptoms' => 'Bỏ ăn 2 ngày',
            'diagnosis' => 'Viêm dạ dày',
            'treatment' => 'Kháng sinh 5 ngày',
            'care_instructions' => 'Cho ăn cháo loãng',
            'recorded_at' => now(),
            'editable_until' => now()->addDay(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VisitItemType;
use App\Models\Service;
use App\Models\Visit;
use App\Models\VisitItem;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VisitItem>
 */
class VisitItemFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'visit_id' => $this->sameTenant(Visit::class),
            'item_type' => VisitItemType::Service,
            'service_id' => $this->sameTenant(Service::class),
            'name' => 'Khám tổng quát',
            'quantity' => '1.000',
            'unit_price' => '200000.00',
            'is_billable' => true,
            'unit_factor' => '1.000',
        ];
    }
}

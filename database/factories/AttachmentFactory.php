<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttachableType;
use App\Enums\AttachmentKind;
use App\Models\Attachment;
use App\Models\Visit;
use Database\Factories\Concerns\InTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    use InTenant;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => $this->currentTenant(),
            'attachable_type' => AttachableType::Visit,
            'attachable_id' => $this->sameTenant(Visit::class),
            'kind' => AttachmentKind::IntakePhoto,
            'is_shared_with_customer' => false,
            'disk' => 'local',
            'path' => fn (array $a): string => 'tenants/'.$a['tenant_id'].'/'.Str::ulid().'.jpg',
            'mime' => 'image/jpeg',
            'size_bytes' => 120_000,
            'original_name' => 'photo.jpg',
            'created_at' => now(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttachableType;
use App\Enums\AttachmentKind;
use App\Tenancy\BelongsToTenant;
use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Private file on a medical entry or a Visit. Polymorphic: the Action checks the target is in the same tenant.
 */
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'uploaded_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachable_type' => AttachableType::class,
            'kind' => AttachmentKind::class,
            'is_shared_with_customer' => 'boolean',
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}

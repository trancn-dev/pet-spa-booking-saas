<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AppendOnly;
use App\Tenancy\BelongsToTenant;
use Database\Factories\MedicalEntryAddendumFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Addendum written after the 24 h edit window (append-only).
 */
class MedicalEntryAddendum extends Model
{
    /** @use HasFactory<MedicalEntryAddendumFactory> */
    use AppendOnly, BelongsToTenant, HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'medical_entry_addenda';

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<MedicalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(MedicalEntry::class, 'medical_entry_id');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'author_staff_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}

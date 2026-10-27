<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\CareProtocolStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareProtocolStep extends Model
{
    /** @use HasFactory<CareProtocolStepFactory> */
    use BelongsToTenant, HasFactory;

    /** @var list<string> */
    protected $guarded = ['id', 'tenant_id'];

    /**
     * @return BelongsTo<CareProtocol, $this>
     */
    public function protocol(): BelongsTo
    {
        return $this->belongsTo(CareProtocol::class, 'protocol_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_no' => 'integer',
            'min_age_days' => 'integer',
            'offset_days' => 'integer',
            'is_recurring' => 'boolean',
            'repeat_every_days' => 'integer',
        ];
    }
}

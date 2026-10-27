<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PayrollPeriodKind;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-shop settings, one row per tenant (created with the shop).
 */
class TenantSetting extends Model
{
    use BelongsToTenant;

    public $incrementing = false;

    protected $primaryKey = 'tenant_id';

    /** @var list<string> */
    protected $guarded = ['tenant_id'];

    /** @var list<string> */
    protected $hidden = ['zalo_config', 'sms_config'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'booking_cancel_cutoff_hours' => 'integer',
            'booking_reschedule_cutoff_hours' => 'integer',
            'return_window_days' => 'integer',
            'default_tax_rate' => 'decimal:2',
            'customer_session_days' => 'integer',
            'walk_in_priority_minutes' => 'integer',
            'payroll_period_kind' => PayrollPeriodKind::class,
            'expiry_alert_days' => 'integer',
            'site_show_products' => 'boolean',
            'zalo_config' => 'encrypted:array',
            'sms_config' => 'encrypted:array',
            'terms_updated_at' => 'datetime',
        ];
    }
}

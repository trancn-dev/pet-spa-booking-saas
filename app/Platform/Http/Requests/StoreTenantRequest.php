<?php

declare(strict_types=1);

namespace App\Platform\Http\Requests;

use App\Models\Plan;
use App\Platform\Data\NewShop;
use App\Support\ShopSlug;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A3 wizard, all three steps in one submit (each step is validated client-side and again here).
 */
class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // `platform.role:super_admin` on the route
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // (1) shop
            'name' => ['required', 'string', 'max:255'],
            'slug' => ShopSlug::rules(),
            'kind' => ['required', Rule::in(['spa', 'vet', 'both'])],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'branch_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            // (2) owner
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:191'],
            'owner_phone' => ['nullable', 'string', 'max:20'],
            // (3) plan: trial of N days or a sale
            'plan_id' => ['required', Rule::exists('plans', 'id')],
            'billing' => ['required', Rule::in(['trial', 'paid'])],
            'trial_days' => ['required_if:billing,trial', 'nullable', 'integer', 'min:1', 'max:90'],
            'paid_from' => ['required_if:billing,paid', 'nullable', 'date'],
            'paid_until' => ['required_if:billing,paid', 'nullable', 'date', 'after:paid_from'],
            'amount' => ['required_if:billing,paid', 'nullable', 'numeric', 'min:0', 'max:9999999999'],
            'payment_ref' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...ShopSlug::messages(),
            'branch_code.regex' => 'Mã cơ sở chỉ gồm chữ và số (vd Q3).',
            'paid_until.after' => 'Ngày hết hạn phải sau ngày bắt đầu.',
            'required_if' => 'Bắt buộc.',
        ];
    }

    public function toNewShop(): NewShop
    {
        $paid = $this->string('billing')->value() === 'paid';
        $timezone = $this->string('timezone')->value();

        return new NewShop(
            name: $this->string('name')->trim()->value(),
            slug: $this->string('slug')->value(),
            plan: Plan::query()->findOrFail($this->integer('plan_id')),
            branchCode: strtoupper($this->string('branch_code')->value()),
            branchName: $this->string('branch_name')->trim()->value(),
            ownerName: $this->string('owner_name')->trim()->value(),
            ownerEmail: $this->string('owner_email')->trim()->value(),
            ownerPhone: $this->filled('owner_phone') ? $this->string('owner_phone')->value() : null,
            trialDays: $paid ? null : $this->integer('trial_days'),
            // Dates are the shop's calendar days: from 00:00 to 23:59:59 local time.
            paidFrom: $paid ? CarbonImmutable::parse($this->string('paid_from')->value(), $timezone)->startOfDay() : null,
            paidUntil: $paid ? CarbonImmutable::parse($this->string('paid_until')->value(), $timezone)->endOfDay() : null,
            amount: $paid ? (string) $this->input('amount') : null,
            paymentRef: $this->filled('payment_ref') ? $this->string('payment_ref')->value() : null,
            timezone: $timezone,
            branchAddress: $this->filled('branch_address') ? $this->string('branch_address')->value() : null,
            note: $this->filled('note') ? $this->string('note')->value() : null,
        );
    }
}

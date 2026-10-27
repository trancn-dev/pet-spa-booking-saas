<?php

declare(strict_types=1);

namespace App\Platform\Actions;

use App\Actions\Branches\CreateBranch;
use App\Actions\Invitations\IssueInvitation;
use App\Actions\Roles\CreateDefaultRoles;
use App\Enums\TenantStatus;
use App\Mail\ShopInvitationMail;
use App\Models\Invitation;
use App\Models\Platform\PlatformAdmin;
use App\Models\Platform\TenantSubscription;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Platform\Audit;
use App\Platform\Data\NewShop;
use App\Platform\Data\ProvisionedShop;
use App\Support\ShopSlug;
use App\Tenancy\TenantContext;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * The ONLY way a shop comes to exist (A3, demo seeder, tests). One transaction: tenant + settings
 * + first branch (stray profile, "Két 1") + 7 template roles + first subscription + the owner's
 * invitation + `tenant.created` audit. The invitation mail is queued after the commit.
 */
class ProvisionTenant
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CreateDefaultRoles $roles,
        private readonly CreateBranch $branches,
        private readonly IssueInvitation $invitations,
    ) {}

    public function handle(PlatformAdmin $by, NewShop $shop, bool $sendInvitation = true): ProvisionedShop
    {
        $this->validate($shop);

        $result = DB::transaction(function () use ($by, $shop): ProvisionedShop {
            // DATETIME columns are UTC: Eloquent writes a Carbon in its own zone, so convert here.
            $expiresAt = $shop->isTrial() ? now()->addDays((int) $shop->trialDays) : $shop->paidUntil?->utc();

            $tenant = Tenant::query()->create([
                'name' => $shop->name,
                'slug' => $shop->slug,
                'timezone' => $shop->timezone,
                'plan_id' => $shop->plan->getKey(),
                'status' => $shop->isTrial() ? TenantStatus::Trial : TenantStatus::Active,
                'plan_expires_at' => $expiresAt,
                'contact_name' => $shop->ownerName,
                'contact_phone' => $shop->ownerPhone,
                'admin_note' => $shop->note,
                'created_by_admin_id' => $by->getKey(),
            ]);

            TenantSubscription::query()->create([
                'tenant_id' => $tenant->getKey(),
                'plan_id' => $shop->plan->getKey(),
                'starts_at' => $shop->isTrial() ? now() : $shop->paidFrom?->utc(),
                'ends_at' => $expiresAt,
                'amount' => $shop->isTrial() ? '0' : $shop->amount,
                'paid_at' => $shop->isTrial() ? null : now(),
                'payment_ref' => $shop->paymentRef,
                'note' => $shop->isTrial() ? "Dùng thử {$shop->trialDays} ngày" : null,
                'created_by_admin_id' => $by->getKey(),
            ]);

            $this->roles->handle($tenant);

            [$invitation, $token] = $this->context->runAs($tenant, function () use ($by, $shop): array {
                TenantSetting::query()->create();

                $branch = $this->branches->handle([
                    'code' => $shop->branchCode,
                    'name' => $shop->branchName,
                    'address' => $shop->branchAddress,
                    'phone' => $shop->branchPhone,
                ]);

                // The owner works at every branch: AcceptInvitation attaches them all.
                return $this->invitations->handle($shop->ownerEmail, ['owner'], $branch->id, admin: $by);
            });

            Audit::record($by, 'tenant.created', $tenant, [
                'slug' => $shop->slug,
                'plan' => $shop->plan->code,
                'status' => $tenant->status->value,
                'owner_email' => $invitation->email,
            ], $tenant->getKey());

            return new ProvisionedShop($tenant, $invitation, $token);
        });

        if ($sendInvitation) {
            $this->mail($result->tenant, $result->invitation, $result->url(), $by->name);
        }

        return $result;
    }

    public function mail(Tenant $tenant, Invitation $invitation, string $url, string $invitedBy): void
    {
        Mail::to($invitation->email)->queue(new ShopInvitationMail(
            shopName: $tenant->name,
            roleLabel: 'Chủ shop',
            url: $url,
            expiresAt: $invitation->expires_at?->timezone($tenant->timezone)->format('H:i d/m/Y') ?? '',
            invitedBy: $invitedBy,
        ));
    }

    private function validate(NewShop $shop): void
    {
        Validator::make([
            'name' => $shop->name,
            'slug' => $shop->slug,
            'timezone' => $shop->timezone,
            'branch_code' => $shop->branchCode,
            'branch_name' => $shop->branchName,
            'owner_name' => $shop->ownerName,
            'owner_email' => $shop->ownerEmail,
            'trial_days' => $shop->trialDays,
            'paid_from' => $shop->paidFrom?->toDateTimeString(),
            'paid_until' => $shop->paidUntil?->toDateTimeString(),
            'amount' => $shop->amount,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ShopSlug::rules(),
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'branch_code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'branch_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:191'],
            'trial_days' => ['nullable', 'integer', 'min:1', 'max:90', 'required_without:paid_until'],
            'paid_from' => ['nullable', 'required_without:trial_days', 'date'],
            'paid_until' => ['nullable', 'required_without:trial_days', 'date', 'after:paid_from'],
            'amount' => ['nullable', 'required_without:trial_days', 'numeric', 'min:0'],
        ], [
            ...ShopSlug::messages(),
            'branch_code.regex' => 'Mã cơ sở chỉ gồm chữ và số (vd Q3).',
            'paid_until.after' => 'Ngày hết hạn phải sau ngày bắt đầu.',
        ])->validate();
    }
}

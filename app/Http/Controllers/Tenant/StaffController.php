<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\Staff\InviteStaff;
use App\Actions\Staff\ManageInvitation;
use App\Actions\Staff\SaveStaff;
use App\Actions\Staff\SetStaffStatus;
use App\Actions\Staff\StaffQuota;
use App\Enums\InvitationState;
use App\Enums\StaffStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\CurrentStaff;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S11 Nhân viên & lời mời, S11.1 hồ sơ nhân viên (task 4.4). `staff.view` lists, `staff.manage`
 * changes anything.
 */
class StaffController extends Controller
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CurrentStaff $current,
    ) {}

    public function index(Request $request, StaffQuota $quota): Response
    {
        Gate::authorize('viewAny', Staff::class);

        $tenant = $this->context->require();
        $staff = Staff::query()->with(['user:id,email', 'branches:id,code,name'])->orderBy('status')->orderBy('display_name')->get();
        $roles = $this->rolesByUser($staff->pluck('user_id')->filter()->all());
        $branchNames = Branch::query()->pluck('name', 'id');

        $invitations = Invitation::query()->whereNull('accepted_at')->whereNull('revoked_at')->whereNull('invited_by_admin_id')
            ->latest('id')->get();
        $roleLabels = $this->roleOptions()->pluck('label', 'name');

        return Inertia::render('App/Staff/Index', [
            'tab' => $request->string('tab')->value() === 'invitations' ? 'invitations' : 'members',
            'staff' => $staff->map(fn (Staff $s): array => [
                'id' => $s->id,
                'display_name' => $s->display_name,
                'email' => $s->user?->email,
                'has_account' => $s->user_id !== null,
                'title' => $s->title,
                'color' => $s->color,
                'status' => $s->status->value,
                'roles' => $roles[$s->user_id] ?? [],
                'primary_branch' => $branchNames[$s->primary_branch_id] ?? null,
                'branches' => $s->branches->where('id', '!=', $s->primary_branch_id)->pluck('name')->values()->all(),
            ])->values(),
            'invitations' => $invitations->map(fn (Invitation $i): array => [
                'id' => $i->id,
                'email' => $i->email,
                'roles' => collect($i->role_names)->map(fn (string $n) => $roleLabels[$n] ?? $n)->values()->all(),
                'primary_branch' => $branchNames[$i->primary_branch_id] ?? null,
                'expires_at' => $i->expires_at?->toIso8601String(),
                'expired' => $i->state() === InvitationState::Expired,
            ])->values(),
            'roles' => $this->roleOptions(),
            'branches' => $this->branchOptions(),
            'quota' => $quota->usage($tenant),
            'timezone' => $tenant->timezone,
            'can' => ['manage' => Gate::allows('create', Staff::class)],
        ]);
    }

    public function invite(Request $request, InviteStaff $invite): RedirectResponse
    {
        Gate::authorize('create', Staff::class);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
            'primary_branch' => ['required', 'string'],
            'branches' => ['array'],
            'branches.*' => ['string'],
        ], ['roles.required' => 'Chọn ít nhất một role.']);

        $ids = $this->branchIds([$data['primary_branch'], ...($data['branches'] ?? [])]);

        $invite->handle(
            $this->by(),
            (string) $data['email'],
            array_values(array_map('strval', $data['roles'])),
            $ids[(string) $data['primary_branch']] ?? 0,
            array_values(array_filter(array_map(fn ($id) => $ids[(string) $id] ?? null, $data['branches'] ?? []))),
        );

        return to_route('app.staff.index', ['tab' => 'invitations'])->with('status', 'Đã gửi lời mời tới '.$data['email'].'.');
    }

    public function resendInvitation(Invitation $invitation, ManageInvitation $manage): RedirectResponse
    {
        Gate::authorize('create', Staff::class);
        $manage->resend($this->by(), $invitation);

        return back()->with('status', 'Đã gửi lại lời mời. Link cũ không còn dùng được.');
    }

    public function revokeInvitation(Invitation $invitation, ManageInvitation $manage): RedirectResponse
    {
        Gate::authorize('create', Staff::class);
        $manage->revoke($invitation);

        return back()->with('status', 'Đã thu hồi lời mời.');
    }

    public function create(): Response
    {
        Gate::authorize('create', Staff::class);

        return $this->form(null);
    }

    public function store(Request $request, SaveStaff $save): RedirectResponse
    {
        Gate::authorize('create', Staff::class);

        $staff = $save->handle(null, $this->profile($request));

        return to_route('app.staff.index')->with('status', 'Đã thêm '.$staff->display_name.'.');
    }

    public function edit(Staff $staff): Response
    {
        Gate::authorize('update', $staff);

        return $this->form($staff);
    }

    public function update(Request $request, Staff $staff, SaveStaff $save): RedirectResponse
    {
        Gate::authorize('update', $staff);

        $save->handle($staff, $this->profile($request));

        return to_route('app.staff.index')->with('status', 'Đã lưu hồ sơ '.$staff->display_name.'.');
    }

    public function setStatus(Request $request, Staff $staff, SetStaffStatus $set): RedirectResponse
    {
        Gate::authorize('update', $staff);

        $status = StaffStatus::from((string) $request->validate(['status' => ['required', Rule::enum(StaffStatus::class)]])['status']);
        $set->handle($this->by(), $staff, $status);

        return back()->with('status', $status === StaffStatus::Disabled ? 'Đã vô hiệu '.$staff->display_name.'.' : 'Đã kích hoạt lại '.$staff->display_name.'.');
    }

    private function form(?Staff $staff): Response
    {
        $staff?->load('branches:id,public_id');
        $user = $staff?->user_id === null ? null : User::query()->find($staff->user_id);
        $roles = $user === null ? [] : ($this->rolesByUser([$user->id])[$user->id] ?? []);
        $primary = $staff === null ? null : Branch::query()->whereKey($staff->primary_branch_id)->value('public_id');

        return Inertia::render('App/Staff/Edit', [
            'staff' => $staff === null ? null : [
                'id' => $staff->id,
                'display_name' => $staff->display_name,
                'email' => $user?->email,
                'has_account' => $user !== null,
                'phone' => $staff->phone,
                'title' => $staff->title,
                'color' => $staff->color,
                'license_no' => $staff->license_no,
                'qualifications' => $staff->qualifications,
                'status' => $staff->status->value,
                'is_owner' => collect($roles)->contains('name', Role::OWNER),
                'roles' => collect($roles)->pluck('name')->reject(fn ($n) => $n === Role::OWNER)->values()->all(),
                'primary_branch' => $primary,
                'branches' => $staff->branches->pluck('public_id')->reject(fn ($id) => $id === $primary)->values()->all(),
            ],
            'roles' => $this->roleOptions(),
            'branches' => $this->branchOptions(),
            'colors' => ['#0D9488', '#F97362', '#8B5CF6', '#0EA5E9', '#EAB308', '#EC4899', '#22C55E', '#64748B'],
        ]);
    }

    /**
     * @return array{display_name: string, phone: string|null, title: string|null, color: string|null, license_no: string|null, qualifications: string|null, roles: list<string>, primary_branch_id: int, branch_ids: list<int>}
     */
    private function profile(Request $request): array
    {
        $data = $request->validate([
            'display_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'title' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'license_no' => ['nullable', 'string', 'max:50'],
            'qualifications' => ['nullable', 'string', 'max:2000'],
            'roles' => ['array'],
            'roles.*' => ['string'],
            'primary_branch' => ['required', 'string'],
            'branches' => ['array'],
            'branches.*' => ['string'],
        ]);

        $ids = $this->branchIds([$data['primary_branch'], ...($data['branches'] ?? [])]);

        return [
            'display_name' => (string) $data['display_name'],
            'phone' => $data['phone'] ?? null,
            'title' => $data['title'] ?? null,
            'color' => $data['color'] ?? null,
            'license_no' => $data['license_no'] ?? null,
            'qualifications' => $data['qualifications'] ?? null,
            'roles' => array_values(array_map('strval', $data['roles'] ?? [])),
            'primary_branch_id' => $ids[(string) $data['primary_branch']] ?? 0,
            'branch_ids' => array_values(array_filter(array_map(fn ($id) => $ids[(string) $id] ?? null, $data['branches'] ?? []))),
        ];
    }

    private function by(): Staff
    {
        $staff = $this->current->get();
        abort_if($staff === null, 403);

        return $staff;
    }

    /**
     * public_id => id, for this shop's branches only (others are simply unknown).
     *
     * @param  list<mixed>  $publicIds
     * @return array<string, int>
     */
    private function branchIds(array $publicIds): array
    {
        return Branch::query()->whereIn('public_id', array_map('strval', $publicIds))->pluck('id', 'public_id')
            ->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return Collection<int, array{name: string, label: string}>
     */
    private function roleOptions(): Collection
    {
        return Role::query()->where('team_id', $this->context->require()->getKey())->where('name', '!=', Role::OWNER)
            ->orderBy('id')->get()->map(fn (Role $r): array => ['name' => $r->name, 'label' => $r->display_name])->values();
    }

    /**
     * @return Collection<int, array{id: string, code: string, name: string}>
     */
    private function branchOptions(): Collection
    {
        return Branch::query()->where('is_active', true)->orderBy('code')->get()
            ->map(fn (Branch $b): array => ['id' => $b->public_id, 'code' => $b->code, 'name' => $b->name])->values();
    }

    /**
     * Roles in this shop per user id (one query, no N+1).
     *
     * @param  array<int, mixed>  $userIds
     * @return array<int, list<array{name: string, label: string}>>
     */
    private function rolesByUser(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.team_id', $this->context->require()->getKey())
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->whereIn('model_has_roles.model_id', $userIds)
            ->orderBy('roles.id')
            ->get(['model_has_roles.model_id', 'roles.name', 'roles.display_name'])
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->map(fn ($r): array => ['name' => (string) $r->name, 'label' => (string) $r->display_name])->values()->all())
            ->all();
    }
}

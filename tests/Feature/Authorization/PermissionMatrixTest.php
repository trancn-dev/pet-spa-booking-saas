<?php

declare(strict_types=1);

use App\Enums\InvoiceStatus;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MedicalEntry;
use App\Models\Pet;
use App\Models\PetVaccination;
use App\Models\Role;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffWorkingHour;
use App\Models\Visit;
use App\Tenancy\CurrentStaff;
use Illuminate\Support\Facades\Gate;

/*
| design.md section 8: template role × action. Every row is a policy decision, checked the same
| way controllers do (Gate / policies, permissions of the current shop only).
*/

const TEMPLATE_ROLES = ['owner', 'branch_manager', 'receptionist', 'cashier', 'vet', 'groomer', 'inventory'];

/**
 * @param  list<string>  $allowed
 * @return array<string, bool>
 */
function expectRoles(array $allowed): array
{
    return array_combine(TEMPLATE_ROLES, array_map(fn (string $role) => in_array($role, $allowed, true), TEMPLATE_ROLES));
}

beforeEach(function () {
    $this->shop = shopWithRoles();
    $this->q3 = inTenant($this->shop, fn () => Branch::factory()->create(['code' => 'Q3']));

    $this->subjects = inTenant($this->shop, function () {
        $customer = Customer::factory()->create();
        $pet = Pet::factory()->of($customer)->create();
        $branch = $this->q3;

        return [
            'customer' => $customer,
            'pet' => $pet,
            'appointment' => Appointment::factory()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id]),
            'medicalVisit' => Visit::factory()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'pet_id' => $pet->id, 'service_group' => ServiceGroup::Medical]),
            'groomingVisit' => Visit::factory()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'pet_id' => $pet->id, 'service_group' => ServiceGroup::Grooming]),
            'draft' => Invoice::factory()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id]),
            'completed' => Invoice::factory()->completed()->create(['branch_id' => $branch->id, 'customer_id' => $customer->id, 'business_date' => now()->subDay()->toDateString()]),
            'service' => Service::factory()->create(),
        ];
    });
});

/**
 * @param  Closure(array<string, mixed>, Branch): array{0: string, 1: mixed}  $check
 */
function decide(object $test, string $role, Closure $check): bool
{
    $user = memberWithRoles($test->shop, [$role], [$test->q3]);
    [$ability, $arguments] = $check($test->subjects, $test->q3);

    return inTenant($test->shop, fn () => asTeam($test->shop, function () use ($test, $user, $ability, $arguments) {
        $test->actingAs($user);
        app(CurrentStaff::class)->forget();

        return Gate::forUser($user)->allows($ability, $arguments);
    }));
}

dataset('matrix', [
    'customers: edit' => [fn ($s) => ['update', $s['customer']], expectRoles(['owner', 'branch_manager', 'receptionist'])],
    'customers: view' => [fn ($s) => ['view', $s['customer']], expectRoles(['owner', 'branch_manager', 'receptionist', 'cashier', 'vet', 'groomer'])],
    'customers: delete' => [fn ($s) => ['delete', $s['customer']], expectRoles(['owner'])],
    'pets: edit, allergy alerts' => [fn ($s) => ['update', $s['pet']], expectRoles(['owner', 'branch_manager', 'receptionist', 'vet'])],
    'medical: full content' => [fn () => ['viewFull', MedicalEntry::class], expectRoles(['owner', 'vet'])],
    'medical: alerts + exam dates' => [fn () => ['viewAlerts', MedicalEntry::class], expectRoles(['owner', 'branch_manager', 'receptionist', 'vet', 'groomer'])],
    'medical: write (owner only with the vet role)' => [fn () => ['create', MedicalEntry::class], expectRoles(['vet'])],
    'vaccinations: view' => [fn () => ['viewAny', PetVaccination::class], expectRoles(['owner', 'branch_manager', 'receptionist', 'vet', 'groomer'])],
    'vaccinations: record' => [fn () => ['create', PetVaccination::class], expectRoles(['vet'])],
    'appointments: book, move, cancel' => [fn ($s) => ['update', $s['appointment']], expectRoles(['owner', 'branch_manager', 'receptionist'])],
    'appointments: view' => [fn ($s) => ['view', $s['appointment']], expectRoles(['owner', 'branch_manager', 'receptionist', 'vet', 'groomer'])],
    'rosters: edit' => [fn ($s, $b) => ['manage', [StaffWorkingHour::class, $b]], expectRoles(['owner', 'branch_manager'])],
    'intake: create visit' => [fn ($s, $b) => ['create', [Visit::class, $b]], expectRoles(['owner', 'branch_manager', 'receptionist'])],
    'visit: take a medical visit' => [fn ($s) => ['take', $s['medicalVisit']], expectRoles(['vet'])],
    'visit: take a grooming visit' => [fn ($s) => ['take', $s['groomingVisit']], expectRoles(['owner', 'groomer'])],
    'prescribe, lab orders' => [fn ($s) => ['prescribe', $s['medicalVisit']], expectRoles(['vet'])],
    'draft invoice: add lines, take payment' => [fn ($s) => ['charge', $s['draft']], expectRoles(['owner', 'branch_manager', 'receptionist', 'cashier'])],
    'draft invoice: void' => [fn ($s) => ['voidDraft', $s['draft']], expectRoles(['owner', 'branch_manager', 'receptionist', 'cashier'])],
    'completed invoice: amend (sensitive)' => [fn ($s) => ['amend', $s['completed']], expectRoles(['owner'])],
    'completed invoice: view' => [fn ($s) => ['view', $s['completed']], expectRoles(['owner', 'branch_manager', 'receptionist', 'cashier'])],
    'till: open / close' => [fn ($s, $b) => ['open', [CashSession::class, $b]], expectRoles(['owner', 'receptionist', 'cashier'])],
    'till: view' => [fn () => ['viewAny', CashSession::class], expectRoles(['owner', 'branch_manager', 'receptionist', 'cashier'])],
    'services and prices: edit' => [fn ($s) => ['update', $s['service']], expectRoles(['owner'])],
    'services: view' => [fn () => ['viewAny', Service::class], expectRoles(TEMPLATE_ROLES)],
    'staff: view' => [fn () => ['viewAny', Staff::class], expectRoles(['owner', 'branch_manager'])],
    'staff: invite, edit' => [fn () => ['create', Staff::class], expectRoles(['owner'])],
    'roles: manage' => [fn () => ['viewAny', Role::class], expectRoles(['owner'])],
]);

it('follows the template role matrix', function (Closure $check, array $expected) {
    $actual = [];
    foreach (TEMPLATE_ROLES as $role) {
        $actual[$role] = decide($this, $role, $check);
    }

    expect($actual)->toBe($expected);
})->with('matrix');

it('adds up permissions of several roles (owner who is also a vet)', function () {
    $user = memberWithRoles($this->shop, ['owner', 'vet'], [$this->q3]);

    inTenant($this->shop, fn () => asTeam($this->shop, function () use ($user) {
        $this->actingAs($user);
        expect(Gate::forUser($user)->allows('create', MedicalEntry::class))->toBeTrue()
            ->and(Gate::forUser($user)->allows('take', $this->subjects['medicalVisit']))->toBeTrue();
    }));
});

it('limits staff to their own branches unless they have branches.all', function () {
    $q7 = inTenant($this->shop, fn () => Branch::factory()->create(['code' => 'Q7']));
    $atQ7 = inTenant($this->shop, fn () => Appointment::factory()->create(['branch_id' => $q7->id, 'customer_id' => $this->subjects['customer']->id]));

    $receptionist = memberWithRoles($this->shop, ['receptionist'], [$this->q3]);
    $owner = memberWithRoles($this->shop, ['owner'], [$this->q3]);

    inTenant($this->shop, fn () => asTeam($this->shop, function () use ($receptionist, $owner, $atQ7, $q7) {
        $this->actingAs($receptionist);
        app(CurrentStaff::class)->forget();
        expect(Gate::forUser($receptionist)->allows('update', $atQ7))->toBeFalse()
            ->and(Gate::forUser($receptionist)->allows('update', $this->subjects['appointment']))->toBeTrue()
            ->and(Gate::forUser($receptionist)->allows('access', $q7))->toBeFalse();

        $this->actingAs($owner);
        app(CurrentStaff::class)->forget();
        expect(Gate::forUser($owner)->allows('update', $atQ7))->toBeTrue();
    }));
});

it('only lets a medical entry author edit it, within 24 hours', function () {
    $author = memberWithRoles($this->shop, ['vet'], [$this->q3]);
    $other = memberWithRoles($this->shop, ['vet'], [$this->q3]);

    inTenant($this->shop, fn () => asTeam($this->shop, function () use ($author, $other) {
        $this->actingAs($author);
        $authorStaff = Staff::query()->where('user_id', $author->id)->sole();
        $entry = MedicalEntry::factory()->create(['author_staff_id' => $authorStaff->id, 'editable_until' => now()->addHour()]);
        $old = MedicalEntry::factory()->create(['author_staff_id' => $authorStaff->id, 'editable_until' => now()->subMinute()]);

        app(CurrentStaff::class)->forget();
        expect(Gate::forUser($author)->allows('update', $entry))->toBeTrue()
            ->and(Gate::forUser($author)->allows('update', $old))->toBeFalse()
            ->and(Gate::forUser($author)->allows('addAddendum', $old))->toBeTrue();

        $this->actingAs($other);
        app(CurrentStaff::class)->forget();
        expect(Gate::forUser($other)->allows('update', $entry))->toBeFalse();
    }));
});

it('keeps draft-only and completed-only actions apart', function () {
    $owner = memberWithRoles($this->shop, ['owner'], [$this->q3]);

    inTenant($this->shop, fn () => asTeam($this->shop, function () use ($owner) {
        $this->actingAs($owner);
        expect(Gate::forUser($owner)->allows('charge', $this->subjects['completed']))->toBeFalse()
            ->and(Gate::forUser($owner)->allows('amend', $this->subjects['draft']))->toBeFalse()
            ->and($this->subjects['draft']->status)->toBe(InvoiceStatus::Draft);
    }));
});

<?php

declare(strict_types=1);

use App\Actions\Customers\DeleteCustomer;
use App\Actions\Customers\SaveCustomer;
use App\Actions\Invitations\AcceptInvitation;
use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Pet;
use App\Models\User;
use App\Models\Visit;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 5.1: S10 customers. Phone = key (E.164, unique per shop), search, list without N+1,
| hidden stray profile, soft delete blocked by upcoming appointments, the combobox API.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = provisionShop();
    $this->tenant = $this->shop->tenant;
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $this->shop->invitation, $this->owner);
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->customer = fn (array $attributes = []): Customer => inTenant($this->tenant, fn () => Customer::factory()->create($attributes));
});

it('creates a customer with the phone normalised to E.164', function () {
    $response = $this->actingAs($this->owner)->post('/app/paw-test/customers', [
        'name' => 'Nguyễn Thị Lan', 'phone' => '090 123-4567', 'email' => '', 'address' => ' 12 Lý Tự Trọng ',
    ]);

    $lan = inTenant($this->tenant, fn () => Customer::query()->where('is_system', false)->sole());
    $response->assertRedirect("/app/paw-test/customers/{$lan->public_id}");
    expect($lan->phone)->toBe('+84901234567')
        ->and($lan->phone_suffix)->toBe('4567')
        ->and($lan->email)->toBeNull()
        ->and($lan->address)->toBe('12 Lý Tự Trọng');
});

it('rejects an invalid phone and a phone another customer has, in any format', function () {
    ($this->customer)(['name' => 'Lan', 'phone' => '+84901234567']);

    $this->actingAs($this->owner)->post('/app/paw-test/customers', ['name' => 'X', 'phone' => '12345'])
        ->assertSessionHasErrors(['phone' => Phone::INVALID]);
    $this->actingAs($this->owner)->post('/app/paw-test/customers', ['name' => 'Lan 2', 'phone' => '+84 901 234 567'])
        ->assertSessionHasErrors(['phone' => SaveCustomer::TAKEN]);

    expect(inTenant($this->tenant, fn () => Customer::query()->where('is_system', false)->count()))->toBe(1);
});

it('lets two shops have the same phone', function () {
    ($this->customer)(['phone' => '+84901234567']);
    $other = provisionShop(['name' => 'Other', 'slug' => 'other-shop', 'ownerEmail' => 'o@other.test']);

    [$c] = inTenant($other->tenant, fn () => app(SaveCustomer::class)->handle(null, ['name' => 'Lan', 'phone' => '0901234567']));

    expect($c->tenant_id)->toBe($other->tenant->id);
});

it('brings back a deleted profile when its phone is added again', function () {
    $old = ($this->customer)(['name' => 'Tên cũ', 'phone' => '+84901234567']);
    inTenant($this->tenant, fn () => $old->delete());

    $this->actingAs($this->owner)->post('/app/paw-test/customers', ['name' => 'Tên mới', 'phone' => '0901234567'])
        ->assertRedirect("/app/paw-test/customers/{$old->public_id}")
        ->assertSessionHas('status', fn (string $s) => str_contains($s, 'khôi phục'));

    $old->refresh();
    expect($old->trashed())->toBeFalse()->and($old->name)->toBe('Tên mới');
});

it('edits a customer but not onto a deleted profile phone', function () {
    $lan = ($this->customer)(['phone' => '+84901234567']);
    $gone = ($this->customer)(['phone' => '+84907654321']);
    inTenant($this->tenant, fn () => $gone->delete());

    $this->actingAs($this->owner)->put("/app/paw-test/customers/{$lan->public_id}", ['name' => 'Lan', 'phone' => '0907654321'])
        ->assertSessionHasErrors(['phone' => SaveCustomer::TAKEN_BY_DELETED]);

    $this->actingAs($this->owner)->put("/app/paw-test/customers/{$lan->public_id}", ['name' => 'Lan Nguyễn', 'phone' => '0901 234 567', 'notes' => 'Khách quen'])
        ->assertRedirect("/app/paw-test/customers/{$lan->public_id}");
    expect($lan->refresh()->name)->toBe('Lan Nguyễn')->and($lan->notes)->toBe('Khách quen');
});

it('lists customers with pet count, last visit and total spent, hiding the stray profile', function () {
    $lan = ($this->customer)(['name' => 'Nguyễn Thị Lan', 'phone' => '+84901234567']);
    ($this->customer)(['name' => 'Trần Văn Bình', 'phone' => '+84912345678']);
    inTenant($this->tenant, function () use ($lan) {
        $pet = Pet::factory()->create(['customer_id' => $lan->id, 'name' => 'Mochi', 'allergies' => 'Penicillin']);
        Pet::factory()->create(['customer_id' => $lan->id, 'name' => 'Bơ']);
        Visit::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $lan->id, 'pet_id' => $pet->id, 'visit_date' => '2026-10-01', 'status' => VisitStatus::Done]);
        Visit::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $lan->id, 'pet_id' => $pet->id, 'visit_date' => '2026-10-20', 'status' => VisitStatus::Cancelled]);
        Invoice::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $lan->id, 'status' => InvoiceStatus::Completed, 'total' => '350000.00']);
        Invoice::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $lan->id, 'status' => InvoiceStatus::Void, 'total' => '999000.00']);
    });

    $this->actingAs($this->owner)->get('/app/paw-test/customers')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Customers/Index')
            ->has('customers.data', 2)
            ->where('customers.data.0.name', 'Nguyễn Thị Lan')
            ->where('customers.data.0.phone', '0901 234 567')
            ->where('customers.data.0.pets_count', 2)
            ->where('customers.data.0.last_visit_on', '2026-10-01')
            ->where('customers.data.0.total_spent', '350000.00')
            ->where('customers.data.0.pets.1.alerts.0.label', 'Dị ứng')
            ->where('customers.data.1.total_spent', '0.00'));
});

it('searches by name, pet name, full phone, phone prefix and the last four digits', function (string $q, array $names) {
    ($this->customer)(['name' => 'Nguyễn Thị Lan', 'phone' => '+84901234567']);
    $binh = ($this->customer)(['name' => 'Trần Văn Bình', 'phone' => '+84912340000']);
    inTenant($this->tenant, fn () => Pet::factory()->create(['customer_id' => $binh->id, 'name' => 'Mochi']));

    $this->actingAs($this->owner)->get('/app/paw-test/customers?q='.urlencode($q))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('customers.data', fn ($rows) => collect($rows)->pluck('name')->all() === $names));
})->with([
    'accent-insensitive name' => ['nguyen', ['Nguyễn Thị Lan']],
    'pet name' => ['moc', ['Trần Văn Bình']],
    'full phone, other format' => ['0901 234 567', ['Nguyễn Thị Lan']],
    'phone prefix' => ['09123', ['Trần Văn Bình']],
    'last four digits' => ['4567', ['Nguyễn Thị Lan']],
    'no match' => ['zzz', []],
]);

it('uses the phone indexes instead of a leading wildcard', function () {
    DB::enableQueryLog();
    $this->actingAs($this->owner)->get('/app/paw-test/customers?q=4567')->assertOk();
    $this->actingAs($this->owner)->get('/app/paw-test/customers?q=09012')->assertOk();
    $sql = collect(DB::getQueryLog())->pluck('query')->filter(fn ($q) => str_contains($q, 'from `customers`'))->implode("\n");

    expect($sql)->toContain('`customers`.`phone_suffix` = ?')
        ->toContain('`customers`.`phone` like ?')
        ->and(collect(DB::getQueryLog())->flatMap(fn ($q) => $q['bindings'])->filter(fn ($b) => is_string($b) && str_starts_with($b, '%') && preg_match('/\d/', $b))->all())->toBe([]);
});

it('lists without N+1: the query count does not grow with the page', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->owner)->get('/app/paw-test/customers')->assertOk();

        return count(DB::getQueryLog());
    };

    inTenant($this->tenant, fn () => Customer::factory()->count(2)->has(Pet::factory()->count(2))->create());
    $count(); // warm-up: the first request also stores last_tenant_id and the like
    $few = $count();
    inTenant($this->tenant, fn () => Customer::factory()->count(15)->has(Pet::factory()->count(2))->create());
    $many = $count();

    expect($many)->toBe($few);
});

it('paginates 20 per page', function () {
    inTenant($this->tenant, fn () => Customer::factory()->count(23)->create());

    $this->actingAs($this->owner)->get('/app/paw-test/customers?page=2')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('customers.data', 3)->where('customers.total', 23)->where('customers.last_page', 2));
});

it('shows a customer with pets, appointments and invoices', function () {
    $lan = ($this->customer)(['name' => 'Lan']);
    inTenant($this->tenant, function () use ($lan) {
        Pet::factory()->create(['customer_id' => $lan->id, 'name' => 'Mochi', 'behavior_notes' => 'Cắn người']);
        Appointment::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $lan->id]);
        Invoice::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $lan->id]);
    });

    $this->actingAs($this->owner)->get("/app/paw-test/customers/{$lan->public_id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Customers/Show')
            ->where('customer.name', 'Lan')
            ->where('pets.0.alerts.0.text', 'Cắn người')
            ->where('appointments.0.branch', 'CN Quận 1')
            ->where('invoices.0.branch', 'CN Quận 1')
            ->where('can.delete', true));
});

it('refuses to delete a customer with an upcoming appointment, deletes otherwise with the pets', function () {
    $lan = ($this->customer)();
    [$pet, $appointment] = inTenant($this->tenant, fn () => [
        Pet::factory()->create(['customer_id' => $lan->id]),
        Appointment::factory()->create(['branch_id' => $this->q1->id, 'customer_id' => $lan->id, 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour()]),
    ]);

    $this->actingAs($this->owner)->from("/app/paw-test/customers/{$lan->public_id}")->delete("/app/paw-test/customers/{$lan->public_id}")
        ->assertSessionHasErrors(['customer' => DeleteCustomer::HAS_UPCOMING]);
    expect($lan->refresh()->trashed())->toBeFalse();

    inTenant($this->tenant, fn () => $appointment->update(['status' => AppointmentStatus::Cancelled]));
    $this->actingAs($this->owner)->delete("/app/paw-test/customers/{$lan->public_id}")->assertRedirect('/app/paw-test/customers');

    expect($lan->refresh()->trashed())->toBeTrue()
        ->and(inTenant($this->tenant, fn () => Pet::withTrashed()->find($pet->id)?->trashed()))->toBeTrue();
});

it('never edits or deletes the stray profile', function () {
    $stray = inTenant($this->tenant, fn () => Customer::query()->where('is_system', true)->sole());

    $this->actingAs($this->owner)->get("/app/paw-test/customers/{$stray->public_id}/edit")->assertForbidden();
    $this->actingAs($this->owner)->put("/app/paw-test/customers/{$stray->public_id}", ['name' => 'X', 'phone' => '0901234567'])->assertForbidden();
    $this->actingAs($this->owner)->delete("/app/paw-test/customers/{$stray->public_id}")->assertForbidden();
    expect($stray->refresh()->name)->toBe('Thú hoang / vô chủ');
});

it('answers the combobox with at most 10 customers and their pets', function () {
    inTenant($this->tenant, function () {
        Customer::factory()->count(12)->sequence(fn ($s) => ['name' => 'Lan '.str_pad((string) $s->index, 2, '0', STR_PAD_LEFT)])
            ->has(Pet::factory()->state(['allergies' => 'Gà']))->create();
    });

    $this->actingAs($this->owner)->getJson('/app/paw-test/customers/search?q=lan')->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('data.0.name', 'Lan 00')
        ->assertJsonPath('data.0.pets_count', 1)
        ->assertJsonPath('data.0.pets.0.alerts.0.text', 'Gà');

    $this->actingAs($this->owner)->getJson('/app/paw-test/customers/search?q=l')->assertOk()->assertJsonCount(0, 'data');
});

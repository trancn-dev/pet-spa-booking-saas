<?php

declare(strict_types=1);

use App\Actions\Invitations\AcceptInvitation;
use App\Actions\Services\DeleteService;
use App\Actions\Services\SavePriceRule;
use App\Actions\Services\SaveService;
use App\Enums\PetSize;
use App\Enums\ServiceGroup;
use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\Branch;
use App\Models\Breed;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ServicePriceRule;
use App\Models\Species;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Task 5.2: S9 services, spa-only combos, branches offering a service, duration / price rules.
*/

beforeEach(function () {
    $this->withoutVite();
    $this->shop = provisionShop(['plan' => Plan::factory()->create(['max_branches' => 3])]);
    $this->tenant = $this->shop->tenant;
    $this->owner = User::factory()->create(['email' => 'owner@paw.test']);
    app(AcceptInvitation::class)->handle($this->tenant, $this->shop->invitation, $this->owner);
    $this->q1 = inTenant($this->tenant, fn () => Branch::query()->sole());
    $this->dog = Species::query()->firstOrCreate(['code' => 'dog'], ['name' => 'Chó']);
    $this->make = fn (array $a = []) => inTenant($this->tenant, fn () => Service::factory()->create($a));
    $this->bath = ($this->make)(['code' => 'TAM', 'name' => 'Tắm', 'service_group' => ServiceGroup::Grooming, 'duration_min' => 45, 'price' => '150000.00']);
    $this->trim = ($this->make)(['code' => 'CAT', 'name' => 'Cắt tỉa', 'service_group' => ServiceGroup::Grooming, 'duration_min' => 60, 'price' => '200000.00']);
    $this->exam = ($this->make)(['code' => 'KHAM', 'name' => 'Khám', 'service_group' => ServiceGroup::Medical]);
    $this->payload = fn (array $o = []) => [
        'code' => 'combo-1', 'name' => 'Tắm + cắt tỉa', 'service_group' => 'grooming', 'is_combo' => true,
        'duration_min' => 105, 'buffer_min' => 15, 'price' => '300000', 'is_public' => true, 'is_active' => true,
        'branch_ids' => [], 'item_ids' => [$this->trim->id, $this->bath->id], ...$o,
    ];
    $this->save = fn (?Service $s, array $o = []) => inTenant($this->tenant, fn () => app(SaveService::class)->handle($s, ($this->payload)($o)));
});

it('creates a spa combo with its items in order and its own price', function () {
    $this->actingAs($this->owner)->post('/app/paw-test/services', ($this->payload)())->assertRedirect();

    $combo = inTenant($this->tenant, fn () => Service::query()->where('code', 'COMBO-1')->with('comboItems')->sole());
    expect($combo->is_combo)->toBeTrue()
        ->and($combo->price)->toBe('300000.00')
        ->and($combo->comboItems->pluck('code')->all())->toBe(['CAT', 'TAM'])
        ->and(DB::table('service_combo_items')->where('combo_service_id', $combo->id)->pluck('tenant_id')->unique()->all())->toBe([$this->tenant->id]);
});

it('refuses a combo that is medical, contains a medical service, another combo, or one item', function (Closure $overrides, string $field, ?string $message) {
    $this->actingAs($this->owner)->post('/app/paw-test/services', ($this->payload)($overrides($this)))
        ->assertSessionHasErrors($message === null ? [$field] : [$field => $message]);

    expect(inTenant($this->tenant, fn () => Service::query()->where('code', 'COMBO-1')->exists()))->toBeFalse();
})->with([
    'combo itself medical' => [fn ($t) => ['service_group' => 'medical'], 'service_group', SaveService::COMBO_NOT_GROOMING],
    'medical item' => [fn ($t) => ['item_ids' => [$t->bath->id, $t->exam->id]], 'item_ids', SaveService::ITEM_NOT_GROOMING],
    'nested combo' => [fn ($t) => ['item_ids' => [$t->bath->id, ($t->make)(['code' => 'C0', 'service_group' => ServiceGroup::Grooming, 'is_combo' => true])->id]], 'item_ids', SaveService::NESTED_COMBO],
    'single item' => [fn ($t) => ['item_ids' => [$t->bath->id]], 'item_ids', null],
]);

it('refuses an item from another shop', function () {
    $other = provisionShop(['name' => 'Other', 'slug' => 'other-shop', 'ownerEmail' => 'o@other.test']);
    $foreign = inTenant($other->tenant, fn () => Service::factory()->create(['service_group' => ServiceGroup::Grooming]));

    expect(fn () => ($this->save)(null, ['item_ids' => [$this->bath->id, $foreign->id]]))
        ->toThrow(ValidationException::class);
});

it('keeps a service inside a combo from becoming medical or a combo', function () {
    ($this->save)(null);

    $edit = fn (array $o) => inTenant($this->tenant, fn () => app(SaveService::class)->handle($this->bath, [
        'code' => 'TAM', 'name' => 'Tắm', 'service_group' => 'grooming', 'is_combo' => false, 'duration_min' => 45, 'buffer_min' => 0,
        'price' => '150000', 'is_public' => true, 'is_active' => true, 'branch_ids' => [], 'item_ids' => [], ...$o,
    ]));

    expect(fn () => $edit(['service_group' => 'medical']))->toThrow(ValidationException::class)
        ->and(fn () => $edit(['is_combo' => true, 'item_ids' => [$this->trim->id, ($this->make)(['service_group' => ServiceGroup::Grooming])->id]]))
        ->toThrow(ValidationException::class)
        ->and($edit(['name' => 'Tắm thơm'])->name)->toBe('Tắm thơm');
});

it('turns a combo back into a plain service without its items', function () {
    $combo = ($this->save)(null);

    ($this->save)($combo, ['is_combo' => false, 'item_ids' => []]);

    expect(inTenant($this->tenant, fn () => $combo->comboItems()->count()))->toBe(0);
});

it('rejects a taken code, including a deleted service code', function () {
    $this->actingAs($this->owner)->post('/app/paw-test/services', ($this->payload)(['code' => 'tam', 'is_combo' => false, 'item_ids' => []]))
        ->assertSessionHasErrors(['code' => 'Mã này đã có dịch vụ dùng.']);

    inTenant($this->tenant, fn () => $this->exam->delete());
    $this->actingAs($this->owner)->post('/app/paw-test/services', ($this->payload)(['code' => 'KHAM', 'service_group' => 'medical', 'is_combo' => false, 'item_ids' => []]))
        ->assertSessionHasErrors(['code' => 'Mã này thuộc một dịch vụ đã xóa.']);
});

it('offers a service at every branch unless branches are chosen', function () {
    $q2 = inTenant($this->tenant, fn () => Branch::factory()->create(['code' => 'Q2']));
    $onlyQ2 = ($this->save)(null, ['code' => 'Q2ONLY', 'is_combo' => false, 'item_ids' => [], 'branch_ids' => [$q2->id]]);

    $at = fn (Branch $b) => inTenant($this->tenant, fn () => Service::query()->availableAt($b)->pluck('code')->sort()->values()->all());

    expect($at($this->q1))->toBe(['CAT', 'KHAM', 'TAM'])
        ->and($at($q2))->toBe(['CAT', 'KHAM', 'Q2ONLY', 'TAM'])
        ->and(inTenant($this->tenant, fn () => $onlyQ2->branches()->pluck('branches.id')->all()))->toBe([$q2->id]);
});

it('refuses to delete a service inside a combo or still booked', function () {
    ($this->save)(null);
    $delete = fn (Service $s) => inTenant($this->tenant, fn () => app(DeleteService::class)->handle($s));

    expect(fn () => $delete($this->bath))->toThrow(ValidationException::class);

    inTenant($this->tenant, function () {
        $appointment = Appointment::factory()->create(['branch_id' => $this->q1->id]);
        AppointmentItem::factory()->create(['appointment_id' => $appointment->id, 'service_id' => $this->exam->id]);
    });
    expect(fn () => $delete($this->exam))->toThrow(ValidationException::class, DeleteService::HAS_UPCOMING);

    $free = ($this->make)(['code' => 'FREE']);
    $delete($free);
    expect($free->refresh()->trashed())->toBeTrue();
});

it('saves rules, refusing duplicates with empty columns and a breed of another species', function () {
    $cat = Species::query()->firstOrCreate(['code' => 'cat'], ['name' => 'Mèo']);
    $poodle = Breed::query()->create(['species_id' => $this->dog->id, 'name' => 'Poodle']);
    $rule = fn (array $d, ?ServicePriceRule $r = null) => inTenant($this->tenant, fn () => app(SavePriceRule::class)->handle($this->bath, $r, [
        'species_id' => null, 'breed_id' => null, 'size' => null, 'duration_min' => null, 'price' => null, ...$d,
    ]));

    $first = $rule(['species_id' => $this->dog->id, 'size' => 'l', 'duration_min' => 90]);
    expect($first->size)->toBe(PetSize::L)
        ->and(fn () => $rule(['species_id' => $this->dog->id, 'size' => 'l', 'price' => '1']))->toThrow(ValidationException::class, SavePriceRule::DUPLICATE)
        ->and(fn () => $rule(['species_id' => $cat->id, 'breed_id' => $poodle->id, 'price' => '1']))->toThrow(ValidationException::class)
        ->and(fn () => $rule(['duration_min' => 30]))->toThrow(ValidationException::class)
        ->and(fn () => $rule(['species_id' => $this->dog->id]))->toThrow(ValidationException::class);

    // The breed sets the species; editing a rule onto itself is not a duplicate.
    $byBreed = $rule(['breed_id' => $poodle->id, 'price' => '180000']);
    expect($byBreed->species_id)->toBe($this->dog->id)
        ->and($rule(['species_id' => $this->dog->id, 'size' => 'l', 'duration_min' => 95], $first)->duration_min)->toBe(95);
});

it('lists services and shows one with its rules and a quote', function () {
    ($this->save)(null);
    inTenant($this->tenant, fn () => ServicePriceRule::factory()->create(['service_id' => $this->bath->id, 'species_id' => $this->dog->id, 'size' => PetSize::L, 'duration_min' => 75, 'price' => '220000.00']));

    $this->actingAs($this->owner)->get('/app/paw-test/services?group=grooming')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Services/Index')
            ->has('services', 3)
            ->where('services', fn ($rows) => collect($rows)->firstWhere('code', 'COMBO-1')['items'] === ['Cắt tỉa', 'Tắm']));

    $this->actingAs($this->owner)->get("/app/paw-test/services/{$this->bath->id}")->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('App/Services/Show')->where('rules.0.label', 'Chó cỡ L'));

    $this->actingAs($this->owner)->getJson("/app/paw-test/services/{$this->bath->id}/quote?species_id={$this->dog->id}&size=l")
        ->assertOk()->assertJsonPath('data.duration_min', 75)->assertJsonPath('data.price', '220000.00')
        ->assertJsonPath('data.explanation', '75 phút · áp dụng cho Chó cỡ L');
});

it('lets every service viewer see the list but only the owner change it', function (string $role) {
    $user = memberWithRoles($this->tenant, [$role], [$this->q1]);

    $this->actingAs($user)->get('/app/paw-test/services')->assertOk();
    $this->actingAs($user)->get("/app/paw-test/services/{$this->bath->id}")->assertOk();
    $this->actingAs($user)->post('/app/paw-test/services', ($this->payload)())->assertForbidden();
    $this->actingAs($user)->post("/app/paw-test/services/{$this->bath->id}/rules", ['species_id' => $this->dog->id, 'duration_min' => 30])->assertForbidden();
    $this->actingAs($user)->delete("/app/paw-test/services/{$this->bath->id}")->assertForbidden();
})->with(['branch_manager', 'receptionist', 'cashier', 'vet', 'groomer', 'inventory']);

it('isolates services and rules between shops', function () {
    $other = provisionShop(['name' => 'Other', 'slug' => 'other-shop', 'ownerEmail' => 'o@other.test']);
    [$s, $r] = inTenant($other->tenant, function () {
        $s = Service::factory()->create();

        return [$s, ServicePriceRule::factory()->create(['service_id' => $s->id])];
    });

    assertTenantIsolated($this->owner, [
        "GET /app/paw-test/services/{$s->id}",
        "GET /app/paw-test/services/{$s->id}/edit",
        "PUT /app/paw-test/services/{$s->id}",
        "DELETE /app/paw-test/services/{$s->id}",
        "GET /app/paw-test/services/{$s->id}/quote",
        "POST /app/paw-test/services/{$s->id}/rules",
        "PUT /app/paw-test/services/{$this->bath->id}/rules/{$r->id}",
        "DELETE /app/paw-test/services/{$this->bath->id}/rules/{$r->id}",
    ], ($this->payload)(['species_id' => $this->dog->id, 'duration_min' => 30]));

    expect(inTenant($other->tenant, fn () => ServicePriceRule::query()->count()))->toBe(1);
});

<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ServiceGroup;
use App\Enums\TenantStatus;
use App\Models\Appointment;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Staff;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Facades\DB;

/*
| The demo dataset of design.md section 9 (task 3.2).
*/

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->paw = Tenant::query()->where('slug', 'paw-house')->firstOrFail();
    $this->today = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfDay();
});

it('seeds reference data idempotently', function () {
    $breeds = DB::table('breeds')->count();

    $this->seed(ReferenceDataSeeder::class);

    expect(Plan::query()->orderBy('id')->pluck('code')->all())->toBe(['starter', 'pro', 'clinic'])
        ->and(Plan::query()->where('code', 'clinic')->value('max_staff'))->toBeNull()
        ->and(DB::table('breeds')->count())->toBe($breeds)
        ->and(DB::table('species')->pluck('code')->sort()->values()->all())->toBe(['cat', 'dog']);
});

it('creates the four demo shops with their plan states', function () {
    $status = Tenant::query()->pluck('status', 'slug')->map(fn (TenantStatus $s) => $s->value)->all();

    expect($status)->toBe([
        'paw-house' => 'active',
        'meow-clinic' => 'past_due',
        'happy-tails' => 'trial',
        'be-cun-spa' => 'suspended',
    ]);
});

it('stores shop-local times in UTC (08:00 in Ho Chi Minh City = 01:00 UTC)', function () {
    $first = DB::table('appointments')->where('tenant_id', $this->paw->id)->min('starts_at');

    expect($first)->toBe($this->today->setTime(8, 0)->utc()->format('Y-m-d H:i:s'))
        ->and(substr((string) $first, 11))->toBe('01:00:00');
});

it('seeds today at Q3 as described in design section 9', function () {
    inTenant($this->paw, function () {
        $todayUtc = [$this->today->utc(), $this->today->addDay()->utc()];

        $appointments = Appointment::query()->whereBetween('starts_at', $todayUtc)->withCount('items')->get();
        $visits = Visit::query()->where('visit_date', $this->today->toDateString())->get();

        expect(Staff::query()->count())->toBe(9)
            ->and(Staff::query()->whereNull('user_id')->value('display_name'))->toBe('Lê Văn Tư')
            ->and($appointments)->toHaveCount(14)
            ->and($appointments->pluck('status')->unique()->sortBy->value->values()->all())
            ->toEqualCanonicalizing(AppointmentStatus::cases())
            ->and($appointments->where('items_count', 2))->toHaveCount(1)
            ->and($visits)->toHaveCount(9)
            ->and($visits->where('service_group', ServiceGroup::Medical))->toHaveCount(6)
            ->and($visits->where('service_group', ServiceGroup::Grooming))->toHaveCount(3)
            ->and($visits->where('is_emergency', true))->toHaveCount(1)
            ->and(Invoice::query()->where('status', InvoiceStatus::Draft)->where('business_date', '<', $this->today->toDateString())->count())->toBe(2)
            ->and(Invoice::query()->where('status', InvoiceStatus::Completed)->count())->toBeGreaterThan(0)
            ->and(CashSession::query()->where('status', 'open')->count())->toBe(1);
    });
});

it('keeps a customer with the same phone separate in each shop', function () {
    $meow = Tenant::query()->where('slug', 'meow-clinic')->firstOrFail();

    $pawNotes = inTenant($this->paw, fn () => Customer::query()->where('phone', '+84903123456')->pluck('notes')->all());
    $meowNotes = inTenant($meow, fn () => Customer::query()->where('phone', '+84903123456')->pluck('notes')->all());

    expect($pawNotes)->toBe([null])->and($meowNotes)->toBe(['Khách của Meow Clinic']);
});

it('lets the demo owner sign in to their shop only', function () {
    $this->withoutVite();
    $owner = User::query()->where('email', 'khoa@pawhouse.test')->firstOrFail();

    $this->actingAs($owner)->get('/app/paw-house/dashboard')->assertOk();
    $this->actingAs($owner)->get('/app/meow-clinic/dashboard')->assertNotFound();
});

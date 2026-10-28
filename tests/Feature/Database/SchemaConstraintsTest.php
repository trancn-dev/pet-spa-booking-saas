<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\CashDrawer;
use App\Models\Customer;
use App\Models\Pet;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Database-level guarantees (db-schema section 1, 2.5–2.7): even buggy code that skips the
| Eloquent layer cannot attach one shop's data to another, double-open a draft or a till, etc.
| Rows are inserted with DB::table() on purpose, bypassing models and global scopes.
*/

beforeEach(function () {
    $this->a = Tenant::factory()->create();
    $this->b = Tenant::factory()->create();

    [$this->branchA, $this->customerA, $this->petA, $this->serviceA, $this->staffA] = inTenant($this->a, function () {
        $branch = Branch::factory()->create(['code' => 'Q3']);
        $customer = Customer::factory()->create();

        return [$branch, $customer, Pet::factory()->of($customer)->create(), Service::factory()->create(), Staff::factory()->create(['primary_branch_id' => $branch->id])];
    });

    [$this->branchB, $this->customerB] = inTenant($this->b, fn () => [Branch::factory()->create(), Customer::factory()->create()]);

    $this->insert = function (string $table, array $row): int {
        return (int) DB::table($table)->insertGetId($row);
    };

    $this->petRow = fn (array $overrides = []): array => [
        'public_id' => (string) Str::ulid(),
        'tenant_id' => $this->a->id,
        'customer_id' => $this->customerA->id,
        'species_id' => $this->petA->species_id,
        'name' => 'Mochi',
        'size' => 'm',
        ...$overrides,
    ];

    $this->appointmentRow = fn (array $overrides = []): array => [
        'public_id' => (string) Str::ulid(),
        'tenant_id' => $this->a->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'starts_at' => '2026-11-02 02:00:00',
        'ends_at' => '2026-11-02 02:30:00',
        'source' => 'staff',
        ...$overrides,
    ];

    $this->itemRow = fn (int $appointmentId, array $overrides = []): array => [
        'tenant_id' => $this->a->id,
        'appointment_id' => $appointmentId,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'pet_id' => $this->petA->id,
        'service_id' => $this->serviceA->id,
        'service_group' => 'medical',
        'starts_at' => '2026-11-02 02:00:00',
        'ends_at' => '2026-11-02 02:30:00',
        'duration_min' => 30,
        'reference_price' => '200000.00',
        ...$overrides,
    ];

    $this->invoiceRow = fn (array $overrides = []): array => [
        'public_id' => (string) Str::ulid(),
        'tenant_id' => $this->a->id,
        'branch_id' => $this->branchA->id,
        'kind' => 'service',
        'customer_id' => $this->customerA->id,
        'status' => 'draft',
        'business_date' => '2026-11-02',
        ...$overrides,
    ];

    $this->visitRow = fn (array $overrides = []): array => [
        'public_id' => (string) Str::ulid(),
        'tenant_id' => $this->a->id,
        'branch_id' => $this->branchA->id,
        'customer_id' => $this->customerA->id,
        'pet_id' => $this->petA->id,
        'service_group' => 'medical',
        'visit_date' => '2026-11-02',
        'queue_no' => 1,
        'priority_at' => '2026-11-02 02:00:00',
        'checked_in_at' => '2026-11-02 02:00:00',
        ...$overrides,
    ];
});

describe('composite tenant foreign keys', function () {
    it('rejects a pet of shop A attached to a customer of shop B', function () {
        ($this->insert)('pets', ($this->petRow)(['customer_id' => $this->customerB->id]));
    })->throws(QueryException::class);

    it('rejects an appointment of shop A at a branch of shop B', function () {
        ($this->insert)('appointments', ($this->appointmentRow)(['branch_id' => $this->branchB->id]));
    })->throws(QueryException::class);

    it('rejects an appointment item whose pet belongs to another customer', function () {
        $otherPet = inTenant($this->a, fn () => Pet::factory()->create());
        $appointmentId = ($this->insert)('appointments', ($this->appointmentRow)());

        ($this->insert)('appointment_items', ($this->itemRow)($appointmentId, ['pet_id' => $otherPet->id]));
    })->throws(QueryException::class);

    it('rejects an appointment item whose customer / branch drift from the appointment', function () {
        $appointmentId = ($this->insert)('appointments', ($this->appointmentRow)());
        $otherBranch = inTenant($this->a, fn () => Branch::factory()->create());

        expect(fn () => ($this->insert)('appointment_items', ($this->itemRow)($appointmentId, ['branch_id' => $otherBranch->id])))
            ->toThrow(QueryException::class);

        $otherCustomer = inTenant($this->a, fn () => Customer::factory()->create());
        $theirPet = inTenant($this->a, fn () => Pet::factory()->of($otherCustomer)->create());

        expect(fn () => ($this->insert)('appointment_items', ($this->itemRow)($appointmentId, [
            'customer_id' => $otherCustomer->id,
            'pet_id' => $theirPet->id,
        ])))->toThrow(QueryException::class);
    });

    it('accepts a consistent multi-pet appointment', function () {
        $secondPet = inTenant($this->a, fn () => Pet::factory()->of($this->customerA)->create());
        $appointmentId = ($this->insert)('appointments', ($this->appointmentRow)());

        ($this->insert)('appointment_items', ($this->itemRow)($appointmentId));
        ($this->insert)('appointment_items', ($this->itemRow)($appointmentId, ['pet_id' => $secondPet->id]));

        expect(DB::table('appointment_items')->where('appointment_id', $appointmentId)->count())->toBe(2);
    });
});

describe('invoices', function () {
    it('allows only one open service draft per customer, branch and day', function () {
        ($this->insert)('invoices', ($this->invoiceRow)());
        ($this->insert)('invoices', ($this->invoiceRow)());
    })->throws(QueryException::class);

    it('allows a new service draft once the previous one is completed', function () {
        $first = ($this->insert)('invoices', ($this->invoiceRow)());
        DB::table('invoices')->where('id', $first)->update(['status' => 'completed', 'code' => 'Q3-20261102-001']);

        ($this->insert)('invoices', ($this->invoiceRow)());

        expect(DB::table('invoices')->where('tenant_id', $this->a->id)->count())->toBe(2);
    });

    it('lets a retail draft and stray invoices with a payer coexist with the service draft', function () {
        ($this->insert)('invoices', ($this->invoiceRow)());
        ($this->insert)('invoices', ($this->invoiceRow)(['kind' => 'retail']));

        $stray = inTenant($this->a, fn () => Customer::factory()->system($this->branchA)->create());
        ($this->insert)('invoices', ($this->invoiceRow)(['customer_id' => $stray->id, 'payer_name' => 'Anh Tuấn']));
        ($this->insert)('invoices', ($this->invoiceRow)(['customer_id' => $stray->id, 'payer_name' => 'Chị Hoa']));

        expect(DB::table('invoices')->where('tenant_id', $this->a->id)->count())->toBe(4);
    });

    it('requires a customer on a service invoice', function () {
        ($this->insert)('invoices', ($this->invoiceRow)(['customer_id' => null]));
    })->throws(QueryException::class);
});

describe('payments and till sessions', function () {
    beforeEach(function () {
        $this->drawer = inTenant($this->a, fn () => CashDrawer::factory()->create(['branch_id' => $this->branchA->id]));

        $this->sessionRow = fn (): array => [
            'tenant_id' => $this->a->id,
            'branch_id' => $this->branchA->id,
            'drawer_id' => $this->drawer->id,
            'status' => 'open',
            'opened_by' => $this->staffA->id,
            'opened_at' => '2026-11-02 01:00:00',
        ];

        $this->paymentRow = fn (array $overrides = []): array => [
            'tenant_id' => $this->a->id,
            'invoice_id' => ($this->insert)('invoices', ($this->invoiceRow)(['kind' => 'retail', 'customer_id' => null])),
            'kind' => 'payment',
            'method' => 'cash',
            'amount' => '100000.00',
            'received_by_staff_id' => $this->staffA->id,
            'paid_at' => '2026-11-02 03:00:00',
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => '2026-11-02 03:00:00',
            ...$overrides,
        ];
    });

    it('rejects a cash payment without a till session', function () {
        ($this->insert)('payments', ($this->paymentRow)());
    })->throws(QueryException::class);

    it('accepts cash with a session, and transfer entries without one', function () {
        $session = ($this->insert)('cash_sessions', ($this->sessionRow)());

        ($this->insert)('payments', ($this->paymentRow)(['cash_session_id' => $session]));
        ($this->insert)('payments', ($this->paymentRow)(['kind' => 'transfer_in']));

        expect(DB::table('payments')->count())->toBe(2);
    });

    it('rejects a zero or negative amount', function () {
        ($this->insert)('payments', ($this->paymentRow)(['method' => 'card', 'amount' => '0.00']));
    })->throws(QueryException::class);

    it('allows only one open session per drawer', function () {
        ($this->insert)('cash_sessions', ($this->sessionRow)());
        ($this->insert)('cash_sessions', ($this->sessionRow)());
    })->throws(QueryException::class);

    it('opens a new session once the previous one is closed', function () {
        $first = ($this->insert)('cash_sessions', ($this->sessionRow)());
        DB::table('cash_sessions')->where('id', $first)->update(['status' => 'closed', 'closed_at' => '2026-11-02 12:00:00']);

        ($this->insert)('cash_sessions', ($this->sessionRow)());

        expect(DB::table('cash_sessions')->where('drawer_id', $this->drawer->id)->count())->toBe(2);
    });
});

describe('visits and queue', function () {
    it('rejects two visits with the same queue number at a branch on a day', function () {
        ($this->insert)('visits', ($this->visitRow)());
        ($this->insert)('visits', ($this->visitRow)());
    })->throws(QueryException::class);

    it('accepts an emergency visit without an owner or pet profile yet', function () {
        ($this->insert)('visits', ($this->visitRow)([
            'customer_id' => null, 'pet_id' => null, 'is_emergency' => true, 'emergency_label' => 'Chó cấp cứu 14:05',
        ]));

        expect(DB::table('visits')->count())->toBe(1);
    });

    it('rejects a non-emergency visit without a pet', function () {
        ($this->insert)('visits', ($this->visitRow)(['pet_id' => null]));
    })->throws(QueryException::class);

    it('rejects a visit for a pet of another customer', function () {
        $otherPet = inTenant($this->a, fn () => Pet::factory()->create());

        ($this->insert)('visits', ($this->visitRow)(['pet_id' => $otherPet->id]));
    })->throws(QueryException::class);
});

describe('customers and invitations', function () {
    it('requires a phone unless the customer is the system (stray) profile', function () {
        ($this->insert)('customers', [
            'public_id' => (string) Str::ulid(), 'tenant_id' => $this->a->id, 'name' => 'No phone', 'phone' => null,
        ]);
    })->throws(QueryException::class);

    it('keeps one phone per shop but lets two shops share a phone', function () {
        $row = fn (Tenant $t) => ['public_id' => (string) Str::ulid(), 'tenant_id' => $t->id, 'name' => 'X', 'phone' => '+84901234567'];

        ($this->insert)('customers', $row($this->a));
        ($this->insert)('customers', $row($this->b));

        expect(fn () => ($this->insert)('customers', $row($this->a)))->toThrow(QueryException::class)
            ->and(DB::table('customers')->where('phone', '+84901234567')->value('phone_suffix'))->toBe('4567');
    });

    it('requires exactly one inviter on an invitation', function () {
        ($this->insert)('invitations', [
            'tenant_id' => $this->a->id, 'email' => 'x@example.com', 'role_names' => '["receptionist"]',
            'token_hash' => hash('sha256', 'x'), 'expires_at' => '2026-11-05 00:00:00',
        ]);
    })->throws(QueryException::class);
});

it('exposes the appointment relations and enum casts', function () {
    $appointment = inTenant($this->a, fn () => Appointment::factory()->create());

    expect($appointment->items())->toBeInstanceOf(HasMany::class)
        ->and($appointment->changes())->toBeInstanceOf(HasMany::class)
        ->and($appointment->status)->toBe(AppointmentStatus::Confirmed);
});

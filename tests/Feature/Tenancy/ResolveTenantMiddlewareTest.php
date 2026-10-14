<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['tenancy.central_domain' => 'spa.test']);

    Route::middleware('tenant')->get('/__tenant/users', fn () => User::pluck('email'));

    $a = Tenant::factory()->create(['slug' => 'a']);
    $b = Tenant::factory()->create(['slug' => 'b', 'domain' => 'booking.bee.com']);
    User::factory()->create(['tenant_id' => $a->id, 'email' => 'a@example.com']);
    User::factory()->create(['tenant_id' => $b->id, 'email' => 'b@example.com']);
});

it('serves only the data of the tenant resolved from the subdomain', function () {
    $this->get('http://a.spa.test/__tenant/users')->assertOk()->assertExactJson(['a@example.com']);
});

it('serves only the data of the tenant resolved from a custom domain', function () {
    $this->get('http://booking.bee.com/__tenant/users')->assertOk()->assertExactJson(['b@example.com']);
});

it('returns 404 for unknown tenants and hosts', function (string $url) {
    $this->get($url)->assertNotFound();
})->with([
    'unknown subdomain' => 'http://nope.spa.test/__tenant/users',
    'central domain' => 'http://spa.test/__tenant/users',
    'nested subdomain' => 'http://x.a.spa.test/__tenant/users',
    'foreign host' => 'http://evil.com/__tenant/users',
]);

it('does not leak tenant context between requests', function () {
    $this->get('http://a.spa.test/__tenant/users')->assertOk();
    $this->get('http://nope.spa.test/__tenant/users')->assertNotFound();
    $this->get('http://booking.bee.com/__tenant/users')->assertExactJson(['b@example.com']);
});

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsOnTenant;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use ActsOnTenant, RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/app/acme/dashboard')->assertRedirect('/login');
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = $this->tenantUser();
        $this->actingAs($user);

        $this->get('/dashboard')->assertRedirect('/app/acme/dashboard');
        $this->get('/app/acme/dashboard')->assertStatus(200);
    }
}

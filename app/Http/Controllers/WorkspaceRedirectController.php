<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Tenancy\TenantMembership;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkspaceRedirectController extends Controller
{
    public function __invoke(Request $request, TenantMembership $membership): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $tenant = $membership->defaultTenantFor($user);

        // No workspace yet (creating one is a later task): land on home.
        return $tenant === null
            ? to_route('home')
            : to_route('tenant.dashboard', ['tenant' => $tenant->slug]);
    }
}

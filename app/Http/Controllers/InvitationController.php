<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Invitations\AcceptInvitation;
use App\Enums\InvitationState;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\InvitationLookup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * S13 `/invite/{token}` (central route, owner and staff invitations). No account yet → name +
 * password on this page; account exists → sign in with that e-mail, then accept.
 */
class InvitationController extends Controller
{
    public function __construct(private readonly InvitationLookup $lookup) {}

    public function show(Request $request, string $token): Response
    {
        $found = $this->lookup->find($token);

        if ($found === null) {
            return Inertia::render('Invitations/Show', ['state' => 'invalid', 'message' => 'Link mời không hợp lệ.']);
        }

        [$invitation, $tenant] = $found;
        $hasAccount = User::query()->where('email', $invitation->email)->exists();
        $user = $request->user();

        if ($user === null && $hasAccount) {
            // "Đăng nhập để nhận": come back here after login.
            $request->session()->put('url.intended', route('invitations.show', $token));
        }

        return Inertia::render('Invitations/Show', [
            'state' => $invitation->state()->value,
            'message' => AcceptInvitation::message($invitation->state()),
            'token' => $token,
            'shop' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'roles' => $this->roleLabels($tenant, $invitation),
            'email' => $invitation->email,
            'hasAccount' => $hasAccount,
            'signedInAs' => $user instanceof User ? $user->email : null,
        ]);
    }

    public function accept(Request $request, string $token, AcceptInvitation $accept): RedirectResponse
    {
        $found = $this->lookup->find($token);
        if ($found === null) {
            throw ValidationException::withMessages(['invitation' => 'Link mời không hợp lệ.']);
        }
        [$invitation, $tenant] = $found;

        $user = $request->user();

        if (! $user instanceof User) {
            if (User::query()->where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages(['email' => 'Email này đã có tài khoản. Đăng nhập để nhận lời mời.']);
            }

            if ($invitation->state() !== InvitationState::Pending) {
                throw ValidationException::withMessages(['invitation' => AcceptInvitation::message($invitation->state())]);
            }

            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
            ]);

            $user = DB::transaction(function () use ($data, $invitation, $tenant, $accept): User {
                $user = User::query()->create([
                    'name' => $data['name'],
                    'email' => $invitation->email,
                    'password' => $data['password'],
                ]);
                $accept->handle($tenant, $invitation, $user);

                return $user;
            });

            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        } else {
            $accept->handle($tenant, $invitation, $user);
        }

        return to_route('app.home', ['tenant' => $tenant->slug]);
    }

    /**
     * @return list<string>
     */
    private function roleLabels(Tenant $tenant, Invitation $invitation): array
    {
        return Role::query()->where('team_id', $tenant->getKey())->whereIn('name', $invitation->role_names)
            ->orderBy('id')->pluck('display_name')->all();
    }
}

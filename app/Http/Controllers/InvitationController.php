<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use App\Support\Consent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Accepting a team invitation - reachable by someone who, by definition,
 * isn't authenticated into the inviting shop yet (and may have no account
 * at all, or be logged into a different one already). No auth/guest
 * middleware on these routes; every branch is handled explicitly below.
 */
class InvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = Invitation::where('token', $token)->first();

        abort_unless($invitation, 404);

        if ($invitation->isAccepted()) {
            return Inertia::render('Invitations/Accept', [
                'mode' => 'accepted',
                'shopName' => $invitation->shop->name,
            ]);
        }

        if ($invitation->isExpired()) {
            return Inertia::render('Invitations/Accept', [
                'mode' => 'expired',
                'shopName' => $invitation->shop->name,
            ]);
        }

        $existingUser = User::where('email', $invitation->email)->first();

        $mode = match (true) {
            Auth::check() && Auth::user()->email === $invitation->email => 'ready',
            $existingUser !== null => 'login-required',
            default => 'create-account',
        };

        return Inertia::render('Invitations/Accept', [
            'mode' => $mode,
            'token' => $token,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'shopName' => $invitation->shop->name,
            'agreementText' => Consent::AGREEMENT,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::where('token', $token)->first();

        abort_unless($invitation, 404);
        abort_if($invitation->isAccepted() || $invitation->isExpired(), 404);

        // The invite itself holds a seat, so it only fails here if the shop
        // shrank (a lowered limit) or filled up some other way since.
        $shopForSeats = $invitation->shop;
        $alreadyMember = $shopForSeats->members()->where('users.email', $invitation->email)->exists();

        if (! $alreadyMember && ! $shopForSeats->hasRoomForSeats(0)) {
            return back()->with('error', $shopForSeats->seatLimitMessage());
        }

        $existingUser = User::where('email', $invitation->email)->first();
        $alreadySignedInAsInvitee = Auth::check() && Auth::user()->email === $invitation->email;

        if (! $alreadySignedInAsInvitee && $existingUser) {
            $request->validate(['password' => ['required', 'string']]);

            if (! Auth::attempt(['email' => $invitation->email, 'password' => $request->password])) {
                throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
            }
        } elseif (! $alreadySignedInAsInvitee && ! $existingUser) {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Rules\Password::defaults()],
                'accepted_terms' => ['accepted'],
            ], ['accepted_terms.accepted' => 'You need to agree to the Terms of Service and Privacy Policy to create an account.']);

            $newUser = User::create([
                'name' => $validated['name'],
                'email' => $invitation->email,
                'password' => Hash::make($validated['password']),
            ]);

            // email_verified_at isn't mass-assignable (see User's Fillable
            // attribute) - same reasoning as current_shop_id, set directly.
            // Clicking a link mailed to this address is itself proof of
            // ownership, so no separate verification email is needed.
            $newUser->email_verified_at = now();
            $newUser->save();

            Auth::login($newUser);

            Consent::recordAgreement($newUser, 'invitation', $request, $invitation->shop);
        }

        $user = Auth::user();
        $shop = $invitation->shop;

        $shop->members()->syncWithoutDetaching([$user->id]);
        $user->current_shop_id = $shop->id;
        $user->save();

        app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);
        $user->assignRole(Role::findOrCreate($invitation->role));

        $invitation->update(['accepted_at' => now()]);

        return redirect()->route('dashboard')->with('status', "You've joined {$shop->name}.");
    }
}

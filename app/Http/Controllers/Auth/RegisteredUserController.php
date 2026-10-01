<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Consent;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', ['agreementText' => Consent::AGREEMENT]);
    }

    /**
     * Handle an incoming registration request.
     *
     * Creates just the account - no Shop yet. Building the shop (name,
     * timezone, plan) is onboarding's first step, which the `onboarded`
     * middleware routes a shop-less verified user into as soon as they log
     * in; registering itself only needs a name, email and password.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'accepted_terms' => ['accepted'],
        ], ['accepted_terms.accepted' => 'You need to agree to the Terms of Service and Privacy Policy to create an account.']);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Proof of what was agreed, to which version, from where.
        Consent::recordAgreement($user, 'registration', $request);

        // Triggers the verification email (App\Providers\AppServiceProvider
        // wires Registered -> SendEmailVerificationNotification).
        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}

<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use App\Support\ShopPermissions;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'shop_name' => 'required|string|max:255',
        ]);

        // Sent silently by the signup form from the browser's own timezone.
        // Something unrecognisable is dropped (falls back to the global
        // default, changeable later in Shop settings) rather than failing
        // signup over a field the person never saw.
        $timezone = in_array($request->input('timezone'), \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)
            ? $request->input('timezone')
            : null;

        $user = DB::transaction(function () use ($request, $timezone) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // The trial is a fixed UTC window from the moment of signup -
            // never derived from the shop's timezone, so changing timezone
            // later can't add (or cost) hours.
            $shop = Shop::create([
                'name' => $request->shop_name,
                'owner_id' => $user->id,
                'plan_id' => Plan::where('key', 'free')->value('id'),
                'timezone' => $timezone,
                'trial_started_at' => now(),
                'trial_ends_at' => now()->addDays(Shop::TRIAL_DAYS),
            ]);

            $shop->members()->attach($user);
            $user->current_shop_id = $shop->id;
            $user->save();

            app(PermissionRegistrar::class)->setPermissionsTeamId($shop->id);

            // Owner/Admin/Staff for this shop, with Admin/Staff's default grants.
            ShopPermissions::seedRoles($shop);

            $user->assignRole('Owner');

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\InstallSelfHostedShop;
use App\Support\Edition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The first-run page of a self-hosted install: whoever gets here first
 * creates the owner account and the shop. It closes permanently the moment
 * any account exists (see Edition::installed()), so it can't be used to take
 * over an install that is already in use.
 */
class SelfHostedSetupController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if (Edition::installed()) {
            return redirect()->route('login');
        }

        abort_unless($this->hasSetupKey($request), 404);

        return Inertia::render('Setup/Index', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'setupKey' => $request->query('key'),
        ]);
    }

    public function store(Request $request, InstallSelfHostedShop $install): RedirectResponse
    {
        // Before validating: a finished install should say so, not complain
        // about a form nobody should be filling in.
        if (Edition::installed()) {
            return redirect()->route('login');
        }

        abort_unless($this->hasSetupKey($request), 404);

        $request->merge(InstallSelfHostedShop::prepare($request->all()));

        $data = $request->validate(InstallSelfHostedShop::rules());

        $user = $install(collect($data)->only(['name', 'email', 'password', 'shop_name', 'timezone'])->all());

        Auth::login($user);
        $request->session()->regenerate();

        // `onboarded` sends them on into the short wizard.
        return redirect()->route('dashboard');
    }

    /**
     * With SELFHOSTED_SETUP_KEY set, only a visitor holding it may see or use
     * the page (a 404 otherwise, so a scanner can't even tell it is there).
     */
    private function hasSetupKey(Request $request): bool
    {
        $expected = config('shop.selfhosted_setup_key');

        return blank($expected) || hash_equals((string) $expected, (string) $request->input('key', ''));
    }
}

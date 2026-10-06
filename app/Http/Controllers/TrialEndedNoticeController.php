<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrialEndedNoticeController extends Controller
{
    /**
     * Records that the current member closed their shop's "trial ended"
     * notice. Scoped to the member and shop (shop_user pivot), never the
     * whole shop - one person closing it doesn't hide it from the others.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->shops()->updateExistingPivot($user->current_shop_id, [
            'trial_ended_dismissed_at' => now(),
        ]);

        return back();
    }
}

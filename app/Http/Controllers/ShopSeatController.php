<?php

namespace App\Http\Controllers;

use App\Support\Billing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Buying (or giving back) seats beyond what the plan includes. Owner-only
 * (can:manage-shop route middleware) - it changes what the shop pays.
 *
 * The page that shows the quote is ShopPlanController::index(); this only
 * applies a change. Like plans and add-ons, nothing is charged yet - no
 * payment processor is connected.
 */
class ShopSeatController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $shop = Auth::user()->currentShop;
        $plan = $shop->plan;

        $price = $plan?->extra_seat_price_cents;

        if ($plan === null || $price === null || $plan->user_limit === null) {
            throw ValidationException::withMessages(['extra_seats' => 'Your plan doesn\'t offer extra seats. Upgrade to add more people.']);
        }

        $validated = $request->validate([
            'extra_seats' => ['required', 'integer', 'min:0', 'max:'.config('shop.max_extra_seats')],
        ]);
        $extra = (int) $validated['extra_seats'];

        // Seats ride on the plan's paid period.
        if ($extra > $shop->extra_seats && Billing::periodEnd($shop) === null) {
            throw ValidationException::withMessages(['extra_seats' => 'Pick a plan first - seats are billed with it.']);
        }

        // Never below the people who already hold a seat.
        $needed = max(0, $shop->seatsUsed() - $plan->user_limit);

        if ($extra < $needed) {
            throw ValidationException::withMessages(['extra_seats' => "Your team has {$shop->seatsUsed()} people, so you need at least {$needed} extra seat".($needed === 1 ? '' : 's').'.']);
        }

        $shop->update(['extra_seats' => $extra]);

        return redirect()->route('shop.plan.index')->with('status', $extra === 0
            ? 'Extra seats removed.'
            : "Your shop now has {$shop->fresh()->seatLimit()} seats.");
    }
}

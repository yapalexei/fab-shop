<?php

namespace App\Http\Controllers;

use App\Enums\QuoteStatus;
use App\Models\Quote;
use App\Services\Ai\AiException;
use App\Services\Ai\QuoteFollowUpDrafter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Drafts a follow-up email for a sent quote that has gone quiet. Returns
 * JSON for the dashboard dialog and saves nothing; the owner edits the text
 * and sends it from their own mail app.
 */
class QuoteFollowUpController extends Controller
{
    public function __invoke(Request $request, Quote $quote, QuoteFollowUpDrafter $drafter): JsonResponse
    {
        $data = $request->validate([
            'tone' => ['nullable', Rule::in(QuoteFollowUpDrafter::TONES)],
        ]);

        if ($quote->status !== QuoteStatus::Sent || $quote->is_archived) {
            return response()->json(['message' => 'A follow-up only makes sense for a quote that has been sent and is still open.'], 422);
        }

        $user = $request->user();
        $quote->load('customer:id,name,email', 'lineItems');

        try {
            $draft = $drafter->draft(
                $user->currentShop,
                $user,
                $quote,
                $data['tone'] ?? 'friendly',
                includeTotal: $user->hasShopPermission('financials.view'),
            );
        } catch (AiException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([...$draft, 'to' => $quote->customer->email]);
    }
}

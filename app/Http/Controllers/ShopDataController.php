<?php

namespace App\Http\Controllers;

use App\Jobs\BuildShopDataExport;
use App\Models\DataExport;
use App\Models\User;
use App\Notifications\ShopDeletionChanged;
use App\Support\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The owner's data controls on the shop settings page: download everything the
 * shop has stored, or ask for the shop to be erased. Owner-only (`manage-shop`)
 * - both decide what happens to the whole shop's data.
 */
class ShopDataController extends Controller
{
    public function export(Request $request): RedirectResponse
    {
        $busy = DataExport::where('status', 'pending')->where('created_at', '>', now()->subHour())->exists();

        if ($busy) {
            return back()->with('error', 'An export is already being prepared. It will appear below when it is ready.');
        }

        $export = DataExport::create(['requested_by' => $request->user()->id]);
        BuildShopDataExport::dispatch($export->id);
        SecurityLog::record('data export requested', $request->user());

        return back()->with('status', 'Preparing your export. We will email you when it is ready.');
    }

    public function download(DataExport $export): StreamedResponse
    {
        abort_unless($export->isDownloadable(), 404);

        SecurityLog::record('data export downloaded', Auth::user(), ['export_id' => $export->id]);

        return Storage::download($export->path, 'shop-data-'.now()->format('Y-m-d').'.zip');
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $shop = $request->user()->currentShop;

        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm_name' => ['required', 'string'],
        ]);

        if ($request->input('confirm_name') !== $shop->name) {
            throw ValidationException::withMessages(['confirm_name' => 'Type the shop name exactly as shown to confirm.']);
        }

        if ($shop->deletion_scheduled_for) {
            return back();
        }

        $shop->forceFill([
            'deletion_requested_at' => now(),
            'deletion_scheduled_for' => now()->addDays((int) config('shop.deletion_grace_days')),
        ])->save();

        SecurityLog::record('shop deletion requested', $request->user(), [
            'scheduled_for' => $shop->deletion_scheduled_for->toIso8601String(),
        ]);

        $shop->members()->get()->each(fn (User $member) => $member->notify(new ShopDeletionChanged($shop->name, $shop->deletion_scheduled_for)));

        return back()->with('status', 'Deletion scheduled for '.$shop->deletion_scheduled_for->format('M j, Y').'. You can cancel until then.');
    }

    public function cancelDeletion(Request $request): RedirectResponse
    {
        $shop = $request->user()->currentShop;

        if (! $shop->deletion_scheduled_for) {
            return back();
        }

        $shop->forceFill(['deletion_requested_at' => null, 'deletion_scheduled_for' => null])->save();

        SecurityLog::record('shop deletion cancelled', $request->user());

        $shop->members()->get()->each(fn (User $member) => $member->notify(new ShopDeletionChanged($shop->name)));

        return back()->with('status', 'Deletion cancelled. Nothing was removed.');
    }
}

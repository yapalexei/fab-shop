<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Support\Attachments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuoteAttachmentController extends Controller
{
    public function store(Request $request, Quote $quote): RedirectResponse
    {
        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:20'],
            'attachments.*' => ['file', 'max:'.Attachments::MAX_KILOBYTES],
        ]);

        foreach ($request->file('attachments') as $file) {
            $quote->addMedia($file)->toMediaCollection('attachments');
        }

        return back()->with('status', 'File uploaded.');
    }

    public function show(Request $request, Quote $quote, int $media): StreamedResponse
    {
        return Attachments::respond($request, $this->find($quote, $media));
    }

    public function destroy(Quote $quote, int $media): RedirectResponse
    {
        $this->find($quote, $media)->delete();

        return back()->with('status', 'File deleted.');
    }

    private function find(Quote $quote, int $media)
    {
        return $quote->media()->where('collection_name', 'attachments')->where('id', $media)->firstOrFail();
    }
}

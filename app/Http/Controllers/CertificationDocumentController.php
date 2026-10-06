<?php

namespace App\Http\Controllers;

use App\Models\Certification;
use App\Support\Attachments;
use App\Support\CertificationDocuments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificationDocumentController extends Controller
{
    public function store(Request $request, Certification $certification): RedirectResponse
    {
        $room = CertificationDocuments::room($certification);

        $request->validate([...CertificationDocuments::rules($room), 'documents' => ['required', 'array', 'max:'.$room]], [
            'documents.required' => 'Choose at least one document.',
            'documents.max' => $room === 0
                ? 'This certification already has '.CertificationDocuments::MAX_FILES.' documents - remove one first.'
                : "Only {$room} more document".($room === 1 ? '' : 's').' can be added (up to '.CertificationDocuments::MAX_FILES.' per certification).',
            'documents.*.max' => 'Each document can be at most '.CertificationDocuments::MAX_MEGABYTES.' MB.',
            'documents.*.mimes' => 'Documents must be a PDF or an image (JPG, PNG, GIF or WebP).',
        ]);

        $files = $request->file('documents');

        foreach ($files as $file) {
            $certification->addMedia($file)->toMediaCollection(CertificationDocuments::COLLECTION);
        }

        return back()->with('status', count($files) === 1 ? 'Document uploaded.' : 'Documents uploaded.');
    }

    public function show(Request $request, Certification $certification, int $media): StreamedResponse
    {
        return Attachments::respond($request, $this->find($certification, $media));
    }

    public function destroy(Certification $certification, int $media): RedirectResponse
    {
        $this->find($certification, $media)->delete();

        return back()->with('status', 'Document deleted.');
    }

    private function find(Certification $certification, int $media): Media
    {
        return $certification->media()
            ->where('collection_name', CertificationDocuments::COLLECTION)
            ->where('id', $media)
            ->firstOrFail();
    }
}

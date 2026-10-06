<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobPhotoRequest;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class JobPhotoController extends Controller
{
    public function store(StoreJobPhotoRequest $request, Job $job): RedirectResponse
    {
        $names = [];

        foreach ($request->file('photos', []) as $photo) {
            $job->addMedia($photo)->toMediaCollection('photos');
            $names[] = $photo->getClientOriginalName();
        }

        activity()
            ->performedOn($job)
            ->causedBy(Auth::user())
            ->log(count($names) === 1
                ? "Uploaded photo {$names[0]}"
                : 'Uploaded '.count($names).' photos ('.implode(', ', $names).')');

        return back()->with('status', 'Photo uploaded.');
    }

    public function destroy(Job $job, int $media): RedirectResponse
    {
        $job->media()->where('id', $media)->firstOrFail()->delete();

        return back()->with('status', 'Photo deleted.');
    }
}

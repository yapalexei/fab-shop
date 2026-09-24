<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJobPhotoRequest;
use App\Models\Job;
use Illuminate\Http\RedirectResponse;

class JobPhotoController extends Controller
{
    public function store(StoreJobPhotoRequest $request, Job $job): RedirectResponse
    {
        foreach ($request->file('photos', []) as $photo) {
            $job->addMedia($photo)->toMediaCollection('photos');
        }

        return back()->with('status', 'Photo uploaded.');
    }

    public function destroy(Job $job, int $media): RedirectResponse
    {
        $job->media()->where('id', $media)->firstOrFail()->delete();

        return back()->with('status', 'Photo deleted.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Support\Attachments;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobAttachmentController extends Controller
{
    public function show(Request $request, Job $job, int $media): StreamedResponse
    {
        $file = $job->media()->where('collection_name', 'attachments')->where('id', $media)->firstOrFail();

        return Attachments::respond($request, $file);
    }
}

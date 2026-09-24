<?php

namespace App\Http\Controllers;

use App\Models\Job;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Jobs/Index', [
            'jobs' => Job::query()
                ->with('customer:id,name')
                ->latest()
                ->get()
                ->map(fn (Job $job) => [
                    'id' => $job->id,
                    'job_number' => $job->job_number,
                    'customer' => $job->customer->name,
                    'status' => $job->status->value,
                    'total' => $job->total,
                ]),
        ]);
    }

    public function show(Job $job): Response
    {
        $job->load('quote.lineItems', 'customer');

        return Inertia::render('Jobs/Show', [
            'job' => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'status' => $job->status->value,
                'total' => $job->total,
                'customer' => [
                    'name' => $job->customer->name,
                    'email' => $job->customer->email,
                ],
                'line_items' => $job->quote->lineItems->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_cost,
                    'total' => $item->total,
                ]),
            ],
        ]);
    }
}

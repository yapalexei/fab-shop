<?php

namespace App\Mcp\Tools;

use App\Models\Job;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the shop\'s jobs on the job board with their current stage, due date and who it is assigned to. Filter by stage name or to overdue jobs. Cancelled jobs are left out unless asked for.')]
#[Name('list-jobs')]
#[IsReadOnly]
#[IsIdempotent]
class ListJobsTool extends McpTool
{
    protected function permission(): string
    {
        return 'jobs.view';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stage' => $schema->string()->description('Only jobs in the stage with this name (as shown on the board).'),
            'overdue_only' => $schema->boolean()->description('Only jobs past their due date that have not shipped.')->default(false),
            'include_cancelled' => $schema->boolean()->description('Include cancelled jobs (default false).')->default(false),
            'limit' => $schema->integer()->description('How many to return, 1-50 (default 20).')->default(20),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([
            'stage' => ['nullable', 'string', 'max:100'],
            'overdue_only' => ['nullable', 'boolean'],
            'include_cancelled' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $today = $shop->localNow();

        $jobs = Job::query()
            ->with(['customer:id,name', 'jobStage', 'assignedTo:id,name'])
            ->when($data['stage'] ?? null, fn ($query, $stage) => $query->whereHas('jobStage', fn ($q) => $q->whereRaw('lower(name) = ?', [mb_strtolower($stage)])))
            ->unless($data['include_cancelled'] ?? false, fn ($query) => $query->whereNull('cancelled_at'))
            ->orderByRaw('due_date asc nulls last')
            ->orderBy('id')
            ->get()
            ->when($data['overdue_only'] ?? false, fn ($jobs) => $jobs->filter(fn (Job $job) => $job->isOverdue($today)))
            ->take($data['limit'] ?? 20);

        return [
            'jobs' => $jobs->values()->map(fn (Job $job) => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'customer' => $job->customer->name,
                'stage_id' => $job->job_stage_id,
                'stage' => $job->jobStage?->name,
                'due_date' => $job->due_date?->toDateString(),
                'overdue' => $job->isOverdue($today),
                'assigned_to' => $job->assignedTo?->name,
                'cancelled' => $job->isCancelled(),
            ])->all(),
        ];
    }
}

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

#[Description('Get one job: its stage, dates, assignee and the quote it came from. The price is included only if this connection may see financials.')]
#[Name('get-job')]
#[IsReadOnly]
#[IsIdempotent]
class GetJobTool extends McpTool
{
    protected function permission(): string
    {
        return 'jobs.view';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The job id (from list-jobs).')->required(),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate(['id' => ['required', 'integer']]);

        $job = Job::query()->with(['customer:id,name', 'jobStage', 'assignedTo:id,name', 'quote:id'])->find($data['id']);

        if (! $job) {
            return ['found' => false, 'message' => 'No job with that id in this shop.'];
        }

        $local = fn ($date) => $date?->copy()->setTimezone($shop->localTimezone())->toIso8601String();

        return [
            'found' => true,
            'job' => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'customer_id' => $job->customer_id,
                'customer' => $job->customer->name,
                'quote_id' => $job->quote_id,
                'stage_id' => $job->job_stage_id,
                'stage' => $job->jobStage?->name,
                'due_date' => $job->due_date?->toDateString(),
                'overdue' => $job->isOverdue($shop->localNow()),
                'assigned_to' => $job->assignedTo?->name,
                'started_at' => $local($job->started_at),
                'finished_at' => $local($job->finished_at),
                'invoiced_at' => $local($job->invoiced_at),
                'cancelled' => $job->isCancelled(),
                ...($agent->hasShopPermission('financials.view') ? ['total' => round($job->total, 2)] : []),
            ],
        ];
    }
}

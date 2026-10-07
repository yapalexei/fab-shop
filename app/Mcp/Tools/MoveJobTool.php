<?php

namespace App\Mcp\Tools;

use App\Actions\MoveJobToStage;
use App\Models\Job;
use App\Models\JobStage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('move-job')]
#[Description('Move a job to another stage on the board (stage ids come from list-job-stages). Follows the board\'s rules: forward to any later stage, backward only before it has shipped. Any stage is allowed, including Shipped (flags the job ready to invoice) and Invoiced (marks it invoiced); a job that has shipped can\'t be moved back. It cannot touch cancelled jobs.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
class MoveJobTool extends McpTool
{
    protected function permission(): string
    {
        return 'jobs.update';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The job id (from list-jobs).')->required(),
            'stage_id' => $schema->integer()->description('The id of the stage to move it to (from list-job-stages).')->required(),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'stage_id' => ['required', 'integer'],
        ]);

        $job = Job::query()->with('jobStage')->find($data['id']);

        if (! $job) {
            return ['moved' => false, 'reason' => 'No job with that id in this shop.'];
        }

        $target = JobStage::query()->find($data['stage_id']);

        if (! $target) {
            return ['moved' => false, 'reason' => 'No stage with that id in this shop. Use list-job-stages.'];
        }

        if ($job->isCancelled()) {
            return ['moved' => false, 'reason' => 'This job is cancelled, so it can\'t be moved.'];
        }

        if (! $job->canTransitionTo($target)) {
            return ['moved' => false, 'reason' => "A job can't move from {$job->jobStage->name} to {$target->name}."];
        }

        $description = app(MoveJobToStage::class)($job, $target, $agent);

        return ['moved' => true, 'description' => $description, 'job' => ['id' => $job->id, 'job_number' => $job->job_number, 'stage' => $target->name]];
    }
}

<?php

namespace App\Mcp\Tools;

use App\Events\JobBoardUpdated;
use App\Models\Job;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('update-job')]
#[Description('Change a job\'s due date and/or who it is assigned to. Send only what you want to change; send null to clear the due date or unassign. The assignee is a team member\'s name. Does not move the job between stages (use move-job) and cannot touch cancelled jobs.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent]
class UpdateJobTool extends McpTool
{
    protected function permission(): string
    {
        return 'jobs.update';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The job id (from list-jobs).')->required(),
            'due_date' => $schema->string()->description('Due date as YYYY-MM-DD, or null to clear it.'),
            'assignee' => $schema->string()->description('The name of the team member to assign, or null to unassign.'),
        ];
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        $args = $request->all();
        $data = $request->validate([
            'id' => ['required', 'integer'],
            'due_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'assignee' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (! array_key_exists('due_date', $args) && ! array_key_exists('assignee', $args)) {
            throw ValidationException::withMessages(['id' => 'Send a due_date and/or an assignee to change.']);
        }

        $job = Job::query()->with('assignedTo:id,name')->find($data['id']);

        if (! $job) {
            return ['updated' => false, 'reason' => 'No job with that id in this shop.'];
        }

        if ($job->isCancelled()) {
            return ['updated' => false, 'reason' => 'This job is cancelled, so it can\'t be changed.'];
        }

        $descriptions = [];

        if (array_key_exists('due_date', $args)) {
            $descriptions[] = $this->setDueDate($job, $data['due_date'] ?? null);
        }

        if (array_key_exists('assignee', $args)) {
            $descriptions[] = $this->setAssignee($job, $shop, $data['assignee'] ?? null);
        }

        $descriptions = array_values(array_filter($descriptions));

        if ($descriptions === []) {
            return ['updated' => false, 'reason' => 'Nothing to change - those values are already set.'];
        }

        foreach ($descriptions as $description) {
            activity()->performedOn($job)->causedBy($agent)->log($description.' (via AI connection)');
        }

        broadcast(new JobBoardUpdated($job->shop_id))->toOthers();

        $job->refresh()->load('assignedTo:id,name');

        return [
            'updated' => true,
            'changes' => $descriptions,
            'job' => [
                'id' => $job->id,
                'job_number' => $job->job_number,
                'due_date' => $job->due_date?->toDateString(),
                'assigned_to' => $job->assignedTo?->name,
            ],
        ];
    }

    private function setDueDate(Job $job, ?string $dueDate): ?string
    {
        $previous = $job->due_date?->format('M j, Y');

        $job->update(['due_date' => $dueDate]);

        $new = $job->due_date?->format('M j, Y');

        return match (true) {
            $new === $previous => null,
            $new === null => 'Due date cleared (was '.$previous.')',
            $previous === null => "Due date set to {$new}",
            default => "Due date changed from {$previous} to {$new}",
        };
    }

    /**
     * Matched by name against the shop's people (never agents), so the AI
     * doesn't need a user id - and an unknown or ambiguous name is an error
     * that lists who it could have meant.
     */
    private function setAssignee(Job $job, Shop $shop, ?string $name): ?string
    {
        $previousName = $job->assignedTo?->name;

        if ($name === null) {
            if ($previousName === null) {
                return null;
            }

            $job->update(['assigned_to' => null]);

            return "Unassigned from {$previousName}";
        }

        $members = $shop->members()->get(['users.id', 'users.name']);
        $matches = $members->filter(fn (User $member) => mb_strtolower($member->name) === mb_strtolower(trim($name)));

        if ($matches->count() !== 1) {
            $names = $members->pluck('name')->implode(', ');

            throw ValidationException::withMessages([
                'assignee' => $matches->isEmpty()
                    ? "No team member is named \"{$name}\". Team members: {$names}."
                    : "More than one team member is named \"{$name}\".",
            ]);
        }

        $member = $matches->first();

        if ($member->id === $job->assigned_to) {
            return null;
        }

        $job->update(['assigned_to' => $member->id]);

        return $previousName === null ? "Assigned to {$member->name}" : "Reassigned from {$previousName} to {$member->name}";
    }
}

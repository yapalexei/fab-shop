<?php

namespace App\Mcp\Tools;

use App\Enums\JobStageRole;
use App\Models\JobStage;
use App\Models\Shop;
use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-job-stages')]
#[Description('List the shop\'s job board stages in order, with their ids (needed by move-job). Shows which stages the AI connection may move a job into.')]
#[IsReadOnly]
#[IsIdempotent]
class ListJobStagesTool extends McpTool
{
    protected function permission(): string
    {
        return 'job-stages.view';
    }

    protected function run(Request $request, User $agent, Shop $shop): array
    {
        return [
            'stages' => JobStage::query()->orderBy('position')->get()->map(fn (JobStage $stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'position' => $stage->position,
                'role' => $stage->role?->value,
                // Shipped and Invoiced trigger invoicing, so only a person moves a job there.
                'ai_can_move_into' => ! in_array($stage->role, [JobStageRole::Shipped, JobStageRole::Invoiced], true),
            ])->all(),
        ];
    }
}

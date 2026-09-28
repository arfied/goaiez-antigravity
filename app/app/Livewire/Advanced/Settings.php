<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Modules\X219\Actions\ModelResolveAction;
use App\Services\Ai\AiSpend;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Settings extends Component
{
    public function render(AiSpend $spend, ModelResolveAction $resolveAction): View
    {
        $businessId = Tenancy::idOrFail();
        $tasks = [];

        foreach (AiTask::cases() as $task) {
            $effectiveModel = $spend->modelFor($task);
            $assignmentName = $resolveAction->handle($businessId, $task->value);

            $hasTenantAssignment = false;
            if ($assignmentName !== null) {
                $assignedModel = AiModel::tryFrom($assignmentName);
                if ($assignedModel !== null && $assignedModel->isEmbedding() === $task->producesEmbedding()) {
                    $hasTenantAssignment = true;
                }
            }

            $tasks[] = [
                'label' => Str::headline($task->value),
                'effective_model' => $effectiveModel->value,
                'has_tenant_assignment' => $hasTenantAssignment,
            ];
        }

        return view('livewire.advanced.settings', [
            'tasks' => $tasks,
        ]);
    }
}

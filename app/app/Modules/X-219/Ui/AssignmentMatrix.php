<?php

declare(strict_types=1);

namespace App\Modules\X219\Ui;

use App\Enums\AiTask;
use App\Enums\UserRole;
use App\Modules\X219\Actions\ModelAssignAction;
use App\Modules\X219\Models\AiModel;
use App\Modules\X219\Models\AiModuleAssignment;
use App\Services\Ai\AiSpend;
use App\Support\Tenancy;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Model Assignment Matrix'])]
class AssignmentMatrix extends Component
{
    #[Locked]
    public int $businessId = 0;

    public array $primaryModelForTask = [];

    public array $backupModelForTask = [];

    public function mount(int $businessId = 0): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole(UserRole::Owner), 403);
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);

        if ($this->businessId > 0) {
            $assignments = AiModuleAssignment::where('business_id', $this->businessId)->get();
            foreach ($assignments as $a) {
                $this->primaryModelForTask[$a->target_module] = $a->primary_model_id;
                $this->backupModelForTask[$a->target_module] = $a->backup_model_id;
            }
        }
    }

    public function assign(string $taskValue, ModelAssignAction $action): void
    {
        $primary = $this->primaryModelForTask[$taskValue] ?? null;
        $backup = $this->backupModelForTask[$taskValue] ?? null;

        if (! $primary || ! $backup) {
            return;
        }

        try {
            $action->handle($this->businessId, $taskValue, (int) $primary, (int) $backup);
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Assignment saved']);
        } catch (InvalidArgumentException $e) {
            $this->addError("assign.{$taskValue}", $e->getMessage());
        }
    }

    public function render(AiSpend $spend)
    {
        $assignments = collect();
        $models = collect();
        $tasks = collect(AiTask::cases());
        $effectiveModels = collect();

        if ($this->businessId > 0) {
            $assignments = AiModuleAssignment::where('business_id', $this->businessId)->get()->keyBy('target_module');
            $models = AiModel::where('business_id', $this->businessId)->where('is_active', true)->get();

            foreach ($tasks as $task) {
                $effectiveModels[$task->value] = $spend->modelFor($task)->value;
            }
        }

        return view('x-219::assignment-matrix', [
            'assignments' => $assignments,
            'models' => $models,
            'tasks' => $tasks,
            'effectiveModels' => $effectiveModels,
        ]);
    }
}

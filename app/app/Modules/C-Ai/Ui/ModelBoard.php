<?php

declare(strict_types=1);

namespace App\Modules\CAi\Ui;

use App\Modules\CAi\Actions\AiCompleteAction;
use App\Modules\CAi\Models\AiCall;
use App\Support\Tenancy;
use Exception;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'AI calls'])]
class ModelBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $ready = false;

    public ?string $errorMessage = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
        $this->ready = true;
    }

    public function load()
    {
        $this->errorMessage = null;
        $this->ready = true;
    }

    public function retry(int $id, AiCompleteAction $action)
    {
        try {
            $call = AiCall::where('business_id', $this->businessId)->findOrFail($id);
            $action->handle(
                businessId: $this->businessId,
                prompt: 'Retry of task '.$call->task_id,
                modelRequested: $call->model_requested ?? 'default_primary',
                taskId: $call->task_id
            );
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        if (! $this->ready) {
            return view('c-ai::model-board', [
                'calls' => collect(),
            ]);
        }

        try {
            $calls = AiCall::where('business_id', $this->businessId)->orderBy('id', 'desc')->limit(20)->get();

            return view('c-ai::model-board', [
                'calls' => $calls,
            ]);
        } catch (Exception $e) {
            $this->errorMessage = $e->getMessage();

            return view('c-ai::model-board', [
                'calls' => collect(),
            ]);
        }
    }
}

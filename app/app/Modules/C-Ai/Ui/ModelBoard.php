<?php

declare(strict_types=1);

namespace App\Modules\CAi\Ui;

use App\Modules\CAi\Actions\AiCompleteAction;
use App\Modules\CAi\Models\AiCall;
use Exception;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ModelBoard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $ready = false;

    public ?string $errorMessage = null;

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
                prompt: 'Retry of task '.$call->task,
                modelRequested: $call->model ?? 'default_primary',
                taskId: $id
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

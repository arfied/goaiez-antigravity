<?php

declare(strict_types=1);

namespace App\Modules\X125\Ui;

use App\Modules\X125\Actions\FlowRunAction;
use App\Modules\X125\Models\FlowRun;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Runs extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $ready = false;

    public ?string $errorMessage = null;

    public function load()
    {
        $this->ready = true;
        $this->errorMessage = null;
    }

    public function retry(int $id, FlowRunAction $action)
    {
        try {
            $run = FlowRun::where('business_id', $this->businessId)->findOrFail($id);
            $action->handle(
                businessId: $this->businessId,
                flowId: $run->flow_id,
                triggerPayload: $run->trigger_payload ?? [],
                isManualRetry: true
            );
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function render()
    {
        if (! $this->ready) {
            return view('x-125::runs', [
                'runs' => collect(),
            ]);
        }

        try {
            // Eager load flow and flowVersion
            $runs = FlowRun::with(['flow', 'flowVersion'])
                ->where('business_id', $this->businessId)
                ->orderBy('id', 'desc')
                ->get();

            return view('x-125::runs', [
                'runs' => $runs,
            ]);
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();

            return view('x-125::runs', [
                'runs' => collect(),
            ]);
        }
    }
}

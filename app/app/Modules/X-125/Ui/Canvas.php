<?php

declare(strict_types=1);

namespace App\Modules\X125\Ui;

use App\Modules\X125\Actions\FlowCreateAction;
use App\Modules\X125\Models\Flow;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your automations'])]
class Canvas extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $flowName = '';

    public string $triggerEvent = '';

    public string $stepLabel = '';

    public string $error = '';

    public string $success = '';

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function createFlow(FlowCreateAction $action): void
    {
        if (trim($this->flowName) === '' || trim($this->triggerEvent) === '') {
            $this->error = 'Flow name and trigger event are required.';

            return;
        }

        $action->handle(
            Tenancy::idOrFail(),
            $this->flowName,
            $this->triggerEvent,
            [['type' => 'action', 'label' => $this->stepLabel]]
        );

        $this->success = 'Created automation '.$this->flowName.'. Nothing runs a flow when its trigger event fires.';
        $this->error = '';

        $this->flowName = '';
        $this->triggerEvent = '';
        $this->stepLabel = '';
    }

    public function render()
    {
        $flows = ($this->businessId > 0)
            ? Flow::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-125::canvas', [
            'flows' => $flows,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

use App\Modules\CAgent\Actions\AgentTeachAction;
use App\Modules\CAgent\Models\AgentInstruction;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agent teaching'])]
class TeachingBox extends Component
{
    #[Locked]
    public int $businessId = 0;

    public string $key = '';

    public string $value = '';

    public ?string $success = null;

    public ?string $error = null;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function teachAgent(AgentTeachAction $action): void
    {
        $this->reset(['success', 'error']);

        if (trim($this->key) === '' || trim($this->value) === '') {
            $this->error = 'Key and value are required.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $this->key, $this->value);

        $this->success = "Set instruction {$result['instruction_id']} with key '{$result['key']}' to '{$result['value']}'. This creates or edits the instruction and its underlying fact row. The fact row is not displayed here. Nothing downstream is wired to it yet.";
        $this->reset(['key', 'value']);
    }

    public function render()
    {
        $instructions = ($this->businessId > 0)
            ? AgentInstruction::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-agent::teaching-box', [
            'instructions' => $instructions,
        ]);
    }
}

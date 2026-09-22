<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

use App\Modules\CAgent\Actions\AgentAnswerAction;
use App\Modules\CAgent\Models\AgentTurn;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agent grounding'])]
class GroundcheckScreen extends Component
{
    public string $userMessage = '';

    public ?string $success = null;

    public ?string $error = null;

    public function askAgent(AgentAnswerAction $action)
    {
        $this->success = null;
        $this->error = null;

        if (trim($this->userMessage) === '') {
            $this->error = 'Message cannot be empty.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $this->userMessage);
        $this->success = 'Recorded agent turn '.$result['turn_id'].'. Status: '.$result['status'].'. Reply: '.$result['reply'].'. This feeds the Thread screen; nothing downstream is wired to it yet.';
        $this->userMessage = '';
    }

    public function render()
    {
        // AgentTurn is scoped to the tenant by the Postgres tenant_isolation policy on agent_turns.
        $turns = AgentTurn::orderByDesc('id')->limit(20)->get();
        $answered = AgentTurn::where('status', 'answered')->count();
        $refused = AgentTurn::where('status', 'refused')->count();
        $handoff = AgentTurn::where('status', 'handoff')->count();

        return view('c-agent::groundcheck-screen', ['turns' => $turns, 'answered' => $answered, 'refused' => $refused, 'handoff' => $handoff]);
    }
}

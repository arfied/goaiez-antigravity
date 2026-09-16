<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

use App\Modules\CAgent\Models\AgentTurn;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agent grounding'])]
class GroundcheckScreen extends Component
{
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

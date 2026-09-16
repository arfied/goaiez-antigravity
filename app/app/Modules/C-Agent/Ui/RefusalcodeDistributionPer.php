<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Ui;

use App\Modules\CAgent\Models\AgentRefusal;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Agent refusals'])]
class RefusalcodeDistributionPer extends Component
{
    public function render()
    {
        // Tenant isolation is enforced by Postgres RLS policy 'tenant_isolation' in 2026_08_30_000013_create_c_agent_tables.php.
        $refusals = AgentRefusal::get();

        return view('c-agent::refusalcode-distribution-per', [
            'refusals' => $refusals,
        ]);
    }
}

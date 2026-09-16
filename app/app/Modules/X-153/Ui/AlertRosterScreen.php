<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Models\Alert;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Team alerts'])]
class AlertRosterScreen extends Component
{
    public function render()
    {
        // Tenant isolation is enforced by Postgres RLS policy 'tenant_isolation' in 2026_08_30_000016_create_x153_alert_tables.php.
        $alerts = Alert::orderBy('id', 'desc')->get();

        return view('x-153::alert-roster-screen', [
            'alerts' => $alerts,
        ]);
    }
}

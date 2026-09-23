<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Actions\AlertSendAction;
use App\Modules\X153\Models\Alert;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Team alerts'])]
class AlertRosterScreen extends Component
{
    public string $title = '';

    public string $body = '';

    public ?string $success = null;

    public ?string $error = null;

    public function broadcastAlert(AlertSendAction $action): void
    {
        $this->success = null;
        $this->error = null;

        if (trim($this->title) === '' || trim($this->body) === '') {
            $this->error = 'Title and body are required.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $this->title, $this->body);

        $this->success = 'Broadcasted alert with reply code '.$result['code'].'. This feeds the claim screen; nothing downstream is wired to it yet.';
        $this->title = '';
        $this->body = '';
    }

    public function render()
    {
        // Tenant isolation is enforced by Postgres RLS policy 'tenant_isolation' in 2026_08_30_000016_create_x153_alert_tables.php.
        $alerts = Alert::orderBy('id', 'desc')->get();

        return view('x-153::alert-roster-screen', [
            'alerts' => $alerts,
        ]);
    }
}

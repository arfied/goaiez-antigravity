<?php

declare(strict_types=1);

namespace App\Modules\X153\Ui;

use App\Modules\X153\Actions\AlertSendAction;
use App\Modules\X153\Models\Alert;
use App\Modules\X207\Actions\PushBroadcastAction;
use App\Modules\X207\Jobs\SendPushToUserJob;
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
        abort_unless(auth()->user()?->role->canConfigureAutomation() === true, 403);

        $this->success = null;
        $this->error = null;

        if (trim($this->title) === '' || trim($this->body) === '') {
            $this->error = 'Title and body are required.';

            return;
        }

        $result = $action->handle(Tenancy::idOrFail(), $this->title, $this->body);

        $recipients = array_values(array_diff(app(PushBroadcastAction::class)->activeWebUserIds(Tenancy::idOrFail()), [(int) auth()->id()]));

        foreach ($recipients as $userId) {
            SendPushToUserJob::dispatch(Tenancy::idOrFail(), $userId, 'team_alert', route('x-153.alert-reply-by', [], false));
        }

        $this->success = 'Broadcasted alert with reply code '.$result['code'].'. '.(count($recipients) === 0 ? 'Nobody else on your team has turned on alerts in their browser yet, so no one was notified.' : 'Sent to '.count($recipients).' '.(count($recipients) === 1 ? 'teammate' : 'teammates').'\'s browser alerts.');
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

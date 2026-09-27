<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Ui;

use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ImpersonationRefused;
use App\Exceptions\ZernioConnectionRefused;
use App\Modules\CWhatsapp\Actions\WhatsappConnectStartAction;
use App\Modules\CWhatsapp\Actions\WhatsappDisconnectAction;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

#[Layout('components.account.layout', ['heading' => 'WhatsApp conversations'])]
class Thread extends Component
{
    public function connect(WhatsappConnectStartAction $action): void
    {
        try {
            $url = $action->handle('user:'.(int) auth()->id());
        } catch (GbpRequestFailed) {
            Toaster::error('We could not start that connection. Please try again shortly.');

            return;
        } catch (ImpersonationRefused $refused) {
            Toaster::warning($refused->getMessage());

            return;
        } catch (ZernioConnectionRefused $refused) {
            $refused->reason === 'ceiling_reached'
                ? Toaster::info($refused->getMessage())
                : Toaster::error($refused->getMessage());

            return;
        }

        $this->redirect($url);
    }

    public function disconnect(WhatsappDisconnectAction $action): void
    {
        $action->handle('user:'.(int) auth()->id());
    }

    public function render()
    {
        $sessions = WhatsappSession::query()
            ->where('business_id', (int) Tenancy::idOrFail())
            ->orderByDesc('last_inbound_at')
            ->limit(50)
            ->get();

        $connection = WhatsappConnection::first();

        return view('c-whatsapp::thread', [
            'sessions' => $sessions,
            'connection' => $connection,
        ]);
    }
}

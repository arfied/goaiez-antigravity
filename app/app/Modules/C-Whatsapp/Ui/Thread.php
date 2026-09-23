<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Ui;

use App\Modules\CWhatsapp\Models\WhatsappSession;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'WhatsApp conversations'])]
class Thread extends Component
{
    public function render()
    {
        $sessions = WhatsappSession::query()
            ->where('business_id', (int) Tenancy::idOrFail())
            ->orderByDesc('last_inbound_at')
            ->limit(50)
            ->get();

        return view('c-whatsapp::thread', ['sessions' => $sessions]);
    }
}

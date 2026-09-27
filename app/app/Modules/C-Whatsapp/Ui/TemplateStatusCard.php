<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Ui;

use App\Exceptions\GbpRequestFailed;
use App\Modules\CWhatsapp\Actions\WhatsappConnectionLookupAction;
use App\Modules\CWhatsapp\Domain\WhatsappEngine;
use App\Modules\CWhatsapp\Models\WhatsappConnection;
use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Services\Zernio\ZernioWhatsappClient;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'WhatsApp templates'])]
class TemplateStatusCard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public ?string $error = null;

    public function refresh(): void
    {
        $this->error = null;

        if ($this->businessId === 0) {
            return;
        }

        $lookup = app(WhatsappConnectionLookupAction::class);
        try {
            $connection = $lookup->forBusiness($this->businessId);
        } catch (\Error) {
            $connection = WhatsappConnection::where('business_id', $this->businessId)->first();
        }

        if (! $connection || $connection->status !== 'connected') {
            $this->error = 'Connect WhatsApp first.';

            return;
        }

        $client = app(ZernioWhatsappClient::class);
        $engine = app(WhatsappEngine::class);

        try {
            $templates = $client->templates($connection->account_ref);
            foreach ($templates as $t) {
                if (! is_array($t)) {
                    continue;
                }

                $engine->applyTemplateStatus(
                    $this->businessId,
                    (string) ($t['id'] ?? ''),
                    (string) ($t['name'] ?? ''),
                    (string) ($t['language'] ?? 'en_US'),
                    (string) ($t['status'] ?? ''),
                    null
                );
            }
        } catch (GbpRequestFailed $e) {
            $this->error = 'We could not reach Zernio. Try again shortly.';
        }
    }

    public function render()
    {
        $templates = ($this->businessId > 0)
            ? WhatsappTemplate::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('c-whatsapp::template-status-card', [
            'templates' => $templates,
        ]);
    }
}

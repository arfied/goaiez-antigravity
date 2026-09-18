<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Ui;

use App\Modules\CWhatsapp\Models\WhatsappTemplate;
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

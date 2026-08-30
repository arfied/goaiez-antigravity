<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Ui;

use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use Livewire\Component;

class TemplateStatusCard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $templates = ($this->businessId > 0)
            ? WhatsappTemplate::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-whatsapp::template-status-card', [
            'templates' => $templates,
        ]);
    }
}

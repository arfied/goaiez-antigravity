<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Ui;

use App\Modules\CWhatsapp\Models\WhatsappTemplate;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Template approval queue'])]
class TemplateApprovalQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $pending = ($this->businessId > 0)
            ? WhatsappTemplate::where('business_id', $this->businessId)->where('status', 'pending_approval')->orderByDesc('id')->get()
            : collect();

        return view('c-whatsapp::template-approval-queue', [
            'pending' => $pending,
        ]);
    }
}

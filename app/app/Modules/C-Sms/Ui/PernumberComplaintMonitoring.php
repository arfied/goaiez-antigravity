<?php

declare(strict_types=1);

namespace App\Modules\CSms\Ui;

use App\Modules\CSms\Models\SmsComposition;
use App\Modules\X204\Domain\ConsentService;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Number health'])]
class PernumberComplaintMonitoring extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render(ConsentService $consentService)
    {
        if ($this->businessId > 0) {
            $rows = SmsComposition::where('business_id', $this->businessId)
                ->get()
                ->groupBy('recipient_phone')
                ->map(fn ($g) => [
                    'sent' => $g->where('status', 'sent')->count(),
                    'halted' => $g->where('status', 'halted')->count(),
                    'scheduled' => $g->where('status', 'scheduled')->count(),
                ]);
            $stopped = $consentService->getSuppressions($this->businessId)
                ->pluck('recipient_phone')
                ->all();
        } else {
            $rows = collect();
            $stopped = [];
        }

        return view('c-sms::pernumber-complaint-monitoring', [
            'rows' => $rows,
            'stopped' => $stopped,
        ]);
    }
}

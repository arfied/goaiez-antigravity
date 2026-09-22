<?php

declare(strict_types=1);

namespace App\Modules\CSms\Ui;

use App\Modules\X204\Domain\ConsentService;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Do-not-text list'])]
class DonottextList extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0): void
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render(ConsentService $consentService)
    {
        $suppressions = ($this->businessId > 0)
            ? $consentService->getSuppressions($this->businessId)
            : collect();

        return view('c-sms::donottext-list', [
            'suppressions' => $suppressions,
        ]);
    }
}

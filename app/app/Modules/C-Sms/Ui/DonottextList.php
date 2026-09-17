<?php

declare(strict_types=1);

namespace App\Modules\CSms\Ui;

use App\Modules\X204\Models\Suppression;
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

    public function render()
    {
        $suppressions = ($this->businessId > 0)
            ? Suppression::where('business_id', $this->businessId)->orderByDesc('id')->get()
            : collect();

        return view('c-sms::donottext-list', [
            'suppressions' => $suppressions,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X155\Ui;

use App\Modules\X155\Models\FormSubmission;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Spam rate'])]
class SpamRate extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public function render()
    {
        $stats = ($this->businessId > 0)
            ? FormSubmission::where('business_id', $this->businessId)
                ->selectRaw('COUNT(*) as total, SUM(CASE WHEN is_spam THEN 1 ELSE 0 END) as spam')
                ->first()
            : null;

        $total = (int) ($stats->total ?? 0);
        $spam = (int) ($stats->spam ?? 0);

        $rate = $total > 0 ? round(($spam / $total) * 100, 1) : 0;

        return view('x-155::spam-rate', [
            'total' => $total,
            'spam' => $spam,
            'rate' => $rate,
        ]);
    }
}

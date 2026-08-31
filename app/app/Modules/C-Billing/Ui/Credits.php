<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

use App\Modules\CBilling\Models\CreditLedgerEntry;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Credits extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $entries = ($this->businessId > 0)
            ? CreditLedgerEntry::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('c-billing::credits', [
            'entries' => $entries,
        ]);
    }
}

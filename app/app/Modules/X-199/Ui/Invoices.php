<?php

declare(strict_types=1);

namespace App\Modules\X199\Ui;

use App\Modules\X199\Models\Invoice;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Invoices extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $invoices = ($this->businessId > 0)
            ? Invoice::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-199::invoices', [
            'invoices' => $invoices,
        ]);
    }
}

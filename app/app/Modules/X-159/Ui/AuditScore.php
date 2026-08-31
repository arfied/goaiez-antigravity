<?php

declare(strict_types=1);

namespace App\Modules\X159\Ui;

use App\Modules\X159\Models\Audit;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AuditScore extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $audits = ($this->businessId > 0)
            ? Audit::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-159::score', [
            'audits' => $audits,
        ]);
    }
}

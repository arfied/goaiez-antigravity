<?php

declare(strict_types=1);

namespace App\Modules\X206\Ui;

use App\Modules\X206\Models\CredentialReveal;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RevealLog extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $logs = ($this->businessId > 0)
            ? CredentialReveal::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-206::reveal-log', [
            'logs' => $logs,
        ]);
    }
}

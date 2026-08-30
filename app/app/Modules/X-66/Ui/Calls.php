<?php

declare(strict_types=1);

namespace App\Modules\X66\Ui;

use App\Modules\X66\Models\CallSession;
use Livewire\Component;

class Calls extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $calls = ($this->businessId > 0)
            ? CallSession::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-66::calls', [
            'calls' => $calls,
        ]);
    }
}

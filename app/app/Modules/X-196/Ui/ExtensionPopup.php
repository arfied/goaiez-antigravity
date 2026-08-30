<?php

declare(strict_types=1);

namespace App\Modules\X196\Ui;

use App\Modules\X196\Models\ExtensionSession;
use Livewire\Component;

class ExtensionPopup extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $sessions = ($this->businessId > 0)
            ? ExtensionSession::where('business_id', $this->businessId)->with('injections')->get()
            : collect();

        return view('x-196::extension-popup', [
            'sessions' => $sessions,
        ]);
    }
}

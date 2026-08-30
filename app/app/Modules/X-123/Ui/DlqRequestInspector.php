<?php

declare(strict_types=1);

namespace App\Modules\X123\Ui;

use App\Modules\X123\Models\DeadLetter;
use Livewire\Component;

class DlqRequestInspector extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $deadLetters = ($this->businessId > 0)
            ? DeadLetter::where('business_id', $this->businessId)->orderBy('id', 'desc')->get()
            : collect();

        return view('x-123::dlq-request-inspector', [
            'deadLetters' => $deadLetters,
        ]);
    }
}

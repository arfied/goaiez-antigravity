<?php

declare(strict_types=1);

namespace App\Modules\X124\Ui;

use App\Modules\X124\Models\AssistantUnsupported;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AssistantunsupportedLog extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $logs = ($this->businessId > 0)
            ? AssistantUnsupported::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-124::assistantunsupported-log', [
            'logs' => $logs,
        ]);
    }
}

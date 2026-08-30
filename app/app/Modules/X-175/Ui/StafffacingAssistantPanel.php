<?php

declare(strict_types=1);

namespace App\Modules\X175\Ui;

use App\Modules\X175\Models\FieldSuggestion;
use Livewire\Component;

class StafffacingAssistantPanel extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $suggestions = ($this->businessId > 0)
            ? FieldSuggestion::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-175::stafffacing-assistant-panel', [
            'suggestions' => $suggestions,
        ]);
    }
}

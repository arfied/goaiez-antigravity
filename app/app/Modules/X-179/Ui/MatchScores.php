<?php

declare(strict_types=1);

namespace App\Modules\X179\Ui;

use App\Modules\X179\Models\TemplateMatch;
use Livewire\Component;

class MatchScores extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $matches = ($this->businessId > 0)
            ? TemplateMatch::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-179::match-scores', [
            'matches' => $matches,
        ]);
    }
}

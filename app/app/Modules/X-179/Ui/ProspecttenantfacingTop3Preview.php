<?php

declare(strict_types=1);

namespace App\Modules\X179\Ui;

use App\Modules\X179\Models\TemplateMatch;
use Livewire\Component;

class ProspecttenantfacingTop3Preview extends Component
{
    public int $businessId = 0;

    public int $prospectId = 0;

    public function render()
    {
        $previews = ($this->businessId > 0 && $this->prospectId > 0)
            ? TemplateMatch::where('business_id', $this->businessId)->where('prospect_id', $this->prospectId)->take(3)->get()
            : collect();

        return view('x-179::prospecttenantfacing-top3-preview', [
            'previews' => $previews,
        ]);
    }
}

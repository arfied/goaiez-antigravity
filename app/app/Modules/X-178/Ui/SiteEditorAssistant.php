<?php

declare(strict_types=1);

namespace App\Modules\X178\Ui;

use App\Modules\X178\Models\DesignChange;
use Livewire\Component;

class SiteEditorAssistant extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $changes = ($this->businessId > 0)
            ? DesignChange::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-178::site-editor-assistant', [
            'changes' => $changes,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X173\Ui;

use App\Modules\X173\Models\AccountMapping;
use Livewire\Component;

class ConnectionMappingView extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $mappings = ($this->businessId > 0)
            ? AccountMapping::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-173::connection-mapping', [
            'mappings' => $mappings,
        ]);
    }
}

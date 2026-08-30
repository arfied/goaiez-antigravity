<?php

declare(strict_types=1);

namespace App\Modules\X138\Ui;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AttributionRow extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $queries = ($this->businessId > 0)
            ? DB::table('attribution_queries')->where('business_id', $this->businessId)->get()
            : collect();

        return view('x-138::attribution-row', [
            'queries' => $queries,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X138\Ui;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RoiDashboard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $snapshots = ($this->businessId > 0)
            ? DB::table('roi_snapshots')->where('business_id', $this->businessId)->get()
            : collect();

        return view('x-138::roi-dashboard', [
            'snapshots' => $snapshots,
        ]);
    }
}

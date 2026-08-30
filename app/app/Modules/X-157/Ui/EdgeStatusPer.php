<?php

declare(strict_types=1);

namespace App\Modules\X157\Ui;

use App\Modules\X157\Models\Deployment;
use Livewire\Component;

class EdgeStatusPer extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $deployments = ($this->businessId > 0)
            ? Deployment::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-157::edge-status-per', [
            'deployments' => $deployments,
        ]);
    }
}

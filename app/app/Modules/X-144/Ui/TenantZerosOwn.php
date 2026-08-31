<?php

declare(strict_types=1);

namespace App\Modules\X144\Ui;

use App\Modules\X144\Models\VisibilityAnswer;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TenantZerosOwn extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $unmentioned = ($this->businessId > 0)
            ? VisibilityAnswer::where('business_id', $this->businessId)->where('tenant_mentioned', false)->get()
            : collect();

        return view('x-144::tenant-zeros-own', [
            'unmentioned' => $unmentioned,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\CSms\Ui;

use App\Modules\X204\Models\Suppression;
use Livewire\Component;

class DonottextList extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $suppressions = ($this->businessId > 0)
            ? Suppression::where('business_id', $this->businessId)->get()
            : collect();

        return view('c-sms::donottext-list', [
            'suppressions' => $suppressions,
        ]);
    }
}

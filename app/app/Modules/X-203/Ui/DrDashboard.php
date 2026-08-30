<?php

declare(strict_types=1);

namespace App\Modules\X203\Ui;

use App\Modules\X203\Models\RestoreTest;
use Livewire\Component;

class DrDashboard extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $tests = ($this->businessId > 0)
            ? RestoreTest::where('business_id', $this->businessId)->get()
            : collect();

        return view('x-203::dr-dashboard', [
            'tests' => $tests,
        ]);
    }
}

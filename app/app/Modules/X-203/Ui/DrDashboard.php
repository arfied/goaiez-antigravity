<?php

declare(strict_types=1);

namespace App\Modules\X203\Ui;

use App\Modules\X203\Models\RestoreTest;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Restore tests'])]
class DrDashboard extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

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

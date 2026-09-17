<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use App\Modules\X118\Models\OnboardingRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Time to first minute'])]
class TtfmDistribution extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();
        $runs = OnboardingRun::where('business_id', $businessId)->orderBy('ttfm_ms')->get();
        return view('x-118::ttfm-distribution', ['runs' => $runs]);
    }
}

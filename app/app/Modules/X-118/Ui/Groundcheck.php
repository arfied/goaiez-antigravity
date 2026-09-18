<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use App\Modules\X118\Models\OnboardingStep;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Onboarding checks'])]
class Groundcheck extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();
        $steps = OnboardingStep::where('business_id', $businessId)->orderByDesc('id')->get();
        return view('x-118::groundcheck', ['steps' => $steps]);
    }
}

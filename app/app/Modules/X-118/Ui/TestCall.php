<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use App\Modules\X118\Models\OnboardingRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Direct Test Call'])]
class TestCall extends Component
{
    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $runs = OnboardingRun::where('business_id', Tenancy::idOrFail())->orderByDesc('id')->get();

        return view('x-118::test-call', ['runs' => $runs]);
    }
}

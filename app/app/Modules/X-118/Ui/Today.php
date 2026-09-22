<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use App\Modules\X118\Actions\OnboardingConfirmAction;
use App\Modules\X118\Models\OnboardingRun;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'First wins'])]
class Today extends Component
{
    public ?string $confirmSuccess = null;

    public function confirmRun(int $runId, OnboardingConfirmAction $action): void
    {
        $action->handle(Tenancy::idOrFail(), $runId);

        $run = OnboardingRun::where('business_id', Tenancy::idOrFail())->find($runId);

        if ($run === null || $run->status !== 'confirmed') {
            $this->confirmSuccess = 'That run is still live.';

            return;
        }

        $this->confirmSuccess = $run->business_name.' is confirmed. '
            .'Nothing reads a confirmed onboarding run yet.';
    }

    public function render()
    {
        abort_unless(Tenancy::check(), 403);
        $businessId = Tenancy::idOrFail();
        $runs = OnboardingRun::where('business_id', $businessId)->orderByDesc('id')->get();

        return view('x-118::today', ['runs' => $runs]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X118\Ui;

use App\Modules\X118\Actions\OnboardingStartAction;
use App\Modules\X118\Actions\OnboardingTestCallAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class DayOneSignup extends Component
{
    public int $askedFieldsCount = 2;

    public string $businessName = '';

    public string $contactPhone = '';

    public string $vertical = 'plumbing';

    public int $step = 1;

    #[Locked]
    public ?int $businessId = null;

    #[Locked]
    public ?int $runId = null;

    public ?string $provisionedNumber = null;

    public ?string $callSid = null;

    public string $callStatus = 'idle';

    public array $callTranscript = [];

    public ?string $errorMessage = null;

    protected $rules = [
        'businessName' => 'required|min:3',
        'contactPhone' => 'required|min:7',
    ];

    public function startSignup(): void
    {
        $this->validate();
        $this->errorMessage = null;

        $user = Auth::user();
        if (! $user) {
            $this->errorMessage = 'You must be signed in to create an account.';

            return;
        }

        try {
            $starter = app(OnboardingStartAction::class);
            $res = $starter->handle($user, $this->businessName, $this->contactPhone);

            $this->businessId = (int) $res['business_id'];
            $this->runId = (int) $res['run_id'];
            $this->provisionedNumber = (string) $res['provisioned_number'];
            $this->askedFieldsCount = (int) $res['asked_fields_count'];
            $this->step = 2;
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to provision tenant: ';
        }
    }

    public function triggerTestCall(): void
    {
        if (! $this->businessId || ! $this->runId) {
            $this->errorMessage = 'No active onboarding run found.';

            return;
        }

        try {
            $testCaller = app(OnboardingTestCallAction::class);
            $res = $testCaller->handle($this->businessId, $this->runId);

            $this->callSid = $res['call_sid'];
            $this->callStatus = 'connected';
            $this->step = 3;

            $this->callTranscript = $res['transcript'] ?? [];
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to place test call. Please try again.';
        }
    }

    public function resetOnboarding(): void
    {
        $this->reset([
            'businessName',
            'contactPhone',
            'businessId',
            'runId',
            'provisionedNumber',
            'callSid',
            'callTranscript',
            'errorMessage',
        ]);
        $this->step = 1;
        $this->askedFieldsCount = 2;
        $this->callStatus = 'idle';
    }

    public function render()
    {
        return view('x-118::day-one-signup');
    }
}

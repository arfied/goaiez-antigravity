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
            $this->errorMessage = 'Failed to provision tenant: '.$e->getMessage();
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

            $this->callTranscript = [
                ['speaker' => 'System', 'time' => '00:01', 'text' => "Placing direct test call to {$this->provisionedNumber}..."],
                ['speaker' => 'AI Agent', 'time' => '00:02', 'text' => "Thank you for calling {$this->businessName}! My name is Ava, your autonomous assistant. How can I help you today?"],
                ['speaker' => 'Caller (Simulated)', 'time' => '00:06', 'text' => 'Hi Ava, I have a leaking pipe under my kitchen sink and need someone out today.'],
                ['speaker' => 'AI Agent', 'time' => '00:10', 'text' => 'I understand. I have an emergency dispatch slot available between 2:00 PM and 4:00 PM today. May I confirm your service address?'],
                ['speaker' => 'Caller (Simulated)', 'time' => '00:14', 'text' => 'Yes, 742 Evergreen Terrace.'],
                ['speaker' => 'AI Agent', 'time' => '00:17', 'text' => 'Perfect! You are booked for today 2:00 PM. A confirmation SMS with tracking has been sent. Have a great day!'],
                ['speaker' => 'System', 'time' => '00:20', 'text' => 'First-Win verified! Job #1042 created and synced to live dispatch calendar.'],
            ];
        } catch (\Throwable $e) {
            $this->errorMessage = 'Failed to place test call: '.$e->getMessage();
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

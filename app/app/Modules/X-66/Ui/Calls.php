<?php

declare(strict_types=1);

namespace App\Modules\X66\Ui;

use App\Modules\X188\Domain\NumberPoolManager;
use App\Modules\X66\Domain\VoiceSessionEngine;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Models\CallTurn;
use App\Modules\X66\Models\Voicemail;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Calls'])]
class Calls extends Component
{
    public ?int $selectedSessionId = null;

    public ?string $errorMessage = null;

    public string $callSid = '';

    public string $fromPhone = '';

    public string $toPhone = '';

    public ?string $success = null;

    public ?string $error = null;

    public function recordCall(): void
    {
        $this->success = null;
        $this->error = null;

        if (empty($this->callSid)) {
            $this->error = 'Call SID is required.';

            return;
        }

        if (! Tenancy::check()) {
            $this->error = 'No business is in view — open one from Tenant locations first.';

            return;
        }

        $result = app(VoiceSessionEngine::class)->handleRing(
            Tenancy::idOrFail(),
            $this->callSid,
            $this->fromPhone,
            $this->toPhone
        );

        $this->success = 'Recorded call '.$result->call_sid.'. This feeds the latency lists; nothing downstream is wired to it yet.';
        $this->callSid = '';
        $this->fromPhone = '';
        $this->toPhone = '';
    }

    public function select(int $sessionId): void
    {
        if ($this->selectedSessionId === $sessionId) {
            $this->selectedSessionId = null;
        } else {
            $this->selectedSessionId = $sessionId;
        }
    }

    public function render()
    {
        $businessId = Tenancy::id();
        $this->errorMessage = null;

        if ($businessId === null) {
            // A platform admin reaches this door with no tenant (wave 832): render the
            // empty screen rather than throw, and say why nothing is here.
            $this->errorMessage = 'No business is in view — open one from Tenant locations first.';

            return view('x-66::calls', [
                'calls' => collect(),
                'assignedNumber' => null,
                'transcriptsExist' => [],
                'voicemailsExist' => [],
                'turns' => null,
                'voicemail' => null,
            ]);
        }

        $assignedNumber = null;
        try {
            $manager = app(NumberPoolManager::class);
            $assignedNumber = $manager->getActiveNumber($businessId);
        } catch (\Throwable $e) {
            $this->errorMessage = "We couldn't load the assigned number.";
        }

        $calls = CallSession::where('business_id', $businessId)
            ->orderBy('id', 'desc')
            ->get();

        $sessionIds = $calls->pluck('id')->toArray();

        $transcriptsExist = CallTurn::where('business_id', $businessId)
            ->whereIn('session_id', $sessionIds)
            ->pluck('session_id')
            ->unique()
            ->flip()
            ->map(fn () => true)
            ->toArray();

        $voicemailsExist = Voicemail::where('business_id', $businessId)
            ->whereIn('call_session_id', $sessionIds)
            ->pluck('call_session_id')
            ->unique()
            ->flip()
            ->map(fn () => true)
            ->toArray();

        $turns = null;
        $voicemail = null;
        if ($this->selectedSessionId) {
            $turns = CallTurn::where('business_id', $businessId)
                ->where('session_id', $this->selectedSessionId)
                ->orderBy('created_at', 'asc')
                ->get();

            $voicemail = Voicemail::where('business_id', $businessId)
                ->where('call_session_id', $this->selectedSessionId)
                ->first();
        }

        return view('x-66::calls', [
            'calls' => $calls,
            'assignedNumber' => $assignedNumber,
            'transcriptsExist' => $transcriptsExist,
            'voicemailsExist' => $voicemailsExist,
            'turns' => $turns,
            'voicemail' => $voicemail,
        ]);
    }
}

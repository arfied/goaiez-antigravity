<?php

declare(strict_types=1);

namespace App\Modules\X66\Ui;

use App\Modules\X188\Models\NumberAssignment;
use App\Modules\X188\Models\NumberPool;
use App\Modules\X66\Models\CallSession;
use App\Modules\X66\Models\CallTurn;
use App\Modules\X66\Models\Voicemail;
use App\Support\Tenancy;
use Livewire\Component;

class Calls extends Component
{
    public ?int $selectedSessionId = null;

    public ?string $errorMessage = null;

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
        $businessId = Tenancy::idOrFail();
        $this->errorMessage = null;

        $assignedNumber = null;
        try {
            $assignment = NumberAssignment::where('business_id', $businessId)->first();
            if ($assignment) {
                $poolNumber = NumberPool::where('id', $assignment->phone_number_id)->first();
                $assignedNumber = $poolNumber?->phone_number;
            }
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

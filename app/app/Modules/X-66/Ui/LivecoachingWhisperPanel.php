<?php

declare(strict_types=1);

namespace App\Modules\X66\Ui;

use App\Modules\X66\Actions\VoiceSessionEngine;
use App\Modules\X66\Models\CallAutopsy;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Call coaching'])]
class LivecoachingWhisperPanel extends Component
{
    public int $sessionId = 0;

    public string $transcript = '';

    public ?string $success = null;

    public ?string $error = null;

    public function coach(): void
    {
        $this->success = null;
        $this->error = null;

        if ($this->sessionId === 0) {
            $this->error = 'Session ID is required.';

            return;
        }
        if (trim($this->transcript) === '') {
            $this->error = 'Transcript cannot be empty.';

            return;
        }

        $businessId = Tenancy::idOrFail();
        $autopsy = VoiceSessionEngine::coach($businessId, $this->sessionId, $this->transcript);

        $this->success = 'Processed transcript for session '.$this->sessionId.'. This feeds the coaching notes. Objection detected: '.($autopsy->sentiment === 'Negative' ? 'yes' : 'no').'.';
    }

    public function render()
    {
        $businessId = Tenancy::idOrFail();
        $autopsies = CallAutopsy::where('business_id', $businessId)->orderByDesc('id')->get();

        return view('x-66::livecoaching-whisper-panel', ['autopsies' => $autopsies]);
    }
}

<?php

declare(strict_types=1);

namespace App\Livewire\Advanced;

use App\Models\Call;
use App\Services\Voice\CallForwarding;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.account')]
class Voice extends Component
{
    public function render(CallForwarding $forwarding): View
    {
        return view('livewire.advanced.voice', [
            'mode' => $forwarding->modeFor(),
            'calls' => Call::query()
                ->with('voicemail')
                ->orderByRaw('started_at DESC NULLS LAST')
                ->orderByDesc('id')
                ->limit(25)
                ->get(),
        ]);
    }
}

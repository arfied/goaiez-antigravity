<?php

declare(strict_types=1);

namespace App\Modules\X66\Ui;

use App\Modules\X66\Models\CallAutopsy;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Call coaching'])]
class LivecoachingWhisperPanel extends Component
{
    public function render()
    {
        $businessId = Tenancy::idOrFail();
        $autopsies = CallAutopsy::where('business_id', $businessId)->orderByDesc('id')->get();
        return view('x-66::livecoaching-whisper-panel', ['autopsies' => $autopsies]);
    }
}

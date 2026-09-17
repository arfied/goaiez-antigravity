<?php

declare(strict_types=1);

namespace App\Modules\X66\Ui;

use App\Modules\X66\Models\CallSession;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Call latency'])]
class LatencyP50p95Per extends Component
{
    public function render()
    {
        $businessId = Tenancy::idOrFail();
        $calls = CallSession::where('business_id', $businessId)->orderByDesc('latency_ms')->get();

        return view('x-66::latency-p50p95-per', ['calls' => $calls]);
    }
}

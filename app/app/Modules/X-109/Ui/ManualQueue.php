<?php

declare(strict_types=1);

namespace App\Modules\X109\Ui;

use App\Modules\X109\Models\CaptchaQuota;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ManualQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function render()
    {
        $queue = ($this->businessId > 0)
            ? CaptchaQuota::where('business_id', $this->businessId)->where('status', 'queued_manual')->get()
            : collect();

        return view('x-109::manual-queue', [
            'queue' => $queue,
        ]);
    }
}

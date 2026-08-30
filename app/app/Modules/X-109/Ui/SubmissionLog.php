<?php

declare(strict_types=1);

namespace App\Modules\X109\Ui;

use App\Modules\X109\Models\CaptchaQuota;
use Livewire\Component;

class SubmissionLog extends Component
{
    public int $businessId = 0;

    public function render()
    {
        $logs = ($this->businessId > 0)
            ? CaptchaQuota::where('business_id', $this->businessId)->where('status', 'submitted')->get()
            : collect();

        return view('x-109::submission-log', [
            'logs' => $logs,
        ]);
    }
}

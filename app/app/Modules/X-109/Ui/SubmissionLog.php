<?php

declare(strict_types=1);

namespace App\Modules\X109\Ui;

use App\Modules\X109\Models\CaptchaQuota;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Contact Form Submission Log'])]
class SubmissionLog extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public function mount(): void
    {
        if ($this->businessId === 0) {
            $this->businessId = Tenancy::id() ?: 0;
            if ($this->businessId <= 0) {
                abort(403, 'Tenant context is required');
            }
        }
        Tenancy::set($this->businessId);
    }

    public function toggleSample(): void
    {
        $this->isSample = ! $this->isSample;
    }

    public function render()
    {
        if ($this->isSample) {
            $logs = collect([
                (object) [
                    'campaign_id' => 12,
                    'prospect_identifier' => '9401',
                    'available_quota' => 42,
                    'updated_at' => now()->subMinutes(15),
                ],
                (object) [
                    'campaign_id' => 12,
                    'prospect_identifier' => '9405',
                    'available_quota' => 41,
                    'updated_at' => now()->subMinutes(25),
                ],
            ]);
        } else {
            $logs = CaptchaQuota::where('business_id', $this->businessId)
                ->where('status', 'submitted')
                ->whereNotNull('prospect_identifier')
                ->orderByDesc('updated_at')
                ->get();
        }

        return view('x-109::submission-log', [
            'logs' => $logs,
        ]);
    }
}

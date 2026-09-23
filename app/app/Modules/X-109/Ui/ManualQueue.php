<?php

declare(strict_types=1);

namespace App\Modules\X109\Ui;

use App\Modules\X109\Actions\FormSubmitAction;
use App\Modules\X109\Models\CaptchaQuota;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Manual Form Review Queue'])]
class ManualQueue extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $isSample = false;

    public ?string $attentionMessage = null;

    public bool $actionFailed = false;

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
        $this->attentionMessage = null;
        $this->actionFailed = false;
    }

    public function resubmit(int $queueId, FormSubmitAction $action): void
    {
        if ($this->isSample) {
            return;
        }

        $this->attentionMessage = null;
        $this->actionFailed = false;

        $queueItem = CaptchaQuota::where('business_id', $this->businessId)
            ->where('status', 'queued_manual')
            ->find($queueId);

        if (! $queueItem) {
            $this->actionFailed = true;

            return;
        }

        $result = $action->submitForm(
            businessId: $this->businessId,
            campaignId: (string) $queueItem->campaign_id,
            prospectIdentifier: (string) $queueItem->prospect_identifier
        );

        if (in_array($result['status'], ['queued_manual', 'skipped_duplicate'])) {
            $this->attentionMessage = $result['message'];
        }
    }

    public function render()
    {
        if ($this->isSample) {
            $queue = collect([
                (object) [
                    'id' => 101,
                    'campaign_id' => '12',
                    'prospect_identifier' => '9401',
                    'updated_at' => now()->subMinutes(15),
                ],
            ]);
        } else {
            $queue = CaptchaQuota::where('business_id', $this->businessId)
                ->where('status', 'queued_manual')
                ->orderByDesc('updated_at')
                ->get();
        }

        return view('x-109::manual-queue', [
            'queue' => $queue,
        ]);
    }
}

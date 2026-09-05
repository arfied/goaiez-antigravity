<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Models\CampaignStep;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SequenceBuilder extends Component
{
    #[Locked]
    public int $businessId = 0;

    public bool $failed = false;

    public bool $isSample = false;

    public string $newCampaignId = '';

    public string $newChannel = 'sms';

    public string $newTemplateName = '';

    public function duplicate(string $campaignId, CampaignCreateAction $action): void
    {
        try {
            $steps = CampaignStep::where('business_id', $this->businessId)
                ->where('campaign_id', $campaignId)
                ->orderBy('step_number')
                ->get()
                ->map(fn (CampaignStep $s) => [
                    'channel' => $s->channel,
                    'template_name' => $s->template_name,
                    'delay_days' => $s->delay_days,
                ])
                ->toArray();

            $action->createCampaign($this->businessId, $campaignId.'-copy', $steps);
        } catch (\Throwable $e) {
            $this->failed = true;
        }
    }

    public function compose(CampaignCreateAction $action): void
    {
        if (empty($this->newCampaignId) || empty($this->newTemplateName)) {
            return;
        }

        try {
            $action->createCampaign($this->businessId, $this->newCampaignId, [
                [
                    'channel' => $this->newChannel,
                    'template_name' => $this->newTemplateName,
                    'delay_days' => 1,
                ],
            ]);
            $this->newCampaignId = '';
            $this->newTemplateName = '';
        } catch (\Throwable $e) {
            $this->failed = true;
        }
    }

    public function render()
    {
        $campaigns = ($this->businessId > 0 && ! $this->failed)
            ? CampaignStep::where('business_id', $this->businessId)
                ->orderBy('campaign_id')
                ->orderBy('step_number')
                ->get()
                ->groupBy('campaign_id')
            : collect();

        return view('x-186::sequence-builder', [
            'campaigns' => $campaigns,
        ]);
    }
}

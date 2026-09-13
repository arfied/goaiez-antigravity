<?php

declare(strict_types=1);

namespace App\Modules\X186\Ui;

use App\Modules\X186\Actions\CampaignCreateAction;
use App\Modules\X186\Models\CampaignStep;
use App\Support\Tenancy;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('components.account.layout', ['heading' => 'Your campaign sequences'])]
class SequenceBuilder extends Component
{
    #[Locked]
    public int $businessId = 0;

    public function mount(int $businessId = 0)
    {
        $this->businessId = $businessId !== 0 ? $businessId : (Tenancy::id() ?? 0);
    }

    public bool $failed = false;

    public bool $isSample = false;

    public string $newCampaignId = '';

    public string $newChannel = 'sms';

    public string $newTemplateName = '';

    public ?string $composeError = null;

    public function duplicate(string $campaignId, CampaignCreateAction $action): void
    {
        try {
            $campaignSteps = CampaignStep::where('business_id', $this->businessId)
                ->where('campaign_id', $campaignId)
                ->orderBy('step_number')
                ->get();

            $steps = [];
            foreach ($campaignSteps as $s) {
                $steps[] = [
                    'channel' => $s->channel,
                    'template_name' => $s->template_name,
                    'delay_days' => $s->delay_days,
                ];
            }

            $action->createCampaign($this->businessId, $campaignId.'-copy', $steps);
        } catch (\Throwable $e) {
            $this->failed = true;
        }
    }

    public function compose(CampaignCreateAction $action): void
    {
        if (empty($this->newCampaignId) || empty($this->newTemplateName)) {
            $this->composeError = 'A sequence needs a campaign id and a template name.';

            return;
        }

        $this->composeError = null;

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

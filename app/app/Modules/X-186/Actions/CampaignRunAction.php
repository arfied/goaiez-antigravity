<?php

declare(strict_types=1);

namespace App\Modules\X186\Actions;

use App\Modules\X186\Events\CampaignExhausted;
use App\Modules\X186\Events\CampaignSent;
use App\Modules\X186\Events\SendRequested;
use App\Modules\X186\Models\CampaignRun;
use App\Modules\X186\Models\CampaignStep;
use Illuminate\Support\Facades\Event;

final class CampaignRunAction
{
    /**
     * Runs drip sequence step (G2-14, G10-25, G11-24).
     * 1. Every send.requested carries class = marketing (TEST ANCHOR & P-063, G11-24).
     * 2. An open RECOVER suppresses the whole sequence (TEST ANCHOR & P-205).
     */
    public function runNextStep(
        int $businessId,
        string $campaignId,
        int $personId,
        bool $hasOpenRecover = false
    ): CampaignRun {
        $run = CampaignRun::firstOrCreate(
            ['business_id' => $businessId, 'campaign_id' => $campaignId, 'person_id' => $personId],
            ['current_step' => 1, 'is_active' => true, 'is_suppressed' => false]
        );

        if (! $run->is_active) {
            return $run;
        }

        // TEST ANCHOR: An open RECOVER suppresses the whole sequence (P-205)
        if ($hasOpenRecover) {
            $run->update([
                'is_suppressed' => true,
                'suppression_reason' => 'Suppressed by active RECOVER intent in progress (P-205)',
            ]);

            return $run;
        }

        $step = CampaignStep::where('business_id', $businessId)
            ->where('campaign_id', $campaignId)
            ->where('step_number', $run->current_step)
            ->first();

        if (! $step) {
            $run->update(['is_active' => false]);
            Event::dispatch(new CampaignExhausted($businessId, $campaignId, $personId));

            return $run;
        }

        // TEST ANCHOR: every send.requested carries class = marketing
        Event::dispatch(new SendRequested(
            businessId: $businessId,
            personId: $personId,
            channel: $step->channel,
            messageClass: 'marketing', // TEST ANCHOR & G11-24
            templateName: $step->template_name
        ));

        Event::dispatch(new CampaignSent($businessId, $campaignId, $personId, $step->step_number, $step->channel));

        $run->increment('current_step');

        return $run;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X186\Actions;

use App\Modules\X186\Events\CampaignReplied;
use App\Modules\X186\Events\SequenceStopped;
use App\Modules\X186\Models\CampaignRun;
use Illuminate\Support\Facades\Event;

final class SequenceStopAction
{
    /**
     * A reply on ANY channel stops every pending step for that Person within one cycle (TEST ANCHOR & P-075, G11-28, G19-04).
     */
    public function stopAllSequencesForPerson(int $businessId, int $personId, string $replyChannel = 'sms'): int
    {
        $activeRuns = CampaignRun::where('business_id', $businessId)
            ->where('person_id', $personId)
            ->where('is_active', true)
            ->get();

        foreach ($activeRuns as $run) {
            $run->update([
                'is_active' => false,
                'stopped_reason' => "Stopped by inbound customer reply on channel: {$replyChannel}",
            ]);

            Event::dispatch(new CampaignReplied($businessId, $run->campaign_id, $personId, $replyChannel));
            Event::dispatch(new SequenceStopped($businessId, $run->campaign_id, $personId, "Inbound reply on {$replyChannel}"));
        }

        return $activeRuns->count();
    }
}

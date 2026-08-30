<?php

declare(strict_types=1);

namespace App\Modules\X185\Actions;

use App\Modules\X185\Events\CampaignSent;
use App\Modules\X185\Events\CartNudged;
use App\Modules\X185\Models\Sequence;
use App\Modules\X185\Models\SequenceStep;
use Illuminate\Support\Facades\Event;

final class CampaignRunAction
{
    public function executeStep(int $businessId, int $sequenceId, int $stepNumber, ?int $prospectId = null): void
    {
        $seq = Sequence::where('business_id', $businessId)->findOrFail($sequenceId);
        $step = SequenceStep::where('business_id', $businessId)
            ->where('sequence_id', $seq->id)
            ->where('step_number', $stepNumber)
            ->firstOrFail();

        Event::dispatch(new CampaignSent($businessId, $seq->id, $stepNumber, $step->channel));

        if ($prospectId !== null) {
            Event::dispatch(new CartNudged($businessId, $seq->id, $prospectId));
        }
    }
}

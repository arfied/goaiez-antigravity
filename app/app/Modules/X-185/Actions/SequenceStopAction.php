<?php

declare(strict_types=1);

namespace App\Modules\X185\Actions;

use App\Modules\X185\Events\CampaignReplied;
use App\Modules\X185\Events\SequenceStopped;
use App\Modules\X185\Models\Sequence;
use Illuminate\Support\Facades\Event;

final class SequenceStopAction
{
    public function stopSequence(int $businessId, int $sequenceId, string $inboundChannel = 'sms'): void
    {
        $seq = Sequence::where('business_id', $businessId)->findOrFail($sequenceId);
        $seq->update(['is_active' => false]);

        Event::dispatch(new CampaignReplied($businessId, $seq->id, $inboundChannel));
        Event::dispatch(new SequenceStopped($businessId, $seq->id, 'Inbound response received: outreach terminated'));
    }
}

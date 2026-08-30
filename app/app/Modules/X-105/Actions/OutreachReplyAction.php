<?php

declare(strict_types=1);

namespace App\Modules\X105\Actions;

use App\Modules\X105\Events\ProspectEngaged;
use App\Modules\X105\Events\ReplyReceived;
use App\Modules\X105\Models\LadderStep;
use App\Modules\X105\Models\OutreachLadder;
use Illuminate\Support\Facades\Event;

final class OutreachReplyAction
{
    /**
     * Handles inbound reply.
     * 1. Stops the ladder (P-075, G3-07).
     * 2. Shifts Person strictly to SMS-class touches (TEST ANCHOR).
     * 3. Cancels all pending email / voice / other rungs (TEST ANCHOR).
     */
    public function handleInboundReply(
        int $businessId,
        int $ladderId,
        string $replyChannel = 'email'
    ): OutreachLadder {
        $ladder = OutreachLadder::where('business_id', $businessId)->findOrFail($ladderId);

        $ladder->update([
            'status' => 'replied',
            'exclusive_sms_mode' => true, // Every subsequent touch is SMS-class (TEST ANCHOR)
        ]);

        // Cancel all pending email / voice / mail rungs (TEST ANCHOR)
        LadderStep::where('business_id', $businessId)
            ->where('ladder_id', $ladder->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        Event::dispatch(new ReplyReceived($businessId, $ladder->id, (int) $ladder->person_id, $replyChannel));
        Event::dispatch(new ProspectEngaged($businessId, (int) $ladder->person_id));

        return $ladder;
    }
}

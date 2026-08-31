<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\AutopilotActionType;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A new activity item, pushed to staff screens.
 *
 * **The channel name is not authorization.** Clients choose what they subscribe
 * to, so `business.7` is attacker-controlled input; routes/channels.php is the
 * only thing enforcing the boundary.
 *
 * **The payload carries the fact, not the content.** A broadcast passes through
 * the Reverb server and lands in browser memory and devtools, so it holds ids
 * and a closed vocabulary and nothing else — no customer names, no review text,
 * no phone numbers. The client fetches detail through a tenant-scoped,
 * policy-checked route, which is also the only way the payload cannot drift out
 * of sync with authorization.
 *
 * A test asserts the payload keys are exactly the allowlist, so this survives
 * future edits to the model rather than depending on nobody adding a field.
 *
 * ShouldBroadcast rather than ShouldBroadcastNow: `29` §2 rule 41 forbids slow
 * work on a synchronous path, and a broadcast must never sit between a caller
 * and a ringing phone.
 *
 * ShouldDispatchAfterCommit, always. ⚠️ `ReviewRouter::recordDecision()` is the
 * only `ActivityService` caller that runs inside a `DB::transaction()` today —
 * the other three, in `TokenService`, `AutopilotJob` and `AnalyzeReviewJob`, do
 * not. Stating it the other way round ("every caller does") was false and made
 * this interface look like a restatement of existing practice rather than what
 * it is: the thing that makes the transactional case safe for every future
 * caller without any of them having to know. Inside such a transaction, and
 * without this, the dispatch happens the moment `dispatch()` is called, before
 * the transaction that created `activityId` has committed. If that transaction
 * then rolls back, the feed item, and everything else it was written
 * alongside, never existed — but the broadcast already reached every open
 * staff screen, announcing an activity that no query will ever find. This is
 * the interface doing the work, not `config/queue.php`: every connection there
 * sets `after_commit` to `false`, so absent this, a rollback is invisible to
 * the dispatch path entirely.
 */
final class ActivityRecorded implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $businessId,
        public readonly int $activityId,
        public readonly AutopilotActionType $action,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('business.'.$this->businessId)];
    }

    public function broadcastAs(): string
    {
        return 'activity.recorded';
    }

    /**
     * @return array<string, int|string|bool>
     */
    public function broadcastWith(): array
    {
        // Explicit, always. The default serializes the whole model, which is how
        // personal data ends up on the wire by accident.
        return [
            'id' => $this->activityId,
            'action' => $this->action->value,
            'title' => $this->action->title(),
            'needs_owner' => $this->action->needsOwner(),
        ];
    }
}

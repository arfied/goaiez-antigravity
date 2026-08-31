<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SupportChannel;
use App\Services\Support\SupportDesk;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * The work-waiting row for one support ticket (T137 `SL-7`).
 *
 * ⚠️ **NO `BelongsToTenant`, AND IT IS THE ONE THING ON THIS TABLE THAT NEEDED
 * AN ARGUMENT.** `28` §9.1's internal roles belong to no business, so a staff
 * queue is read with **no tenant established** — and under `FORCE` row-level
 * security a query with no `app.business_id` returns nothing at all, including
 * one that reaches for `withoutGlobalScope()`, because the policy is the
 * database's and not Eloquent's. A tenant-owned queue table is therefore a queue
 * nobody can read.
 *
 * WHAT REPLACES THE SCOPE. The row carries **no personal data and no text**: two
 * foreign keys, a channel and three timestamps. The subject line, the thread and
 * every word anybody wrote stay on {@see SupportTicket} and
 * {@see SupportMessage}, behind RLS — so the strongest thing this table can leak
 * to a reader who should not have it is *that account 14 is waiting on us*.
 * {@see SupportDesk} is the only writer and the only
 * reader, held there by a chokepoint lint, and the two screens above it sit
 * behind `SupportAccess::GATE`.
 *
 * ⛔ **IT IS NOT A SECOND COPY OF THE THREAD AND MUST NOT BECOME ONE.** 2186's
 * defect one table over: a second row answering "what did they ask" is a second
 * answer that can disagree with the first. The status lives on the ticket; what
 * lives here is the lifecycle the *queue* needs — when it opened, when we first
 * replied, and whether it is done.
 *
 * @property-read int $id
 * @property int $business_id
 * @property int $support_ticket_id
 * @property SupportChannel $channel
 * @property CarbonImmutable $opened_at
 * @property ?CarbonImmutable $first_response_at
 * @property ?CarbonImmutable $resolved_at
 */
final class SupportQueueEntry extends Model
{
    /**
     * No `created_at` and no `updated_at`.
     *
     * `opened_at` is this row's creation time under a name that says what it
     * means, and a second copy of it is a second thing that can disagree. The
     * lifecycle here is three named timestamps and nothing else — which is also
     * what makes the column-set assertion in `SupportDeskTest` a claim worth
     * making.
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'support_ticket_id' => 'integer',
            'channel' => SupportChannel::class,
            'opened_at' => 'immutable_datetime',
            'first_response_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}

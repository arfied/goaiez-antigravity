<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\FixThenAskResponse;
use App\Enums\OutreachChannel;
use App\Enums\TriageStatus;
use Database\Factories\TriageConversationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The recovery conversation a below-threshold review opens
 * (DATA-MODEL §5.7). ai_paused is the human-takeover flag: once the owner
 * steps in, AI stays out.
 *
 * ⚠️ NO CODE HERE DERIVES `channel` FROM EVIDENCE. ReviewRouter::openTriage()
 * leaves it at the column default and TriageConversationFactory writes the same
 * value explicitly — same result, neither one a decision. Nothing that sets this
 * column knows how the customer can be reached: the row may have no customer at
 * all, and a customer may have no phone, no email and no consent record. **Row 4
 * must derive the channel from the customer's own consent records, never read it
 * from here.** Iterating these rows and calling permit($customer,
 * $conversation->channel) would ask for SMS on every one, and the follow-up
 * would silently reach nobody.
 *
 * ⚠️ **`owner_notified_at` STILL HAS NO WRITER AND THAT IS NOW A DECISION
 * RATHER THAN A GAP** (2703). The recovery queue writes `status`, `resolution`
 * and `ai_paused`; it deliberately leaves this one alone, because `17`
 * TRIAGE-03 defines it as *"notify owner via SMS + email … set
 * `owner_notified_at`"* and its readers will use it to decide not to tell
 * somebody twice. An owner who found the conversation themselves has not been
 * notified, and stamping it because they clicked would permanently suppress the
 * notification that ticket owes them. Its writer is the notification path on
 * the messaging lane.
 *
 * ⚠️ **`transcript` IS STILL WRITTEN ONCE AS `[]` AND NOTHING APPENDS TO IT**
 * (942). It is the customer-facing turn log of TRIAGE-01's AI SMS loop, which
 * is unbuilt; `resolution` is the owner's own note and is a different column on
 * purpose. Reading the transcript would render *"nothing was said"* where the
 * truth is *"we never recorded what was said"*.
 *
 * ⛔ **`ai_paused` HAS A WRITER AND NO READER IN `app/`** (2706). See
 * `ReviewRouter::setTriageTakeover()` — the reader is TRIAGE-01's loop, and
 * until it exists no screen may claim this column prevents an automated
 * message. ⚠️ Named in prose rather than with `{@see}`: Pint turns that into a
 * real `use`, and a model importing a service is a dependency a formatter
 * invented.
 *
 * ⚠️ **`outreach_draft` IS A DRAFT AND NEVER A SEND** (T176 P15, 4350–4355).
 * It holds the win-back message `DraftRecoveryOutreachJob` wrote for the owner
 * to send themselves, through whatever channel they already use to reach that
 * customer. Nothing in `app/` sends from this table and nothing in this slice
 * added a sender: the recovery outreach path is draft-and-approve, and the
 * approval is the owner pressing send in their own phone or mail client. **Do
 * not wire a sender to this column without the consent lane, the suppression
 * check and the arbiter every other outbound path goes through** — a `text`
 * column is not a send permit.
 *
 * ⚠️ **`resolved_at`, `fix_then_ask_offered_at`, `fix_then_ask_response` AND
 * `fix_then_ask_responded_at` ARE T546 §37.3(1)'S FIELDS, WAVE 38 LANE C
 * (10590–10609).** `resolved_at` is written by
 * `ReviewRouter::recordTriageOutcome()` whenever `$to === TriageStatus::Resolved`
 * — distinct from `updated_at`, which moves on every edit to this row and is
 * therefore the wrong clock for "how long has this been fixed". The other
 * three are the fix-then-ask check-in's own send-and-answer record, written by
 * `ReviewRouter::recordFixThenAskOffered()` and
 * `ReviewRouter::recordFixThenAskResponse()` — **never by the sender, the job
 * or the controller directly**, on `Architecture\ReviewsTest`'s own rule that
 * this model is reachable from exactly one file.
 *
 * @property TriageStatus $status
 * @property OutreachChannel $channel
 * @property ?array<int|string, mixed> $transcript
 * @property ?string $resolution
 * @property bool $ai_paused
 * @property ?Carbon $owner_notified_at
 * @property ?string $outreach_draft
 * @property ?Carbon $outreach_draft_at
 * @property ?string $outreach_draft_fallback_reason
 * @property ?Carbon $resolved_at
 * @property ?Carbon $fix_then_ask_offered_at
 * @property ?FixThenAskResponse $fix_then_ask_response
 * @property ?Carbon $fix_then_ask_responded_at
 */
final class TriageConversation extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<TriageConversationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => OutreachChannel::class,
            'status' => TriageStatus::class,
            'transcript' => 'array',
            'ai_paused' => 'boolean',
            'owner_notified_at' => 'datetime',
            'outreach_draft_at' => 'datetime',
            'resolved_at' => 'datetime',
            'fix_then_ask_offered_at' => 'datetime',
            'fix_then_ask_response' => FixThenAskResponse::class,
            'fix_then_ask_responded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Review, $this>
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}

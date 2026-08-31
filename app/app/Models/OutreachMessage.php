<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\MessagingLane;
use App\Enums\OutreachChannel;
use App\Enums\OutreachStatus;
use Database\Factories\OutreachMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An outbound message (DATA-MODEL §5.7).
 *
 * `lane` here is a frozen record of which consent lane the send used —
 * settable by the send service, unlike customers.messaging_lane, which is
 * derived current state and unsettable.
 *
 * ⚠️ **THE `@property` LINES ARE LOAD-BEARING, NOT DECORATION.** Without them
 * Larastan reads these columns as the raw database types and cannot see
 * `casts()`, so a `match ($message->status)` against `OutreachStatus` cases
 * analyses as *"comparison between string and enum is always false"* — which is
 * an accurate report of what the analyser can prove and a false report of what
 * runs, because the cast is applied at runtime and the match works. That gap is
 * the dangerous kind: the code behaves correctly, the gate says the branch is
 * dead, and the tempting fixes are a cast or an ignore. `TriageConversation`
 * already annotates its own casts for the same reason.
 *
 * ⚠️ **`sent_at` IS ANNOTATED TOO, AND THIS PARAGRAPH USED TO SAY IT DID NOT
 * NEED TO BE.** It read *"the `datetime` casts it already infers correctly"* —
 * which held for as long as nothing called a Carbon method on one in analysed
 * code. The tenant CRM's contact timeline does (`CustomerTimeline`), and
 * Larastan reported `Cannot call method toImmutable() on string`: an accurate
 * report of what it can prove and a false report of what runs, which is exactly
 * the gap the paragraph above describes for the enum columns. The remedy is the
 * same one and `Customer` already uses it — naming the class the cast returns at
 * runtime, not a cast or an ignore.
 *
 * ⚠️ **`created_at` JOINED THEM AT P20 FOR THE THIRD INSTANCE OF THE SAME
 * GAP.** The cast returns `Illuminate\Support\Carbon` at runtime and Larastan
 * inferred the base `Carbon\Carbon` from `casts()` alone, so a value flowing
 * into a typed array shape failed analysis while working perfectly. It matters
 * more on this column than on `sent_at`: `created_at` is the **send clock** on
 * the SMS path — `sent_at` stays null there until the carrier receipt lands —
 * so it is the one every reader of this table ends up reaching for.
 *
 * ⚠️ **`media_count` IS ANNOTATED AND CAST FOR A FOURTH REASON, WHICH IS NOT
 * THE THREE ABOVE.** `SendCredits` reads `purpose` off one of these rows with
 * `getAttribute()` under a note that the property form would be a Larastan
 * finding, because that column has neither a cast nor a `@property` line. This
 * one has both, so the reader is `$row->media_count` and the analyser sees an
 * `?int` — which matters here more than elsewhere, since the value decides how
 * many credits a tenant is charged (12461).
 *
 * ⛔ **IT WAS `carried_media`, A `?bool`, UNTIL 2026-08-30 (12461).** The owner
 * ruled that **each** photo counts, so the fact the ledger points at had to
 * become a quantity: `ceil(characters / 160) + photos`. ⚠️ **`body` is now
 * load-bearing on this table for the same reason** — it was already *"the only
 * record of the exact words a stranger received"*, and it is now also the only
 * thing that explains the size of the movement pointing at the row.
 *
 * @property OutreachChannel $channel
 * @property OutreachStatus $status
 * @property ?int $media_count
 * @property ?MessagingLane $lane
 * @property ?Carbon $sent_at
 * @property ?Carbon $delivered_at
 * @property ?Carbon $complained_at
 * @property ?Carbon $created_at
 * @property ?int $number_id
 */
final class OutreachMessage extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<OutreachMessageFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /**
     * What a credit movement or a cost entry points at when it points at one of
     * these rows.
     *
     * ⚠️ **IT LIVES ON THE MODEL BECAUSE THERE ARE TWO SENDERS, NOT ONE.**
     * `PlatformMessageSender` held it as a private constant under the note that
     * *"two spellings of one string in one class is how a ledger ends up with
     * rows nothing can find"* — and decision 2900 found the second sender,
     * `ReviewInviteSender`, debiting nothing at all. Giving that one its own
     * literal would have made the warning come true across two files instead of
     * within one, so the string moved to the thing it names. Both senders read it
     * here, and a `ref_type` that no longer matches is a compile-time edit rather
     * than a silent orphan in the ledger.
     *
     * ⚠️ **THE TWO SENDERS ARE NAMED IN PROSE RATHER THAN WITH `{@see}`, AND THE
     * OMISSION IS LOAD-BEARING.** Pint's `fully_qualified_strict_types` promotes
     * a `{@see}` into a real `use` statement — it did exactly that on this
     * slice's first `composer lint`, leaving **a model importing a service**,
     * which is a dependency edge pointing the wrong way and indistinguishable
     * from an intent to call one. `OutboundMessage` and `SentText` carry the same
     * warning for the same reason.
     */
    public const string CREDIT_REFERENCE_TYPE = 'outreach_message';

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
            'status' => OutreachStatus::class,
            'lane' => MessagingLane::class,
            // ⛔ **THIS ONE DECIDES A CHARGE** (12461). `SendCredits` reads it
            // to price the send, so a missing cast would hand the analyser a
            // `mixed` and the runtime the string `'1'` — which is truthy, and
            // arithmetic on it is right by accident rather than by type.
            'media_count' => 'integer',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            // ⛔ **NEITHER OF THESE IS A STATUS, AND THAT IS THE WHOLE REASON
            // THEY ARE COLUMNS** (6362). `OutreachStatus::Delivered` is
            // terminal, so writing it from an SES delivery event would make
            // `MailReplyRouter` drop every reply that came afterwards — which
            // that class's docblock predicted in terms. These record the two
            // mail outcomes that have to be counted once each, and
            // `MailSendingHealth` is their only writer.
            'delivered_at' => 'datetime',
            'complained_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<ReviewRequestCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ReviewRequestCampaign::class, 'campaign_id');
    }
}

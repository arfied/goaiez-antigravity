<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\MarketingTouch;
use App\Enums\OutreachChannel;
use App\Enums\OutreachStatus;
use App\Enums\ReviewInviteKind;
use App\Models\Customer;
use App\Models\OutreachMessage;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The one place an owner's sent messages are read (`28` §3.4).
 *
 * ⚠️ **`outreach_messages` HAD A WRITER AND NO READER** — decision 620's
 * inversion, and the reason this slice exists. `ReviewInviteSender` files a row
 * for every review invite, and the only other query against the table was that
 * service's own idempotency check. So the first message this product ever sent
 * to somebody else's customer (880–889) was recorded where nobody could look at
 * it, and the tenant's first question the morning after — *what did you say to
 * my customer?* — had no answer. A missing reader is worse than a missing writer
 * for exactly one reason: a missing writer eventually shows up as an empty
 * screen, and a missing reader shows up as nothing at all.
 *
 * ## Three ways this is narrower than §3.4, all structural
 *
 * **Outbound only.** §3.4 says *"every SMS, review invite, email, and AI reply
 * the system sent **or received**"* and lists `direction` among the Advanced
 * filters. ⚠️ **There is no `direction` column, and nothing inbound exists to
 * put in one** — an inbound SMS arrives on an Infobip webhook, which is row 4
 * and waits on 10DLC brand registration. A direction filter over a table with
 * one direction is decision 256's vacuous lint in a UI costume, so it is not
 * built, and the screen says the log is what we sent.
 *
 * **No triage transcripts, because there are none.** §3.4 names them as a
 * source. A triage conversation's `transcript` is written exactly once — as
 * `[]`, when the conversation is opened — and **nothing in `app/` ever appends
 * to it**. Reading it would add an always-empty column to every row and imply
 * the conversation had no messages rather than that we never recorded any.
 *
 * (Written without the `Class::member` form on purpose: the chokepoint lint
 * that holds that model to one service matches on the literal text and does not
 * strip docblocks, so the reference would trip it. Decision 603's precedent —
 * reword, never widen the allowlist.)
 *
 * **No support-bot threads.** §3.4's third source does not exist in any form.
 *
 * ## Why it is a service rather than a query in the component
 *
 * The same reason `AuditExplorer` is: this is the one place a tenant's message
 * bodies are read, an `ArchitectureTest` lint holds it here, and the next screen
 * that wants a message list should call this rather than grow a second query
 * whose tenant scope and status vocabulary drift from this one's.
 */
final class MessageLog
{
    /** How many messages one page of the log shows. */
    public const int PER_PAGE = 25;

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * The tenant's messages, newest first.
     *
     * ⚠️ **ORDERED BY `id`, NOT BY `sent_at` OR `created_at`.** Both are
     * nullable here — `sent_at` is null until a send succeeds and `created_at`
     * is Laravel's own nullable default — and Postgres sorts NULL *first* on a
     * DESC ordering, so ordering by either would put queued and undated rows
     * above every message actually sent. An `ArchitectureTest` lint permits no
     * column but `id` for exactly this reason, and `id` is the only monotonic
     * thing on the row.
     *
     * @return LengthAwarePaginator<int, OutreachMessage>
     */
    public function page(): LengthAwarePaginator
    {
        Tenancy::idOrFail();

        return OutreachMessage::query()
            ->with('customer')
            ->orderByDesc('id')
            ->paginate($this->defaults->int('messaging.log.per_page'));
    }

    /**
     * What we sent one contact, newest first.
     *
     * ⚠️ **THIS METHOD EXISTS SO THAT `CustomerTimeline` DOES NOT QUERY THIS
     * TABLE.** The lint above holds `outreach_messages` to two files, and the
     * tenant CRM's contact timeline (`34` §1.2) needs the same rows keyed by
     * customer. Decision 624's rule decided it: two chokepoint lints refused a
     * feature there and neither was widened, because an allowlist entry is a
     * security change that does not look like one on its own diff. The reader
     * that already owns this table grows a method instead.
     *
     * ⚠️ Ordered by `id` for `page()`'s reason, which bites harder here: a
     * contact's timeline is read as a sequence of events, and a queued message
     * sorted above a delivered one by a null `sent_at` would tell an owner we
     * sent something after we sent something we have not sent yet.
     *
     * @return Collection<int, OutreachMessage>
     */
    public function forCustomer(Customer $customer): Collection
    {
        Tenancy::idOrFail();

        return OutreachMessage::query()
            ->where('customer_id', $customer->getKey())
            ->orderByDesc('id')
            ->get();
    }

    /**
     * The touch classes this contact has already received on this channel since
     * a moment — `S1`'s question, asked of the store that already answers it.
     *
     * ⚠️ **A METHOD HERE RATHER THAN AN ALLOWLIST ENTRY, ON DECISION 624'S
     * RULE**: ask whether the caller belongs behind the service before widening
     * the chokepoint, and this one does. The drift the lint exists to catch is a
     * second *list* query whose tenant scope, ordering and status vocabulary
     * diverge from this file's; the arbiter has none of the three — it reads no
     * bodies, sorts nothing, and returns a set of touch classes rather than
     * rows.
     *
     * ⚠️ **`created_at`, NOT `sent_at`, AND THE CHOICE IS THE CONSERVATIVE
     * ONE.** `sent_at` is null until a send succeeds, so a window keyed on it
     * would treat a message queued four minutes ago as though it had never
     * happened — and the arbiter would then authorise a second one beside it.
     * A row exists because we decided to message this person; that is the touch,
     * whatever the carrier did next.
     *
     * ⚠️ **THE ROWS THIS FINDS ARE REAL AND FEW.** `ReviewInviteSender` is the
     * only writer of this table today, so what the arbiter sees is review
     * invites — which is exactly the collision `S1` names first, and is why this
     * is not a gate that matches nothing (decision 256).
     *
     * @return list<MarketingTouch>
     */
    public function touchesSince(Customer $customer, OutreachChannel $channel, CarbonInterface $since): array
    {
        Tenancy::idOrFail();

        $purposes = OutreachMessage::query()
            ->where('customer_id', $customer->getKey())
            ->where('channel', $channel)
            ->where('created_at', '>=', $since)
            ->pluck('purpose');

        $touches = [];

        foreach ($purposes as $purpose) {
            $touch = MarketingTouch::forOutreachPurpose((string) $purpose);

            if ($touch instanceof MarketingTouch) {
                $touches[] = $touch;
            }
        }

        return array_values(array_unique($touches, SORT_REGULAR));
    }

    /**
     * The review invite this contact was sent, if one ever was — T176 P14.
     *
     * ⚠️ **A METHOD HERE RATHER THAN AN ALLOWLIST ENTRY, ON DECISION 624'S
     * RULE**, and this is the second caller to take that route rather than the
     * first (`touchesSince()` above is the precedent). The follow-up sweep needs
     * two facts about the same table — *was this contact invited, and when* —
     * and a sweep growing its own query is precisely the drift the chokepoint
     * lint exists to catch.
     *
     * ⚠️ **THE ROW IS THE CLOCK, NOT `reviews.routed_at`.** The reminder is due
     * a fixed delay after **the invite left**, and those two moments are not the
     * same one: `ReinviteDeferredReviews` re-offers invites a tenant pause
     * deferred, so a routed-at clock would make a resumed tenant's reminders due
     * the instant their invite went out.
     *
     * ⚠️ **`created_at`, NOT `sent_at`, FOR `touchesSince()`'s REASON.** The row
     * is written inside the send transaction, so `created_at` is when we
     * committed to messaging this person; `sent_at` is null on the SMS path
     * until the carrier answers, and a clock keyed on it would treat a message
     * that went out three days ago as though it never had.
     *
     * ⚠️ **`orderBy('id')` ASCENDING, WHICH IS THE ONE ORDERING THIS CODEBASE'S
     * NULLS-LAST LINT NEVER ARGUES WITH.** There is at most one row to find —
     * `ReviewInviteSender::alreadyInvitedOnAnyChannel()` gives a contact one
     * invite ever, on one channel — so the ordering only decides which of an
     * anomalous pair wins, and the first is the honest clock.
     */
    public function reviewInviteFor(Customer $customer): ?OutreachMessage
    {
        Tenancy::idOrFail();

        return OutreachMessage::query()
            ->where('customer_id', $customer->getKey())
            ->where('purpose', ReviewInviteKind::Invite->outreachPurpose())
            ->orderBy('id')
            ->first();
    }

    /**
     * Whether this contact has already had their one follow-up — T176 P14.
     *
     * ⛔ **THIS IS WHAT MAKES "EXACTLY ONE REMINDER, THEN STOP" TRUE, AND IT IS
     * KEYED ON THE CONTACT RATHER THAN ON THE REVIEW.** The asymmetry is
     * deliberate and copies `alreadyInvitedOnAnyChannel()`'s: a customer who
     * leaves feedback twice is one person, and two nudges about two reviews is
     * the double-contact this product exists not to commit, however well
     * consented each was.
     *
     * ⚠️ **BEST-EFFORT UNDER CONCURRENCY AND SAID OUT LOUD** — decision 350's
     * shape, the same statement `alreadySent()` carries. This is a SELECT with
     * no unique index behind it; `SendInviteReminderJob`'s idempotency key is
     * the layer that holds under a redelivery, and this is what catches the
     * ordinary case of two sweeps overlapping.
     */
    public function reviewInviteReminderSent(Customer $customer): bool
    {
        Tenancy::idOrFail();

        return OutreachMessage::query()
            ->where('customer_id', $customer->getKey())
            ->where('purpose', ReviewInviteKind::Reminder->outreachPurpose())
            ->exists();
    }

    /**
     * Every review invite and reminder this tenant sent recently that a text
     * message could still be answering — T176 R20, patch P20.
     *
     * ⚠️ **A METHOD HERE RATHER THAN AN ALLOWLIST ENTRY, ON DECISION 624'S
     * RULE**, and this is the third caller to take that route.
     * `CampaignReplyResolver` needs this tenant's recent invites to decide which
     * send an inbound reply is answering, and it is a *list* query with an
     * ordering — the exact drift shape the chokepoint lint exists to catch, and
     * the one thing neither `DeliveryReceipts` nor `NumberHealthService` was (a
     * write and an aggregate respectively). So 624's stated remedy applies here
     * where it did not there: the reader that owns the table grows a method.
     *
     * ⛔ **`created_at`, NOT `sent_at`, AND THE FIRST DRAFT OF P20 GOT THIS
     * WRONG.** `ReviewInviteSender` writes the SMS row with `created_at` and
     * **no `sent_at` at all** — the carrier receipt fills that in later — so a
     * window keyed on `sent_at` silently excludes **every SMS review invite**,
     * which is the send this product makes most. The tell was a green test,
     * because a fixture that sets `sent_at` by hand looks exactly like a real
     * row. `reviewInviteFor()` above argues the identical point for the reminder
     * clock; this is the second place it bites.
     *
     * ⚠️ **SMS ONLY.** An inbound text can only be answering a text. An email
     * invite in this list would let a reply to something else entirely be
     * attributed to a message the person read in a mail client.
     *
     * ⚠️ **A FAILED OR OPTED-OUT SEND IS EXCLUDED AND `Queued` IS NOT.** The row
     * is written `Queued` inside the send transaction and the receipt moves it
     * on, so refusing `Queued` would refuse every invite in the first minutes
     * after it left — which is when a reply is likeliest.
     *
     * ⚠️ **`orderBy('id')` ASCENDING**, this class's settled ordering: the
     * caller wants a set rather than a ranking, and `created_at` is the clock
     * the window already filtered on.
     *
     * @return Collection<int, OutreachMessage>
     */
    public function openReviewInvitesSince(CarbonInterface $since): Collection
    {
        Tenancy::idOrFail();

        return OutreachMessage::query()
            ->where('channel', OutreachChannel::Sms)
            ->whereIn('purpose', array_map(
                static fn (ReviewInviteKind $kind): string => $kind->outreachPurpose(),
                ReviewInviteKind::cases(),
            ))
            ->whereNotNull('customer_id')
            ->whereNotIn('status', [OutreachStatus::Failed, OutreachStatus::OptedOut])
            ->where('created_at', '>=', $since)
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether the tenant has ever been sent anything.
     *
     * Distinguishes "nothing has happened yet" from "your filter matched
     * nothing", which are the two states an empty list otherwise collapses into
     * — and the first is the one every tenant is in on day one.
     */
    public function isEmpty(): bool
    {
        Tenancy::idOrFail();

        return ! OutreachMessage::query()->exists();
    }

    /**
     * The delivery state in the owner's words (§3.4).
     *
     * ⚠️ **`Replied` IS THE HONEST-LABEL PROBLEM, NOT A COSMETIC ONE.** The
     * status exists and the reply itself has nowhere to live: `outreach_messages`
     * is outbound-only and no inbound store exists. Saying "They replied" while
     * showing nothing invites the owner to hunt for a message that was never
     * recorded, so the wording says both halves. When row 4's webhooks land and
     * inbound messages have a home, this string is the thing to change.
     *
     * `Queued` is *"waiting to send"* rather than "pending": §3.4 asks for plain
     * words, and every string here names what happened to the customer rather
     * than what state a row is in.
     */
    public static function statusLabel(OutreachStatus $status): string
    {
        return match ($status) {
            OutreachStatus::Queued => 'Waiting to send',
            OutreachStatus::Sent => 'Sent',
            OutreachStatus::Delivered => 'Delivered',
            OutreachStatus::Failed => 'Didn’t go through',
            OutreachStatus::OptedOut => 'Not sent — they asked us to stop',
            OutreachStatus::Replied => 'They replied — we cannot show replies yet',
        };
    }

    /**
     * Why a message did not arrive, in plain words, or null when it did.
     *
     * ⚠️ **THE STORED `error_message` IS NEVER SHOWN.** It is a vendor string —
     * an Infobip code or an SMTP rejection — written for us, and putting it in
     * front of an owner turns a delivery problem into a support ticket about a
     * message they cannot read. §3.4 asks for an auto-explain, and this is it;
     * the raw text stays in the row for whoever debugs it.
     */
    public static function explain(OutreachMessage $message): ?string
    {
        return match ($message->status) {
            OutreachStatus::Failed => 'This address or number couldn’t receive it.',
            OutreachStatus::OptedOut => 'They asked us to stop messaging them, so we didn’t send it.',
            OutreachStatus::Queued,
            OutreachStatus::Sent,
            OutreachStatus::Delivered,
            OutreachStatus::Replied => null,
        };
    }
}

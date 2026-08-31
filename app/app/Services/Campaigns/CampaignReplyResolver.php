<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Contracts\Campaigns\CampaignContextResolver;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CampaignReplyOrigin;
use App\Enums\OutreachChannel;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CampaignReply;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\InboundMessage;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\MessageLog;
use App\Services\Sms\TenantNumbers;
use App\Support\Identifier;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;

/**
 * Which send an inbound message is answering — T176 R20, patch P20.
 *
 * The implementation of {@see CampaignContextResolver}; that interface's
 * docblock is the specification and is not repeated here. What follows is how
 * the three steps it describes are actually done, and what each one refuses.
 *
 * ## 1. Our own number establishes the tenant, and nothing else may
 *
 * `to_number` → {@see TenantNumbers::tenantFor()} → `business_id`. The sender is
 * never touched until a tenant is in hand, so there is no point at which this
 * class holds a member of the public's number outside a tenant boundary.
 *
 * ⛔ **A SHARED POOL NUMBER RESOLVES TO `null` AND MUST NEVER GUESS.** R8 makes
 * the map one-to-one for a tenant that holds its own number; a number nobody
 * owns answers `null`, and picking the tenant who most recently messaged this
 * person would hand one business's customer conversation to another. That is
 * 2125's precedent — a tenant inferred from *"who last messaged this person"* is
 * wrong the first time somebody is a customer of two businesses — and here being
 * wrong is a cross-tenant breach rather than a mis-counted complaint.
 *
 * ## 2. The contact is found by hashing, because the hash cannot be reversed
 *
 * `inbound_messages.value_hash` is `hash_hmac('sha256', …, app.key)` of the
 * sender ({@see Identifier::hash()}), and `customers.phone` is stored in clear.
 * **No SQL join can bridge those**, because the key is in the application and
 * not in the database — so the match is made in PHP, over a candidate set.
 *
 * ⚠️ **THE CANDIDATE SET IS THE SENDS, NOT THE CONTACT BOOK, AND THAT BOUND IS
 * THE WHOLE PERFORMANCE ARGUMENT.** Hashing every contact a tenant has would put
 * an unbounded scan on a carrier webhook. The only contacts that can possibly
 * own this reply are the ones we sent to inside `campaigns.reply_window_hours`,
 * so that set is gathered first and the hashing is bounded by a campaign's size
 * rather than by a tenant's history.
 *
 * ## 3. Exactly one open send, or `null`
 *
 * Two sends to the same contact in the window resolve to `null`, which the
 * contract requires: *"guessing the more recent one would be right most of the
 * time, and the times it is wrong are a customer's words filed against someone
 * else's campaign."*
 *
 * ⚠️ **A STORED LINKAGE WINS OVER A FRESH RESOLUTION.** {@see CampaignReply} is
 * written on the webhook; every later read returns it verbatim. Without that,
 * the answer would change under a reader the moment the send aged past the
 * window — a thread that opened with context would silently lose it, which is
 * worse than never having had it.
 *
 * ⛔ **NOTHING HERE WIDENS THE TENANT BOUNDARY.** No `withoutGlobalScope`, no
 * `DB::table`, no join outside the tenant `to_number` resolved. Every query
 * below runs inside {@see Tenancy::actingAs()} against a model with
 * `BelongsToTenant`, with RLS forced beneath it.
 */
final class CampaignReplyResolver implements CampaignContextResolver
{
    public function __construct(
        private readonly TenantNumbers $numbers,
        private readonly DefaultsRegistry $registry,
        private readonly MessageLog $messages,
    ) {}

    public function forInbound(InboundMessage $message): ?CampaignContext
    {
        // ⚠️ **SMS ONLY, STATED RATHER THAN ASSUMED.** Every candidate send
        // below is an SMS send, and an inbound row on another channel would
        // match a text message the person never received. `inbound_messages`
        // has only ever held SMS, so this is a guard for the day it does not.
        if ($message->identifier_type !== OutreachChannel::Sms) {
            return null;
        }

        $businessId = $this->tenantFor($message);

        if ($businessId === null) {
            return null;
        }

        return Tenancy::actingAs(
            $businessId,
            fn (): ?CampaignContext => $this->withinTenant($message),
        );
    }

    /**
     * The linkage recorded against this thread, read back verbatim (4236).
     *
     * ⛔ **ONE INDEXED LOOKUP AND NOTHING ELSE — NO FALLBACK, NO RESOLUTION.**
     * The interface's own docblock is the specification and is not repeated
     * here; what it means in code is that this method must never reach the
     * candidate-gathering machinery above. Everything below `forInbound()`
     * matches a *message* against open sends, and a thread is not a message: it
     * has no `value_hash`, so the only key it could offer is its contact, and
     * one contact holds many threads and many linkages.
     *
     * ⚠️ **THE EARLIEST LINKAGE, WHICH IS WHAT `ThreadState::$campaign`
     * PROMISES**: *"which send this thread is answering, **when it began** as a
     * campaign reply."* A thread stays open indefinitely — nothing in `app/`
     * writes `conversations.resolved_at` — so a long-lived thread can accrue a
     * second linkage when the same contact answers a later send. `orderBy('id')`
     * makes the answer the one the thread opened with, which is stable and
     * provable, rather than one that changes under a reader.
     * ⚠️ **THAT IS A DELIBERATE LIMITATION AND IS RECORDED AS OWED (4238)**: the
     * *latest* send would serve the agent better, and choosing it here would
     * silently redefine a DTO another lane owns. The screen dates the context it
     * shows, so a stale one reads as old rather than as wrong.
     *
     * ## The tenant filters here are defence in depth, and three mutations prove
     * it rather than three sentences claiming otherwise
     *
     * ⛔ **AN EARLIER DRAFT OF THIS DOCBLOCK CLAIMED THE FILTER WAS "PINNED HERE
     * AND NOT INHERITED", AND THAT WAS 314–316's FAILURE INSIDE THE SLICE THAT
     * QUOTED IT** — a protection layer asserted before it was true. What was
     * actually measured, against the cross-tenant test below:
     *
     * 1. Deleting the `business_id` comparison from the guard → **GREEN**.
     * 2. Deleting it *and* the `where('business_id')` on the query → **GREEN**;
     *    `BelongsToTenant`'s global scope refuses the row on its own.
     * 3. Deleting those *and* the global scope (`withoutGlobalScopes()`) →
     *    **still GREEN**. Only **RLS** refuses at that point.
     *
     * ⚠️ **SO A BEHAVIOURAL CROSS-TENANT ASSERTION PROVES NOTHING ABOUT THIS
     * CLASS**, which is the rehearsal's L3 finding and CLAUDE.md's 398 exactly.
     * What pins the application layer is the *structural* test beside it —
     * *"the thread lookup reaches the database with a tenant predicate on it"* —
     * which reads the emitted SQL and reddens on mutation 3, the only one of the
     * three that leaves a row genuinely unprotected above the database.
     *
     * ⚠️ **BOTH FILTERS STAY**, unfalsifiable or not: RLS is the layer that must
     * never be the only one, and the guard is the shape neither RLS nor the
     * global scope can see — a *hydrated* foreign model.
     * `AgentThreadStates::refuseForeignThread()` and
     * `ConversationThreads::refuseForeignThread()` are the precedent, and this
     * file's own `$sentAt === null` arm is the precedent for keeping a guard that
     * cannot currently be driven red and **saying so** instead of implying it is
     * load-bearing.
     */
    public function forThread(Conversation $conversation): ?CampaignContext
    {
        $businessId = Tenancy::idOrFail();

        if (! $conversation->exists || (int) $conversation->business_id !== $businessId) {
            // ⛔ **NULL RATHER THAN A THROW, UNLIKE THE WRITER'S EQUIVALENT
            // GUARD.** This is a read on a rendering path: a screen holding a
            // stale or foreign thread must answer *"no context"* rather than
            // produce a 500 on the Inbox. The writer throws because a bad thread
            // there would be **written down**; nothing is written here.
            return null;
        }

        $stored = CampaignReply::query()
            ->where('business_id', $businessId)
            ->where('conversation_id', $conversation->getKey())
            ->orderBy('id')
            ->first();

        return $stored?->toContext();
    }

    /**
     * Whose number this arrived on — step 1, and the only step that runs outside
     * a tenant.
     *
     * ⚠️ **PUBLIC BECAUSE {@see CampaignReplies} NEEDS THE ID AND MUST NOT ASK
     * TWICE.** The writer has to open `Tenancy::actingAs()` to record the
     * linkage; re-deriving the tenant there would be a second copy of the one
     * rule that decides whose conversation this is, and this codebase's most
     * repeated lesson is that the copy drifts.
     */
    public function tenantFor(InboundMessage $message): ?int
    {
        $toNumber = $message->to_number;

        if ($toNumber === null) {
            // Some inbound payloads omit the receiving number. There is nothing
            // to look a tenant up by, and the alternatives — the sender's
            // history, the platform's own aggregate — are the inference
            // `TenantNumbers` refuses.
            return null;
        }

        return $this->numbers->tenantFor($toNumber);
    }

    /**
     * Steps 2 and 3, with the tenant established and RLS in force.
     */
    private function withinTenant(InboundMessage $message): ?CampaignContext
    {
        $stored = CampaignReply::query()
            ->where('inbound_message_id', $message->getKey())
            ->first();

        if ($stored !== null) {
            return $stored->toContext();
        }

        $since = now()->subHours($this->registry->int('campaigns.reply_window_hours'));

        $candidates = [...$this->campaignSends($since), ...$this->inviteSends($since)];

        if ($candidates === []) {
            return null;
        }

        $customerId = $this->contactBehind(
            $message,
            array_values(array_unique(array_column($candidates, 'customerId'))),
        );

        if ($customerId === null) {
            return null;
        }

        $theirs = array_values(array_filter(
            $candidates,
            static fn (array $candidate): bool => $candidate['customerId'] === $customerId,
        ));

        // ⛔ **AMBIGUITY IS `null`, AND `count() === 1` IS HOW THAT IS SAID.**
        // Two open sends to one contact — a reactivation and the review invite
        // it produced, say — cannot be told apart from the reply alone. The
        // contract is explicit that the recent one must not be guessed.
        if (count($theirs) !== 1) {
            return null;
        }

        return $this->contextOf($theirs[0]);
    }

    /**
     * Reactivation and broadcast sends still open for a reply.
     *
     * @return list<array{origin: CampaignReplyOrigin, campaignId: ?int, recipientId: int, customerId: int, sentAt: Carbon, occasion: string}>
     */
    private function campaignSends(Carbon $since): array
    {
        $recipients = CampaignRecipient::query()
            // ⚠️ **`Sent` ONLY.** A refused, skipped or failed recipient never
            // received anything, so a reply cannot be answering it — and
            // `campaign_recipients_refusal_sent_nothing` makes that a fact about
            // the row rather than a hope about the runner.
            ->where('status', CampaignRecipientStatus::Sent->value)
            ->where('sent_at', '>=', $since)
            ->with('campaign:id,business_id,kind')
            ->orderBy('id')
            ->get();

        $sends = [];

        foreach ($recipients as $recipient) {
            $campaign = $recipient->campaign;

            if (! $campaign instanceof Campaign) {
                // The campaign was hard-deleted out from under its recipients.
                // Nothing to attribute the reply to, and inventing an origin
                // would file a customer's words against a run that no longer
                // exists.
                continue;
            }

            $sentAt = $recipient->sent_at;

            if ($sentAt === null) {
                // ⚠️ **A TYPE GUARD, NOT A SAFETY GUARD, AND THE DIFFERENCE IS
                // WORTH WRITING DOWN.** `campaign_recipients_sent_rows_are_
                // complete` makes a `sent` row with no `sent_at` impossible at
                // the database, and the status predicate above already asked for
                // `sent` — so this cannot be reached and a mutation deleting it
                // leaves the suite green. It stays because `sent_at` is
                // `?Carbon` and Larastan is right about that; it is documented
                // as unreachable so that nobody later reads it as the thing
                // keeping unsent recipients out.
                continue;
            }

            $sends[] = [
                'origin' => $campaign->kind->replyOrigin(),
                'campaignId' => (int) $campaign->getKey(),
                'recipientId' => (int) $recipient->getKey(),
                'customerId' => (int) $recipient->customer_id,
                'sentAt' => $sentAt,
                // ⚠️ **THE RUNNER'S OWN OCCASION, THROUGH THE RUNNER'S OWN
                // METHOD.** Rebuilding the string here would be a second
                // spelling of the value `SendKey` hashes, and the two would
                // disagree the first time either moved.
                'occasion' => $campaign->sendOccasion(),
            ];
        }

        return $sends;
    }

    /**
     * Review invites and their one reminder, still open for a reply.
     *
     * ⚠️ **`outreach_messages`, NOT `review_request_campaigns`.** That table has
     * no writer anywhere in `app/` — see {@see CampaignContext} for why the
     * campaign id is nullable because of it.
     *
     * @return list<array{origin: CampaignReplyOrigin, campaignId: ?int, recipientId: int, customerId: int, sentAt: Carbon, occasion: string}>
     */
    private function inviteSends(Carbon $since): array
    {
        // ⚠️ **THROUGH {@see MessageLog} RATHER THAN A QUERY HERE, ON DECISION
        // 624's RULE.** `Architecture\ConsentTest` holds `outreach_messages` to
        // a named list of files and refused this one on its first run, which is
        // the lint working: this is a *list* query with an ordering, the exact
        // drift shape it guards. That method's docblock carries the clock
        // argument — `created_at`, never `sent_at` — and the SMS invite defect
        // that argument caught.
        $messages = $this->messages->openReviewInvitesSince($since);

        $sends = [];

        foreach ($messages as $message) {
            // ⛔ **`created_at` IS THE SEND CLOCK ON THIS PATH AND `sent_at` IS
            // NOT.** The SMS invite row is written with no `sent_at` at all
            // until the carrier receipt lands, so reading it here would make
            // `CampaignContext::$sentAt` null for the commonest send in the
            // product — and the recovery flow reads that interval.
            $sentAt = $message->created_at;
            $customerId = $message->customer_id;

            if ($sentAt === null || $customerId === null) {
                continue;
            }

            $sends[] = [
                'origin' => CampaignReplyOrigin::ReviewInvite,
                // Null in practice and read rather than assumed: the column
                // exists, nothing writes it, and the day something does this
                // carries it without an edit.
                'campaignId' => $message->campaign_id === null ? null : (int) $message->campaign_id,
                'recipientId' => (int) $message->getKey(),
                'customerId' => (int) $customerId,
                'sentAt' => $sentAt,
                // ⚠️ **SYNTHESISED, BECAUSE THIS PATH HAS NO `SendKey` AT ALL**
                // (2975, still owed). Built from the row's own purpose and id so
                // that a reminder is told from the invite it followed, which is
                // the one thing the DTO needs an occasion for.
                'occasion' => ((string) $message->purpose).':'.$message->getKey(),
            ];
        }

        return $sends;
    }

    /**
     * Which of these contacts sent this message — the hash match of step 2.
     *
     * ⚠️ **`hash_equals`, NOT `===`.** The comparison is over a keyed digest and
     * a timing-safe comparison is the habit worth keeping where one is involved,
     * even though the attacker here would need to control the contact book.
     *
     * ⚠️ **AND IT STOPS AT THE FIRST MATCH BECAUSE THE SCHEMA SAYS IT CAN**:
     * `customers` is unique on `(business_id, phone)`, so at most one contact in
     * this tenant can hash to one value.
     *
     * @param  list<int>  $customerIds
     */
    private function contactBehind(InboundMessage $message, array $customerIds): ?int
    {
        foreach (array_chunk($customerIds, 500) as $chunk) {
            $contacts = Customer::query()
                ->whereIn('id', $chunk)
                ->whereNotNull('phone')
                ->get(['id', 'phone']);

            foreach ($contacts as $contact) {
                $hash = Identifier::hash($contact->phone, OutreachChannel::Sms);

                if ($hash !== null && hash_equals($message->value_hash, $hash)) {
                    return (int) $contact->getKey();
                }
            }
        }

        return null;
    }

    /**
     * @param  array{origin: CampaignReplyOrigin, campaignId: ?int, recipientId: int, customerId: int, sentAt: Carbon, occasion: string}  $send
     */
    private function contextOf(array $send): CampaignContext
    {
        return new CampaignContext(
            origin: $send['origin'],
            campaignId: $send['campaignId'],
            recipientId: $send['recipientId'],
            customerId: $send['customerId'],
            sentAt: $send['sentAt'],
            occasion: $send['occasion'],
        );
    }
}

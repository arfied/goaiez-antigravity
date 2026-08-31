<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Enums\OutreachStatus;
use App\Models\MailTrackingCode;
use App\Models\OutreachMessage;
use App\Services\Messaging\SendingGuard;
use App\Services\Messaging\SendingHealth;
use App\Services\Messaging\SendingRates;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;

/**
 * What SES said about one email, charged to the tenant that sent it (6360).
 *
 * ⛔ **THE EMAIL COMPLAINT TRIP COULD NEVER FIRE, AND IT IS 2496's DEFECT ON
 * THE CHANNEL R16 MADE PRIMARY.** {@see SendingGuard} asks
 * {@see SendingHealth::rates()} for a channel, `hasEnoughVolume()` answers false
 * against a window that has no rows, and the guard returns *"may send"* forever
 * — with a green suite over it, because every test of that table seeds the
 * counters by hand. 2101/2102/2113 make an automatic complaint trip a
 * **precondition of sending at all**, so a threshold set on `Email` was set on
 * nothing. `SendingHealth`'s own docblock said so in terms: *"email writes
 * nothing at all … a per-channel threshold set on it today is set on nothing."*
 *
 * ## The denominator is the whole slice, and the numerator alone would rebuild
 * the bug
 *
 * ⛔ **`complaintRateBp()` DIVIDES BY `delivered`, NOT BY `sent`**
 * ({@see SendingRates}), and `hasEnoughVolume()` reads
 * `delivered` too. So wiring `Complaint` and not `Delivery` produces a rate that
 * is **permanently zero with a complaint sitting in the numerator** — a
 * containment that looks wired, reads clean and cannot fire. That is 2496
 * rebuilt, one channel over. **Both halves arrive together or neither is worth
 * building**, which is the same conclusion `SendSettlement` reached about `sent`
 * and is 3032's membership rule stated from the other end.
 *
 * ## Membership: the set that can be counted is the set that can be found
 *
 * 3032: **the denominator must have the same membership as the numerator.**
 * Here that falls out of one fact — every counter this class writes is written
 * for a row it found by `provider_msg_id`, and the only rows that have one are
 * the rows {@see MailSettlement} settled when the transport named them. No
 * larger and no smaller.
 *
 * ⚠️ **AND THE POPULATION IS BOUNDED UPSTREAM BY THE TRANSPORT GATE, WHICH IS
 * WHY NO CHECK OF IT LIVES HERE** (398). `PlatformMailer::sendToCustomer()`
 * refuses customer mail outright unless `MailDrivers::feedbackSignal()` answers
 * `Typed` **and** an SNS topic is allowlisted — so an `outreach_messages` email
 * row can only exist on a deployment where these events can arrive. Repeating
 * that test here would make the real gate unfalsifiable while proving nothing.
 *
 * ## Establishing the tenant, which is the thing SES cannot tell us
 *
 * ⚠️ **THIS IS `DeliveryReceipts`' PROBLEM WITH A DIFFERENT KEY AND NO ECHO
 * FIELD.** Infobip carries the business id out as `callbackData` and hands it
 * back, so the SMS receipt handler can `Tenancy::actingAs()` before it queries.
 * SES echoes nothing of ours: an event carries a recipient, an SES message id,
 * and no tenant. So the id is written at send time onto `mail_tracking_codes` —
 * the un-tenanted row that already exists to answer *whose message was this* —
 * and this class resolves that first and establishes the tenant from it.
 *
 * ⚠️ **THE RESOLUTION DECIDES WHICH ROWS ARE VISIBLE AND IS NEVER PROOF OF
 * ANYTHING**, which is `MailReplyRouter`'s rule verbatim. Everything after the
 * `actingAs()` runs under RLS with that tenant set, and the outreach row is
 * fetched **inside** it — so a mapping that named the wrong business would find
 * no row and write nothing, rather than counting one tenant's complaint against
 * another's window. **That ordering is the cross-tenant guard**, and it is why
 * the tracking row is stamped only after the outreach row has been found.
 *
 * ## Counted once per message per outcome, because SNS delivers at least once
 *
 * ⚠️ **A REDELIVERED `Delivery` PUSHES THE COMPLAINT RATE DOWN AND SUPPRESSES
 * THE TRIP.** AWS is explicit that it makes no ordering or batching guarantee
 * for these notifications (*Amazon SNS notification contents for Amazon SES*,
 * docs.aws.amazon.com, read 2026-08-20), and the endpoint answers 200 to
 * everything, so a repeat is ordinary rather than exceptional. `DeliveryReceipts`
 * gets its idempotency free from the one-way status machine; this path cannot,
 * because `OutreachStatus::Delivered` is terminal and setting it would drop
 * every later reply ({@see MailReplyRouter}). So delivery and complaint are
 * marked with their own timestamps, written `whereNull`, and the counter moves
 * only when the update actually changed a row.
 *
 * ⚠️ **A BOUNCE USES THE STATUS MACHINE, BECAUSE FOR A BOUNCE THE STATUS IS
 * TRUE.** `Failed` is what the message is, `canTransitionTo()` refuses the
 * repeat, and the row then reads the way `MessageLog` already renders a failed
 * send — which is `DeliveryReceipts`' `UNDELIVERABLE` arm exactly.
 *
 * ## What is counted as a failure, and what is not counted as a suppression
 *
 * ⚠️ **EVERY PUBLISHED BOUNCE COUNTS AS `failed`, INCLUDING A TRANSIENT ONE,
 * AND THAT IS NOT A DISAGREEMENT WITH {@see MailFeedback}.** That class refuses
 * to *suppress* on anything but `Permanent`, because a suppression here never
 * lifts and a full mailbox is not a dead address. A health counter is asking a
 * different question — did this message arrive — and AWS only publishes the
 * transient bounces it has **stopped retrying**: *"`Transient` bounces are sent
 * to you when a message has soft bounced several times, and Amazon SES has
 * stopped trying to re-deliver it"* (docs.aws.amazon.com, *Contents of event
 * data that Amazon SES publishes to Amazon SNS*, read 2026-08-20). A message
 * SES has given up on did not arrive.
 *
 * ⚠️ **`failed` FEEDS NO RATE TODAY** — `SendingRates` derives delivery,
 * opt-out and complaint rates and never reads it — so this is a number on a
 * screen rather than an input to the trip. Said out loud so that nobody later
 * reads the bounce arm as a containment.
 */
final class MailSendingHealth
{
    public function __construct(
        private readonly MailTrackingCodes $codes,
        private readonly SendingHealth $health,
    ) {}

    /**
     * The recipient's mail server accepted it — **the denominator** (3032).
     *
     * ⛔ **THIS IS THE METHOD THE WHOLE SLICE TURNS ON.** Without it
     * `complaintRateBp()` divides by zero volume for ever and
     * `hasEnoughVolume()` never clears the floor, so the numerator below can
     * fire as often as it likes and nothing ever trips.
     *
     * ⛔ **AND IT DELIBERATELY DOES NOT SET `OutreachStatus::Delivered`.** That
     * status is terminal, and `MailReplyRouter` would then drop every reply to
     * a delivered message — its own docblock predicted this slice by name.
     *
     * @return bool whether a counter moved
     */
    public function delivered(string $providerMessageId): bool
    {
        return $this->against($providerMessageId, function (OutreachMessage $row): bool {
            if (! $this->stamp($row, 'delivered_at')) {
                return false;
            }

            $this->health->recordDelivered($row->channel);

            return true;
        });
    }

    /**
     * A mailbox provider's feedback loop fired — **the numerator** (2102).
     *
     * @return bool whether a counter moved
     */
    public function complained(string $providerMessageId): bool
    {
        return $this->against($providerMessageId, function (OutreachMessage $row): bool {
            if (! $this->stamp($row, 'complained_at')) {
                return false;
            }

            $this->health->recordComplaint($row->channel);

            return true;
        });
    }

    /**
     * It did not arrive and SES has stopped trying.
     *
     * @param  ?string  $reason  the bounce type and subtype, or null
     * @return bool whether a counter moved
     */
    public function bounced(string $providerMessageId, ?string $reason = null): bool
    {
        return $this->against($providerMessageId, function (OutreachMessage $row) use ($reason): bool {
            if (! $row->status->canTransitionTo(OutreachStatus::Failed)) {
                // A repeat, or a row already terminal. `canTransitionTo()` is
                // the same one-way rule that makes a redelivered SMS receipt a
                // no-op, and it is doing the same job here.
                return false;
            }

            $row->status = OutreachStatus::Failed;

            if ($reason !== null) {
                // ⛔ **SES'S OWN `bounceType`/`bounceSubType` AND NEVER
                // `diagnosticCode`** — `DeliveryReceipts`' rule, which stores
                // the vendor's status *name* and refuses its description. The
                // subtypes are a fixed enumeration (`General`, `NoEmail`,
                // `MailboxFull` …); a diagnostic code is free text from a
                // stranger's mail server that routinely quotes the recipient's
                // address back at us, and this column is tenant-readable.
                $row->error_message = $reason;
            }

            $row->save();

            $this->health->recordFailed($row->channel);

            return true;
        });
    }

    /**
     * Find the tenant, then the message, then do the work as that tenant.
     *
     * ⚠️ **THE ORDER IS THE CROSS-TENANT GUARD.** The tracking row is
     * un-tenanted by necessity — it is what establishes a tenant for a request
     * that has none — so nothing about finding it proves anything. What proves
     * something is the `OutreachMessage` lookup **inside** `actingAs()`: it is
     * filtered by the global scope and again by the FORCE row-level security
     * policy, so a mapping naming the wrong business finds no row at all and
     * every counter below is skipped.
     *
     * ⚠️ **AN UNKNOWN MESSAGE ID IS A NO-OP AND NEVER AN EXCEPTION.** Ordinary
     * causes, all of which will happen: platform mail to an account holder is
     * never tracked at all, a message sent before this column existed has no
     * mapping, and SES publishes an event for anything the account sends
     * including a console test. `SesFeedbackController` answers 200 to all of
     * them, because none is improved by SNS delivering it again — and a throw
     * would be worse than useless, since **SNS retries a 500 indefinitely** and
     * one unmatched id would become a permanent redelivery loop against the
     * endpoint the whole bounce feed depends on.
     *
     * @param  callable(OutreachMessage): bool  $work
     */
    private function against(string $providerMessageId, callable $work): bool
    {
        $providerMessageId = trim($providerMessageId);

        if ($providerMessageId === '') {
            return false;
        }

        $tracking = $this->codes->resolveByTransportMessageId($providerMessageId);

        if (! $tracking instanceof MailTrackingCode) {
            // ⚠️ **THE ID IS NOT LOGGED AND NEITHER IS ANYTHING ELSE FROM THE
            // EVENT.** An SES message id is a handle for a message sent to a
            // named person; a log line pairing it with "we could not place
            // this" is a breadcrumb with no reader and a retention question.
            // The count is what an operator needs, and the platform-health
            // counters are where a count belongs.
            return false;
        }

        $outreachMessageId = $tracking->outreach_message_id;

        if ($outreachMessageId === null) {
            // A tracked customer email that is not an outreach message — the
            // nullable column exists for exactly that, and such a message is
            // outside the measured population at both ends.
            return false;
        }

        return (bool) Tenancy::actingAs(
            $tracking->business_id,
            function () use ($outreachMessageId, $providerMessageId, $work): bool {
                $row = OutreachMessage::query()
                    // ⛔ **THE SECOND PREDICATE IS NOT DRIVEN BY ANY TEST AND I
                    // CHECKED RATHER THAN ASSUMED — 398'S SHAPE, SAID OUT LOUD
                    // (6368).** Deleting `->where('provider_msg_id', …)` leaves
                    // this whole file green, because the two columns are
                    // written **in one transaction** by `MailSettlement` and
                    // there is no code path that can make them disagree — a
                    // re-send overwrites both from the same variable, and a
                    // stale event for a superseded attempt fails to resolve a
                    // mapping at all and never reaches this line.
                    //
                    // It stays because it is the consistency check on a pair
                    // this counter's correctness depends on: `sent` counts the
                    // rows that acquire a handle and `delivered` must count
                    // only rows that still carry the handle the event names
                    // (3032). **What must not happen is somebody adding a test
                    // that claims to cover it**, or a second writer of either
                    // column arriving on the strength of a green suite.
                    ->whereKey($outreachMessageId)
                    ->where('provider_msg_id', $providerMessageId)
                    ->first();

                if (! $row instanceof OutreachMessage) {
                    return false;
                }

                return $work($row);
            }
        );
    }

    /**
     * Mark one outcome once.
     *
     * ⚠️ **A CONDITIONAL UPDATE RATHER THAN A READ AND A SAVE, FOR
     * `SendingHealth::increment()`'s OWN REASON.** Two SNS deliveries of one
     * event can be handled by two workers at the same moment; a read-then-write
     * lets both see null and both count. The database decides, and the affected
     * count is what says whether this call is the one that counted.
     *
     * @param  'delivered_at'|'complained_at'  $column
     */
    private function stamp(OutreachMessage $row, string $column): bool
    {
        $marked = OutreachMessage::query()
            ->whereKey($row->getKey())
            ->whereNull($column)
            ->update([$column => now()]);

        if ($marked === 0) {
            Log::info('A mail feedback event was received twice and counted once.', [
                'outcome' => $column,
            ]);

            return false;
        }

        return true;
    }
}

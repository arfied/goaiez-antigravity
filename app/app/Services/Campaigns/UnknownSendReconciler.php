<?php

declare(strict_types=1);

namespace App\Services\Campaigns;

use App\Contracts\SendLogReader;
use App\Enums\AutopilotActionType;
use App\Enums\CampaignRecipientStatus;
use App\Enums\CarrierVerdict;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Services\ActivityService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Sms\SendLogEntry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;

/**
 * Asking the carrier about the sends this application could not account for.
 *
 * ⛔ **`CampaignRecipientStatus::Unknown` IS CORRECT AND IT IS HALF AN ANSWER**
 * (7180, 7192(d), 7198(b)). A campaign send whose outcome nobody can establish
 * ends on a terminal row that claims nothing in either direction — the right
 * thing to record — and until this class existed **nothing in the application
 * could ever find out**. The row was where the question stopped.
 *
 * This is the other half: once an hour, for as long as the vendor will still
 * answer, it asks *"do you have this message?"* about every unresolved row and
 * promotes the ones the carrier confirms.
 *
 * ## The population, stated as a property — and the send it cannot reach
 *
 * ⛔ **THIS ASKS ABOUT A CAMPAIGN SEND WHOSE OUTCOME WAS NEVER ESTABLISHED, AND
 * ABOUT NOTHING ELSE.** Four predicates below state it — `Unknown`, a non-null
 * handle, a null `carrier_answered_at`, an attempt inside the vendor's window —
 * over `campaign_recipients` and no other table. ⚠️ **So every other send this
 * platform makes is outside it by construction**: a review invite, a missed-call
 * text-back, a recovery check-in and an owner-channel text are `outreach_messages`
 * rows, which nothing here reads.
 *
 * ⛔ **AND A MESSAGE THE CARRIER ACCEPTED AND THEN FILTERED IS OUTSIDE IT TOO,
 * WHICH IS THE ONE THAT MATTERS TODAY** (12340). `InfobipClient::send()`
 * answers with a `SentText` for any 200 whose per-message status was not
 * `REJECTED` or `UNDELIVERABLE` — `PENDING` is an acceptance and that driver
 * says so in as many words — so the row is written `Sent` and **no predicate
 * here will ever match it again**. The 10DLC campaign this platform sends over
 * stood rejected under code **9999** when this paragraph was written (12279),
 * and an unregistered sender is not refused by the carrier but *filtered*, so
 * the excluded case is the expected outcome of a send rather than an edge.
 *
 * ⛔ **DO NOT "FIX" THAT BY WIDENING THE PREDICATE.** The two are different
 * questions. *"Did anything happen?"* has one safe answer when the carrier is
 * silent — leave the row alone — which is this class's own first refusal above.
 * *"Did the thing we recorded actually happen?"* has **no** safe answer from a
 * log, because a filtered message and a message still in flight are the same
 * absence, and the sweep would have to read that absence as a negative to be
 * worth anything.
 *
 * ⚠️ **WHAT WOULD HAVE TO BE TRUE TO BRING IT INSIDE IS A DELIVERY RECEIPT AND
 * NOT A LOOKUP.** `DeliveryReceipts::apply()` already lands a carrier's own
 * verdict — on `outreach_messages.provider_msg_id`, a different column on a
 * different table from the `campaign_recipients.provider_message_id` this
 * promotion writes, on the row this class deliberately does not rebuild. Three
 * things are missing and none of them is a predicate: **the receipt has to
 * arrive**, it has to **name a row this tenant holds**, and a campaign send
 * would have to **keep** the `outreach_messages` row that
 * `PlatformMessageSender::transact()` rolls back when the send fails.
 * ⛔ **The first is not engineering.** Whether an account under a rejected
 * campaign emits a receipt at all, and whether this platform's endpoint is the
 * subscription that receives one, are facts about a vendor account (12276,
 * 12279) — **nothing in this repository can establish either, and a test
 * asserting one would be inventing a fake.**
 *
 * ## It moves rows in exactly one direction, and the refusal is the design
 *
 * ⛔ **`Unknown` → `Sent`, AND NEVER `Unknown` → ANYTHING OUTSTANDING.** The
 * tempting second direction is to read the carrier's `REJECTED` as *"nothing
 * went, put them back in the audience"*, and it is refused twice over:
 *
 *   1. ⛔ **A MISSING ANSWER IS NOT A NEGATIVE.** {@see SendLogReader} lists the
 *      three things that produce one and only one of them is a message that
 *      never went. A reconciler that re-opened a contact on an absent key would
 *      text somebody who is already holding the message — which is the single
 *      outcome the whole `Unknown` mechanism was built to prevent.
 *   2. ⚠️ **AND THE CAMPAIGN IS ALREADY CLOSED.** `closeIfFinished()` completes
 *      a campaign the moment nothing is outstanding, so an `Unknown` row is
 *      normally the *last* thing that happened to it. Making a row outstanding
 *      again on a `Completed` campaign produces a recipient no pass will ever
 *      pick up — `RunCampaignJob::campaign()` refuses a non-sendable status —
 *      which is strictly worse than the terminal row it replaced: outstanding,
 *      unreachable and invisible.
 *
 * **So a failure is recorded, counted, logged and left alone.** Whether it
 * should also re-open the campaign is a question about audiences rather than
 * about carriers and it is raised at 7377 rather than guessed at here.
 *
 * ⛔ **"RECORDED" IS NEW AND IT IS 7501: THE ROW USED TO BE LEFT *UNTOUCHED*,
 * AND SO IT WAS ASKED ABOUT AGAIN EVERY HOUR FOR TWO DAYS.** The sweep matches
 * on status, a non-null handle and the vendor's window, and a refusal changed
 * none of them — so one contact the carrier had already answered for cost
 * roughly forty-seven further vendor calls, forty-seven identical log lines and
 * forty-seven increments of a printed figure an operator would reasonably read
 * as a number of people. `carrier_answered_at` is the full stop, and its
 * migration carries the argument for why it is a column rather than a status.
 *
 * ## The two failures that are not one failure — and what neither of them proves
 *
 * ⛔ **`REJECTED` AND `UNDELIVERABLE` ARRIVED AS ONE BOOLEAN AND ARE DIFFERENT
 * FACTS** (7500), so {@see CarrierVerdict} tells them apart in the driver, where
 * the vocabulary is read. ⛔ **AND THE SLICE THAT SPLIT THEM SET OUT TO ACT ON
 * THE SPLIT AND REFUSED TO, HAVING READ THE VENDOR** (7506): its brief said
 * `UNDELIVERABLE` means *the carrier took the message and could not deliver it*
 * — and Infobip's own status page documents that group as *"The message has not
 * been delivered"*, with `UNDELIVERABLE_NOT_SENT`, *"The message has not been
 * sent"*, sitting inside it. The mirror holds for `REJECTED`, whose group
 * description ends *"or the operator has returned `REJECTED` as final status"*.
 * **A group name therefore establishes neither that the message was accepted nor
 * that it never went**, and this class promotes on neither. What both establish
 * is the only thing the owner is owed — **it did not arrive** — and that is what
 * gets written and reported.
 *
 * ⚠️ **THE THIRD THING THE BOOLEAN COULD NOT SAY WAS NOTHING AT ALL.** A log
 * line carrying no status was `false` too, so a silence was counted, logged and
 * reported as a carrier refusal; under 7501's full stop that reading would have
 * become permanent.
 *
 * ## What a promotion does and does not repair
 *
 * ✅ The row gains a real `provider_message_id` and a `sent_at` the carrier
 * stands behind. {@see SendCollisionArbiter} then counts the touch through the
 * column it already reads, and `CampaignReplyResolver::campaignSends()` — which
 * takes `Sent` rows with a `sent_at` — can attribute a reply from that contact
 * for the first time. Neither needed widening; 7192(a) and 7192(b) close on the
 * promotion alone.
 *
 * ⚠️ **AND THE TOUCH IT COUNTS CAN BE OLDER THAN THE ONE IT REPLACES, WHICH IS
 * CORRECT AND IS WORTH KNOWING** (7503). While the row is `Unknown` the arbiter
 * counts the touch off `updated_at` — the attempt; once promoted it counts it
 * off the carrier's `sent_at`, which is the same moment as far as anybody can
 * tell but is stated by the vendor rather than by us. A row promoted after the
 * marketing-touch window has passed therefore stops blocking a second campaign,
 * **because it should**: the window is measured from the send, not from the day
 * we found out about it. The failure would be the other reading — extending a
 * live person's block by the age of our own ignorance.
 *
 * ⛔ **IT DOES NOT REBUILD THE `outreach_messages` ROW AND DOES NOT DEBIT THE
 * CREDIT** (7370). `PlatformMessageSender::transact()` rolled the row, the
 * `SendKey` claim, the debit and the cost entry back together when the send
 * failed, and re-creating them here would put a second writer beside the one
 * chokepoint that owns them. **The consequence is stated rather than hidden**:
 * a reconciled send is not billed to the tenant, does not appear in
 * `MessageLog`, moves no `SendingHealth` counter and can never have a delivery
 * receipt applied to it. Every one of those errs toward the customer — an
 * un-billed message rather than a surprise charge is rule 43's surviving half,
 * and a complaint-rate denominator one short trips the containment *earlier*.
 *
 * ⛔ **AND `campaign_recipients.provider_message_id`, WHICH THE PROMOTION
 * WRITES, IS READ BY NO CODE IN `app/`** (7504). Its reader is a database
 * CHECK — `campaign_recipients_sent_rows_are_complete` insists a `sent` row
 * names a message — so what is enforced is its *presence* and nothing reads its
 * *value*. **The reader that would make it load-bearing is the delivery-receipt
 * join, and that join is on `outreach_messages.provider_message_id`: a
 * different column on a different table, on the row this promotion deliberately
 * does not rebuild.** 272's shape, reported rather than closed, because the
 * honest way to close it is 7377(b)'s owner question and not a reader invented
 * to satisfy a lint.
 */
final class UnknownSendReconciler
{
    /**
     * The vendor's own log window.
     *
     * ⚠️ **FORTY-EIGHT HOURS IS INFOBIP'S FIGURE AND NOT A CHOICE**, so it is a
     * constant rather than a registry key — the same call `LogTexter` makes
     * about GSM-03.38's 160 and `ZernioGbpClient` about somebody else's `limit`
     * ceiling. Both log endpoints state it: *"the available logs are limited to
     * those generated in the last 48 hours"* (raw OpenAPI
     * `https://api.infobip.com/platform/1/openapi/sms` and `…/openapi/mms`, both
     * version `3.222.1`, fetched 2026-08-22).
     *
     * ⛔ **A ROW OLDER THAN THIS IS UNANSWERABLE FOR EVER AND STOPS BEING
     * ASKED ABOUT.** That is the whole reason this slice was urgent: the window
     * runs from the attempt, not from the day somebody gets round to building
     * the lookup.
     */
    public const int VENDOR_LOG_WINDOW_HOURS = 48;

    /**
     * How many rows one tenant's pass may ask about.
     *
     * ⚠️ **FIVE VENDOR CALLS PER TENANT PER HOUR, WHICH IS WHERE THIS NUMBER
     * COMES FROM.** `InfobipClient` chunks at forty handles per request against
     * the vendor's 2,048-character filter, so two hundred is five round trips.
     *
     * ⚠️ **AND THE ORDER IS OLDEST FIRST, WHICH IS WHAT STOPS THE CAP
     * STARVING ANYBODY.** A tenant with more unresolved rows than this would,
     * under a newest-first or an id ordering, ask about the same two hundred
     * every hour while the rest aged out unasked. Oldest first spends each pass
     * on the rows closest to falling out of the window, and rows that can never
     * be answered leave the set on their own after forty-eight hours.
     */
    private const int MAX_PER_PASS = 200;

    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly SendLogReader $carrierLog,
    ) {}

    /**
     * Reconcile the current tenant's unresolved sends.
     *
     * ⚠️ **IT RUNS INSIDE AN ESTABLISHED TENANT AND SAYS SO LOUDLY.**
     * `campaign_recipients` is FORCE row-level security, so a pass with no
     * tenant would quietly find nothing and report a cheerful zero — 6181's
     * silent-zero shape, on a sweep whose whole job is to notice things.
     *
     * ⚠️ **`confirmed` AND `undelivered` ARE NOW COUNTS OF CONTACTS AND NOT
     * COUNTS OF ANSWERS** (7501). Each row can reach either of them at most once
     * ever, because both write something the sweep then excludes. `unresolved`
     * is deliberately still per-pass: it is the number of questions still open,
     * and the same row is meant to appear in it again next hour.
     *
     * @return array{confirmed: int, undelivered: int, unresolved: int}
     */
    public function reconcile(): array
    {
        Tenancy::idOrFail();

        $confirmed = 0;
        $undelivered = 0;
        $unresolved = 0;

        $rows = CampaignRecipient::query()
            ->where('status', CampaignRecipientStatus::Unknown->value)
            // ⚠️ A row with no handle can never be reconciled: the transport
            // had nothing to put on the wire, or the send predates the handle
            // entirely. Asking about it would be asking about nothing.
            ->whereNotNull('send_handle')
            // ⛔ **AND A ROW THE CARRIER HAS ALREADY ANSWERED FOR IS NOT ASKED
            // ABOUT AGAIN** (7501). A failure is the carrier's final word about
            // this handle and no later pass can change it — so without this
            // clause the same row bought a vendor call an hour for two days and
            // inflated the figure the operator reads.
            ->whereNull('carrier_answered_at')
            ->where('updated_at', '>=', now()->subHours($this->vendorLogWindowHours()))
            ->orderBy('updated_at')
            ->limit(self::MAX_PER_PASS)
            ->get();

        if ($rows->isEmpty()) {
            return ['confirmed' => 0, 'undelivered' => 0, 'unresolved' => 0];
        }

        /** @var list<string> $handles */
        $handles = array_values(array_unique(
            $rows->map(static fn (CampaignRecipient $row): string => (string) $row->send_handle)->all()
        ));

        $answers = $this->carrierLog->outcomesFor($handles);

        /** @var array<int, array{confirmed?: int, undelivered?: int}> $settled */
        $settled = [];

        foreach ($rows as $row) {
            $entry = $answers[(string) $row->send_handle] ?? null;

            if (! $entry instanceof SendLogEntry) {
                // No answer. The row is untouched and stays askable until it
                // falls out of the window — see the contract for why this is
                // never read as "nothing was sent".
                $unresolved++;

                continue;
            }

            // ⛔ **A `match` WITH NO `default`, SO A FIFTH VENDOR STATE IS A
            // COMPILE-TIME CONVERSATION** rather than a silent arm. Every case
            // was decided in the driver; nothing here looks at a vendor string.
            $outcome = match ($entry->verdict) {
                CarrierVerdict::Took => $this->confirmIfTimed($row, $entry),
                // ⚠️ **TWO DIFFERENT FACTS, ONE ARM, AND THE SHARED ARM IS THE
                // FINDING RATHER THAN A SHORTCUT** (7506). Neither group name
                // establishes whether the message was ever accepted — see
                // {@see CarrierVerdict} for the vendor's own wording — so
                // neither may promote a row to `Sent` and neither may claim
                // nothing went. What both establish is that it did not arrive,
                // and that is what is recorded. They stay separate cases because
                // 7377(a)'s re-opened audience will need them separate.
                CarrierVerdict::Undelivered, CarrierVerdict::Declined => $this->recordFailure($row, $entry),
                // ⛔ **A LOG LINE WITH NO STATUS IS NOT AN ANSWER**, and until
                // 7500 it was counted as a refusal. Left where it is, asked
                // again next hour, and never recorded as a full stop.
                CarrierVerdict::Unstated => 'unresolved',
            };

            if ($outcome === 'unresolved') {
                $unresolved++;

                continue;
            }

            // ⚠️ Counted per campaign as well as in total, because the sentence
            // the owner is owed is about **their campaign** and the sweep is
            // walking every campaign this tenant has.
            $campaignId = (int) $row->campaign_id;

            $settled[$campaignId][$outcome] = ($settled[$campaignId][$outcome] ?? 0) + 1;

            if ($outcome === 'confirmed') {
                $confirmed++;
            } else {
                $undelivered++;
            }
        }

        $this->correctTheOwnersCount($settled);

        return ['confirmed' => $confirmed, 'undelivered' => $undelivered, 'unresolved' => $unresolved];
    }

    /**
     * The carrier has this message — promote the row if it will say when.
     *
     * @return 'confirmed'|'unresolved'
     */
    private function confirmIfTimed(CampaignRecipient $row, SendLogEntry $entry): string
    {
        if ($entry->sentAt === null) {
            // ⛔ **THE CARRIER HAS IT AND WILL NOT SAY WHEN.**
            // `campaign_recipients_sent_rows_are_complete` requires a time, and
            // the only times available are a substituted `now()` — which would
            // file a message sent thirty hours ago as today's marketing touch —
            // or none. The row stays unresolved and is asked again next hour,
            // when the vendor may have filled it in.
            return 'unresolved';
        }

        $this->confirm($row, $entry);

        return 'confirmed';
    }

    /**
     * Record that the carrier has this message after all.
     *
     * ⛔ **THIS IS THE SECOND AND LAST PLACE IN THE APPLICATION THAT WRITES
     * `CampaignRecipientStatus::Sent`, AND A LINT SAYS SO** (7366). The other is
     * `RunCampaignJob::record()`, on a `SendOutcome` the transport returned.
     * Both write it only with a carrier's own word for the message in hand, and
     * a third writer would be a third idea of what *sent* means on this table.
     *
     * ⚠️ **THE TIME IS THE CARRIER'S AND NEVER `now()`.** It is the column
     * `SendCollisionArbiter` measures the marketing-touch window against, so a
     * substituted clock does not merely record the wrong hour — it can block a
     * send that was allowed, or permit one that was not.
     *
     * ⚠️ **`send_handle` IS DELIBERATELY LEFT IN PLACE.** It is spent as a
     * question and it is still the evidence of *how* this row became `Sent`,
     * which is the first thing anybody reviewing a reconciled send will want.
     *
     * ⚠️ **AND `carrier_answered_at` IS STAMPED HERE TOO, WHICH IS WHAT MAKES
     * THAT EVIDENCE READABLE** (7501). Beside `sent_at` it is the pair that says
     * this row was rescued rather than written by the runner: one clock is the
     * carrier's and says when the message went, the other is ours and says when
     * we found out. A row where they are thirty hours apart came through here.
     * ⚠️ **Timestamps stay ON for this write**, unlike the failure arm: this row
     * stops being `unknown`, so the clause that reads `updated_at` as an attempt
     * time no longer applies to it.
     */
    private function confirm(CampaignRecipient $row, SendLogEntry $entry): void
    {
        $row->status = CampaignRecipientStatus::Sent;
        $row->refusal_reason = null;
        // ⚠️ **THE HANDLE IS THE CARRIER'S NAME FOR THIS MESSAGE**, because the
        // carrier was asked by it and answered about it. {@see SendLogEntry}
        // carries the argument, and the reason it is not a second field.
        $row->provider_message_id = $entry->handle;
        $row->sent_at = $entry->sentAt;
        $row->carrier_answered_at = now();
        $row->save();

        Log::info('An unconfirmed campaign send was confirmed by the carrier.', [
            'campaign_id' => $row->campaign_id,
            'group' => $entry->statusGroup,
        ]);
    }

    /**
     * Record that the carrier says this message did not arrive — and stop
     * asking.
     *
     * ⛔ **THE STATUS IS NOT TOUCHED AND THE MIGRATION SAYS WHY.** There is no
     * case on `CampaignRecipientStatus` this class may honestly write here:
     * `refused` needs a `SendRefusalReason`, and every one of those names a
     * decision *this application* took, so the carrier's answer would arrive
     * dressed as our own; `failed` is outstanding on a campaign that has
     * already closed. **What changed is that the question has an answer, and
     * that is the only thing recorded.**
     *
     * ⛔ **AND IT IS THE ONLY THING THAT MAY BE RECORDED, WHICH IS 7506.**
     * Neither `UNDELIVERABLE` nor `REJECTED` establishes at group level whether
     * the message was ever accepted — {@see CarrierVerdict} quotes the vendor —
     * so writing `Sent` here on the strength of one of them would put *"this
     * person was texted"* on a row the vendor may be saying was never sent, and
     * telling the owner *"they did get it"* would be a false statement made
     * directly to them.
     *
     * ⛔ **`updated_at` IS NOT MOVED, AND THIS IS THE LOAD-BEARING LINE IN THE
     * METHOD** (7502). It is the attempt time for an `unknown` row:
     * `SendCollisionArbiter` counts such a row as a marketing touch for as long
     * as `updated_at` sits inside the touch window, so an ordinary `save()`
     * would extend a real person's block by a full window **every hour**, on
     * the strength of a full stop rather than a send. `timestamps = false` is
     * therefore not tidiness — it is the difference between recording an answer
     * and inventing a touch. A test drives it red.
     *
     * ⚠️ **THE PROMOTING ARM IS DIFFERENT AND KEEPS ITS TIMESTAMPS**: that row
     * stops being `unknown`, so the clause that reads `updated_at` no longer
     * applies to it and the arbiter reads the carrier's `sent_at` instead.
     *
     * ⚠️ **AND THE CLOCK IS OURS, NOT THE CARRIER'S.** This column says *when
     * we were told*, which is a fact about this application; the vendor's own
     * timestamps only ever land in `sent_at`, where a carrier's word is what is
     * wanted.
     *
     * @return 'undelivered'
     */
    private function recordFailure(CampaignRecipient $row, SendLogEntry $entry): string
    {
        $row->carrier_answered_at = now();
        $row->timestamps = false;
        $row->save();
        $row->timestamps = true;

        Log::info('The carrier accounted for an unconfirmed campaign send and had not delivered it.', [
            'campaign_id' => $row->campaign_id,
            // The vendor's status *group*, which is a fixed enumeration
            // — `TextNotDeliverable`'s rule about names rather than
            // descriptions, one layer over. ⚠️ **It is the log line's whole
            // job to carry which of the two failures it was**, because the row
            // deliberately does not.
            'group' => $entry->statusGroup,
        ]);

        return 'undelivered';
    }

    /**
     * Tell the owner what became of the contacts nobody could confirm.
     *
     * ⛔ **BECAUSE THE NUMBER THEY WERE GIVEN AT CLOSE IS NEVER RIGHT AGAIN**
     * (7503). `RunCampaignJob::reportWhoCouldNotBeConfirmed()` files *"N
     * contacts may already have this message"* once, at the moment the campaign
     * closes, and every answer this class gets afterwards makes that N one too
     * high. Before this the corrections went to `storage/logs`, which is not a
     * place an owner reads.
     *
     * ⛔ **TWO ITEMS AND NOT ONE, WHICH IS 7187's ARGUMENT ONE TABLE OVER.**
     * *"It was sent after all"* and *"it did not arrive"* are opposite
     * statements and a single sentence carrying both counts cannot pluralise
     * either. Each is silent at zero, so the ordinary pass — which settles
     * nothing — files nothing at all.
     *
     * ⚠️ **THE WORDS ARE THE ONES THE ANSWER SUPPORTS AND NOT THE ONES THE
     * VENDOR'S GROUP NAMES SUGGEST** (7506). *"Was sent"* is what
     * `CampaignRecipientStatus::Sent` means on this table — the carrier had the
     * message — and never *"they read it"*; *"did not arrive"* is the whole of
     * what `UNDELIVERABLE` and `REJECTED` establish between them, and saying
     * *"never sent"* would be this application asserting a sub-status it cannot
     * see.
     *
     * ⚠️ **`SystemMessage` RATHER THAN `OwnerActionNeeded`, WHICH BOTH CLOSING
     * REPORTS USE.** Nothing here asks the owner for anything: the campaign is
     * closed, the audience cannot be re-opened (7377), and both sentences are
     * strictly better news than the one they correct. A second *needs you*
     * marker for that would be a feed that punishes reading, which is the
     * failure `AutopilotJob::activityAction()` argues at length.
     *
     * ⚠️ **NO IDENTIFIER, NO REASON STRING, NO PERSON.** A campaign id, a count
     * this system made, and nothing else — the standing rule for every title
     * that reaches this feed.
     *
     * @param  array<int, array{confirmed?: int, undelivered?: int}>  $settled
     */
    private function correctTheOwnersCount(array $settled): void
    {
        if ($settled === []) {
            return;
        }

        // ⚠️ One query for the whole pass, and the location may legitimately be
        // null: `campaigns.location_id` is nullable, and the feed takes a null
        // as "this tenant" rather than refusing the item.
        $locations = [];

        foreach (Campaign::query()->whereIn('id', array_keys($settled))->get(['id', 'location_id']) as $campaign) {
            $locations[(int) $campaign->getKey()] = $campaign->location_id;
        }

        foreach ($settled as $campaignId => $tally) {
            $confirmed = $tally['confirmed'] ?? 0;
            $undelivered = $tally['undelivered'] ?? 0;

            if ($confirmed > 0) {
                $this->file($campaignId, $locations[$campaignId] ?? null, $confirmed, trans_choice(
                    '{1} 1 contact we could not confirm was sent this message after all'
                    .'|[2,*] :count contacts we could not confirm were sent this message after all',
                    $confirmed,
                    ['count' => number_format($confirmed)],
                ), 'confirmed');
            }

            if ($undelivered > 0) {
                $this->file($campaignId, $locations[$campaignId] ?? null, $undelivered, trans_choice(
                    '{1} 1 contact we could not confirm did not receive this message'
                    .'|[2,*] :count contacts we could not confirm did not receive this message',
                    $undelivered,
                    ['count' => number_format($undelivered)],
                ), 'undelivered');
            }
        }
    }

    /**
     * One correction on the owner's feed.
     *
     * @param  'confirmed'|'undelivered'  $kind
     */
    private function file(int $campaignId, ?int $locationId, int $count, string $title, string $kind): void
    {
        app(ActivityService::class)->record(
            AutopilotActionType::SystemMessage,
            $locationId,
            [
                'campaign_id' => $campaignId,
                $kind => $count,
            ],
            $title,
        );
    }

    public function vendorLogWindowHours(): int
    {
        return $this->defaults->int('campaigns.unknown_send.vendor_log_window_hours');
    }
}

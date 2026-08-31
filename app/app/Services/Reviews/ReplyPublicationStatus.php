<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AutomationRunStatus;
use App\Enums\ReplyPublicationState;
use App\Models\AutomationRun;
use App\Models\GbpConnection;
use App\Models\Reply;
use App\Services\Config\DefaultsRegistry;

/**
 * Why an approved reply is not on the listing — derived, never stored (1738).
 *
 * The read half of `PostReplyJob`'s refusals, expressed as something an owner
 * can act on. It answers for the state of the world **now**, not for the state
 * at the moment of the attempt, which is why the ladder below re-asks the
 * registry and the connection rather than trusting `replies.error_message`.
 *
 * ⛔ **THIS CLASS NO LONGER READS `error_message` AT ALL, AND THAT IS 6685
 * CLOSED** (6720). It used to derive `NotAccepted` — *"We tried to publish this
 * and Google did not take it."* — from that column being non-null. **The column
 * does not mean that.** `PostReplyJob::handoff()` writes it for
 * `integration_disabled` and for `not_connected`, and neither of those leaves
 * this application, so the sentence asserted a decision Google had never made.
 * It was hidden by the ladder's earlier arms and only while
 * `gbp.zernio_enabled` was false — the arms stop matching the moment that row
 * is true and the location is connected, and then every handed-off reply tells
 * its owner Google saw the text and refused it.
 *
 * ✅ **THE EVIDENCE IS `replies.provider_declined_at` AND ONLY A VENDOR ROUND
 * TRIP CAN WRITE IT** (6722). `ReviewReplies::markDeclinedByProvider()` is its
 * one writer and it cannot be called without a `GbpRequestFailed` built from a
 * response the vendor returned — the same shape as `markPosted()` requiring a
 * `GbpReplyReceipt`. So a pre-flight refusal has no way to reach this arm: the
 * wrong sentence is unrepresentable rather than merely unreachable, which is
 * what the previous design was.
 *
 * ⚠️ **A LEGACY ROW CARRIES NOTHING AND FALLS TO `NotPublishedYet`, WHICH IS
 * TRUE OF EVERY ONE OF THEM** (6729). No backfill: a timestamp here is a claim
 * about what a third party did, and it may not be reconstructed from a string
 * this application wrote to itself. What such a row loses is precision, and
 * approving it again writes real evidence.
 *
 * ⚠️ **`error_message` KEEPS ITS OWN RULE AND IT IS UNCHANGED** (1748). It is
 * still written, still never rendered, and its contents are still for whoever
 * debugs the row. What is gone is its second job — deciding a sentence — which
 * is the job it was never able to do correctly.
 *
 * ⚠️ **THE LADDER IS ORDERED AND THE ORDER IS LOAD-BEARING.** `PublishingOff`
 * outranks `NotConnected` because `Account\Connections` gates its own connect
 * control on the same registry key: with the integration off, telling an owner
 * to connect Google sends them to a screen with nothing on it.
 *
 * ⛔ **AND `PublishingOff` IS NO LONGER THE TOP OF IT — THE PARAGRAPH ABOVE IS
 * KEPT BECAUSE IT IS STILL THE ARGUMENT FOR ARMS TWO TO FOUR** (6942, closing
 * 6828). `replies.publish_unconfirmed_at` is asked **first**, above the
 * registry and above the connection, because the four sentences below it all
 * end in a claim that the reply is not on the listing — *"has not gone up"*,
 * *"could not be published"*, *"did not take it"*, *"This is not on Google."* —
 * and an unconfirmed row is the one row about which none of them may be said.
 * The switch really is off and the connection really is broken on those rows;
 * what is wrong is the clause after the *so*.
 *
 * ⚠️ **THE FIRST-PLACE ARGUMENT FOR `PublishingOff` SURVIVES INTACT AGAINST
 * EVERYTHING IT WAS MADE AGAINST.** It is about not sending an owner to a
 * screen the same registry key has emptied, and `PublishUnconfirmed` sends them
 * to **Google**, which needs nothing from this platform: they can look with the
 * integration off, with their grant revoked, and with this application down.
 * The second clause of that next step — *approve it again* — does degrade when
 * publishing is off, and it degrades into the honest answer rather than a
 * silent one: `approve()` clears the column, `handoff()` records that it could
 * not post, and the card then reads `PublishingOff`, which by then is true.
 *
 * ⚠️ **IT READS THE INSTANCE AND `PostReplyJob` READS THE DATABASE, AND
 * NEITHER IS THE OTHER'S MISTAKE** (6823, 6945). That guard sits three queries
 * downstream of its own `find()` and is the last thing between a stale row and
 * a second publication, so it must re-ask. This is a render: the rows were
 * loaded by `awaitingPublicationAcrossTenant()` for this response, a card is
 * a report on the instant it was drawn, and a query per card would be the N+1
 * `publicationStates()` is built to avoid. **A stale read here costs a sentence
 * on a page that is about to be refreshed; a stale read there costs a second
 * reply on a public listing.**
 *
 * ⚠️ **THE FIRST ARM IS NO LONGER A ONE-WAY DOOR, AND UNTIL 2026-08-21 IT WAS**
 * (6955, closed at 7120–7139). `publish_unconfirmed_at` had three clearers and
 * all three were actions on the reply rather than answers about it, so a card
 * reaching this arm stayed on it until the owner pressed *Approve again* —
 * which nulls the column and drops the row to `NotPublishedYet`, restoring the
 * exact claim this arm exists to refuse. `ReviewReplies::reconcileUnconfirmedPublication()`
 * now settles it from a provider report of **no owner reply on the review**,
 * so a row leaves this arm because something was read rather than because
 * somebody guessed.
 *
 * ⛔ **AND IT NEVER LEAVES IT IN THE OTHER DIRECTION.** A provider report that
 * an owner reply *does* exist settles nothing and moves nothing: `hasReply` is
 * true for somebody else's reply, for one typed into Google's own dashboard,
 * and for one of ours that Google rejected in moderation — Google's
 * `ReviewReply.reviewReplyState` is `PENDING`/`REJECTED`/`APPROVED` and Zernio
 * relays none of it. So the label on this arm is permanent for that population
 * by construction, and that is a fact about the vendor's data rather than about
 * this application.
 *
 * ⚠️ **NOTHING CAN CARRY BOTH TIMESTAMPS TODAY AND THE ORDER IS STILL WRITTEN
 * DOWN** (6944). `PostReplyJob::execute()` refuses at `publishIsUnconfirmed()`
 * before the vendor call, so `markDeclinedByProvider()` is unreachable while the
 * column is set, and `approve()` clears all three together. If a later slice
 * makes both reachable, an unconfirmed attempt still outranks a later decline:
 * a refusal of the *second* attempt says nothing about whether the *first* one
 * published, and `NotAccepted` is the arm that speaks for Google.
 *
 * ## ⚠️ A fifth thing an abandoned run can make false — 10188, 10260–10269
 *
 * ⛔ **`NotPublishedYet`'S "This is not on Google" IS SAID ABOUT A RUN THIS
 * PLATFORM'S OWN WORKER KILLED MID-FLIGHT, AND THAT CAN BE FALSE.**
 * `AutomationRunStatus::Abandoned` means `AutopilotJob::closeAbandonedRun()`
 * found a `Running` row left open by a worker that outran `--timeout` — which
 * for `Jobs\Reviews\PostReplyJob` can happen **during** the vendor call, after
 * Google has already accepted the reply and before this application recorded
 * it. Wave 34 named three states that reach this arm — abandoned, skipped, and
 * a `clientRefused` throw — and refused all three together because reading
 * `automation_runs` needed a join on unindexed `input->>'reply_id'` on a table
 * it had just proved unbounded (10188). ⚠️ **Only `Abandoned` is closed here,
 * and the narrowing is argued rather than a shortcut** (10264): a **skipped**
 * run never reached the vendor, so "not on Google" is true, and the existing
 * remedy is already correct. ⛔ **ITS STATED REASON — *"because nothing else
 * will ever retry it (1755)"* — STOPPED BEING TRUE ON 2026-08-28 AND IS KEPT
 * BECAUSE THE NARROWING IT SUPPORTS IS UNCHANGED** (11040):
 * `reviews:retry-stranded-replies` does retry such a row, once, and that is
 * **correct for a skipped run and only for a skipped run** — nothing reached
 * the vendor, so a second attempt cannot be a second post. The three states
 * that could be are refused by the sweep's own query and by
 * {@see self::abandonedReplyIds()}, whose one caller outside this class is that
 * command. A
 * **`clientRefused`** throw also never reaches the vendor, and
 * `PostReplyJob::execute()`'s own docblock says withholding an owner item for
 * it is deliberate — there is nothing for the owner to do about our own
 * credential being missing. **Only `Abandoned` can make the sentence false**,
 * and it is epistemically identical to {@see ReplyPublicationState::PublishUnconfirmed}'s
 * own "we asked and do not know" — so an abandoned run is read as that case
 * rather than as a sixth one, on 10181/10182's rule against two sentences an
 * owner reads as different problems with the same one action.
 *
 * ⚠️ **`abandonedReplyIds()` IS A SECOND BATCH QUERY, NOT A QUERY PER CARD** —
 * the same discipline `Account\ReplyQueue::publicationStates()`'s own comment
 * states for the registry and the connections, and the one this class's own
 * docblock above already argues at length for `PostReplyJob`'s re-check.
 */
final class ReplyPublicationStatus
{
    /**
     * ⚠️ **THE STRING IS `PostReplyJob::automationKey()`'s AND IS PINNED BY A
     * TEST RATHER THAN BY THIS COMMENT** — `VisibilitySyncHistory::REVIEW_SYNC_AUTOMATION`'s
     * own precedent. A typo here is invisible: the query matches nothing,
     * every reply reads as never-abandoned, and `NotPublishedYet` is said for
     * ever about a run this application cannot see.
     */
    private const string POST_REPLY_AUTOMATION = 'reviews.post_reply';

    public function __construct(private readonly DefaultsRegistry $defaults) {}

    /**
     * Whether replies can be published at all right now.
     *
     * Hoisted out of {@see self::for()} so a list screen asks the registry once
     * instead of once per card — the same reason `Account\ReplyQueue` loads
     * every connection with one query.
     */
    public function publishingIsSwitchedOn(): bool
    {
        return $this->defaults->value('gbp.zernio_enabled') === true;
    }

    /**
     * Reply ids whose most recent `reviews.post_reply` run was abandoned —
     * one query for the whole list, keyed off the generated `reply_id` column
     * `2026_08_26_172707_add_reply_id_to_automation_runs.php` added.
     *
     * ⚠️ **THE SAME ORDERING `AutomationRunRetention::survivorIds()` AND
     * `VisibilitySyncHistory::lastRun()` BOTH USE** — `started_at DESC NULLS
     * LAST, id DESC` — because "the most recent attempt" has to be the
     * identical row everywhere this application asks for it.
     *
     * @param  array<int, int>  $replyIds
     * @return array<int, true> reply id => true, for O(1) membership checks
     */
    public function abandonedReplyIds(array $replyIds): array
    {
        if ($replyIds === []) {
            return [];
        }

        $abandoned = [];

        foreach (
            AutomationRun::query()
                ->selectRaw('distinct on (reply_id) reply_id, status')
                ->where('automation_key', self::POST_REPLY_AUTOMATION)
                ->whereIn('reply_id', $replyIds)
                ->orderBy('reply_id')
                ->orderByRaw('started_at desc nulls last')
                ->orderByDesc('id')
                ->get() as $run
        ) {
            if ($run->status === AutomationRunStatus::Abandoned && $run->reply_id !== null) {
                $abandoned[$run->reply_id] = true;
            }
        }

        return $abandoned;
    }

    /**
     * ⛔ **`$planIsRunning` IS REQUIRED AND HAS NO DEFAULT, WHICH COST SIXTEEN
     * CALL-SITE EDITS AND IS THE POINT** (11202). The dangerous value is `true`
     * — a caller that forgot it would silently omit
     * `ReplyPublicationState::NotEntitled` and tell a lapsed account its
     * reply is merely *not on Google*, which is the arm this parameter exists
     * to add. `CLAUDE.md`'s own rule: the dangerous default is therefore not the
     * default, and every existing caller was made to fail loudly rather than
     * quietly inherit the wrong premise.
     *
     * ⚠️ **THE TWO BOOLEANS EITHER SIDE OF IT ARE POSITIONALLY SWAPPABLE AND
     * NOTHING IN THE TYPE SYSTEM WOULD NOTICE**, so this method is called with
     * named arguments everywhere. The declaration order is the ladder order —
     * platform switch, then plan, then the abandoned run — which is the only
     * mnemonic on offer.
     *
     * ⚠️ **IT IS ASKED ONCE PER LIST, NEVER ONCE PER CARD**, for
     * `publishingIsSwitchedOn()`'s reason: entitlement is a fact about the
     * business, every card on this screen belongs to the same business, and a
     * a `Subscriptions::isEntitled()` call per row would be a query per row.
     *
     * @param  ?GbpConnection  $connection  This reply's location's connection, or null.
     * @param  bool  $planIsRunning  `Services\Billing\Subscriptions::isEntitled()`
     *                               for this reply's business, asked by the caller.
     *                               Spelled bare rather than as an `@see`: Pint
     *                               promotes one into a real `use` import, and
     *                               this class must not gain a dependency it
     *                               does not call — `Enums\SubscriptionStatus`
     *                               names the same trap.
     * @param  bool  $lastAttemptAbandoned  Whether the most recent post_reply
     *                                      run for this reply was killed
     *                                      mid-flight — {@see self::abandonedReplyIds()}.
     */
    public function for(
        Reply $reply,
        ?GbpConnection $connection,
        bool $publishingIsSwitchedOn,
        bool $planIsRunning,
        bool $lastAttemptAbandoned = false,
    ): ReplyPublicationState {
        // ⛔ FIRST, ABOVE THE SWITCH AND ABOVE THE CONNECTION (6942). Every
        // arm below this one ends in a claim that the reply is not on the
        // listing, and this is the one row entitled to none of them. Moving
        // this check down the ladder does not make a sentence vaguer; it makes
        // the application state, to the one person who can go and check, that a
        // reply which may be public under their name is not.
        //
        // ⚠️ `$lastAttemptAbandoned` JOINS THE COLUMN CHECK RATHER THAN
        // FOLLOWING IT, ON EQUAL FOOTING (10264, 10265). Both answer the
        // identical question — "did the last attempt reach Google and we lost
        // the answer" — for two different failure shapes, and a run that is
        // abandoned can never also have set `publish_unconfirmed_at`:
        // `ReviewReplies::approve()` clears that column before every
        // dispatch, so the two conditions describe disjoint attempts rather
        // than competing for one.
        if ($reply->publish_unconfirmed_at !== null || $lastAttemptAbandoned) {
            return ReplyPublicationState::PublishUnconfirmed;
        }

        if (! $publishingIsSwitchedOn) {
            return ReplyPublicationState::PublishingOff;
        }

        // ⛔ **UNDER THE PLATFORM SWITCH AND ABOVE THE CONNECTION** (11202).
        // With `gbp.zernio_enabled` down nothing publishes for anybody, so
        // *start your plan* would sell a subscription that would not have
        // published either; above the connection for the mirror image of
        // `PublishingOff`'s own first-place argument — reconnecting Google buys
        // an unentitled account nothing, so telling them to do it first sends
        // them at the wrong blocker.
        //
        // ⚠️ IT IS A FACT ABOUT THE ACCOUNT AND EVERY OTHER ARM IS A FACT ABOUT
        // THE REPLY, THE INTEGRATION OR THE LISTING. That is what makes it the
        // one arm this class cannot derive for itself: it is handed in.
        if (! $planIsRunning) {
            return ReplyPublicationState::NotEntitled;
        }

        if (! $connection instanceof GbpConnection || ! $connection->isUsable()) {
            return ReplyPublicationState::NotConnected;
        }

        // ⛔ THE EVIDENCE, NEVER `error_message` (6720). This is the one arm
        // that speaks for a third party, so it asks the one column only a
        // vendor round trip can write.
        if ($reply->provider_declined_at !== null) {
            return ReplyPublicationState::NotAccepted;
        }

        return ReplyPublicationState::NotPublishedYet;
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs\Reviews;

use App\Enums\AutopilotActionType;
use App\Enums\ReplyStatus;
use App\Exceptions\GbpRequestFailed;
use App\Jobs\AutopilotJob;
use App\Models\Business;
use App\Models\Location;
use App\Services\Billing\Subscriptions;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\GbpConnections;
use App\Services\Reviews\ReviewReplies;

/**
 * Publish an approved reply to Google (`17` GBP-04).
 *
 * ✅ **THIS PUBLISHES NOW, AND FOR MONTHS IT DID NOT** (decision 2330). The
 * vendor's create-reply endpoint was confirmed against their raw OpenAPI
 * document on 2026-08-11, which is the condition decision 534 set when it
 * refused to invent one from marketing copy. `canExecute()`'s vendor line is
 * gone; the approval line is not, and never was the same thing.
 *
 * ⚠️ **THE HANDOFF PATH IS NOT A STUB THAT SHOULD HAVE COME OUT WITH IT.**
 * `29` §2 rule 44 requires the whole review engine to run with zero GBP API
 * access, so a disconnected tenant, a disabled integration and an unapproved
 * draft each still get an honest outcome rather than a failed job — and the
 * three are reported apart, because `automation_runs` is the only trace and
 * "reconnect Google" is the wrong thing to tell somebody whose reply simply has
 * not been approved yet.
 *
 * ⚠️ **`automation_runs` IS THE ONLY TRACE AND UNTIL 6680 IT COULD NOT NAME THE
 * REPLY.** Decision 6528 found four returns from {@see self::execute()} that
 * write nothing to the `replies` row; the sharper half of that finding is that
 * the run row could not identify the reply either. `input()` carried a business
 * id and a location id, which every reply at that location shares, and the one
 * field that did name the row — `idempotency_key`, `post-reply:N` — is **nulled
 * by `releaseUnearnedClaim()` on every attempt that did not publish**. So the
 * identifier was erased by exactly the outcomes worth tracing, and three failed
 * runs against three replies read the same as three against one.
 *
 * ✅ **CLOSED HERE BY MAKING THE RUN ROW THE RECORD**: `input()` carries
 * `reply_id`, and every non-publishing return goes through
 * {@see self::unpublished()}, which stamps the reply id beside the outcome and
 * the reason. Both halves are needed — `input()` alone leaves a `skipped` run
 * unattributed, and the output alone leaves nothing to join a killed run to.
 *
 * ⛔ **AND NOTHING NEW IS WRITTEN TO `replies`, WHICH IS A DECISION AND NOT AN
 * OMISSION** (6684). Every refusal in `execute()` above the vendor call happens
 * **before anything reaches Google**, and its whole record is the run row: an
 * arm names itself there, and `error_message` names nothing. The two writers
 * that do touch the row — the revoked grant and the declined reply — both
 * reached the provider first.
 *
 * ⛔ **6684 WAS A RULE ABOUT WHO MAY CALL A METHOD AND IT HELD FOR ONE WAVE —
 * THE SENTENCE IS NOW UNREPRESENTABLE INSTEAD** (6685 closed at 6720). The rule
 * said writing `error_message` for a pre-flight refusal would render
 * `ReplyPublicationState::NotAccepted` — *"We tried to publish this and Google
 * did not take it."* — about an attempt that never left this application.
 * `execute()`'s arms obeyed it. **`handoff()`'s two arms pre-dated it and did
 * not**, and nothing could tell, because the ladder's earlier arms hid them
 * while `gbp.zernio_enabled` was false. `ReplyPublicationStatus` now derives
 * that sentence from `replies.provider_declined_at`, whose only writer is
 * `ReviewReplies::markDeclinedByProvider()` — and that method cannot be reached
 * without a `GbpRequestFailed` the vendor's own response produced. **So
 * `handoff()` still writes `error_message`, and there is no longer any sentence
 * it can cause.**
 *
 * ⛔ **A RETRYABLE FAILURE USED TO BE ONE THING AND IT IS TWO** (6525, closed at
 * 6820–6839). `execute()` wrote nothing to the row before the vendor call and
 * rethrew every retryable failure so the queue would come back. A timeout
 * reading the response is a retryable failure thrown **after Zernio accepted**,
 * and nothing on the row or in `automation_runs` said so —
 * `releaseUnearnedClaim()` nulls the only field on the run row that names the
 * reply, on exactly the attempts that did not publish. So the ladder ran again
 * against a row that looked untouched and asked Google to publish the same text
 * under the tenant's name a second and a third time.
 *
 * ✅ **THE SPLIT IS `GbpRequestFailed::$status`.** A status is a response the
 * vendor returned, so the round trip completed and the next attempt is a first
 * attempt. A status of `0` is `unreachable()` — nothing came back to classify —
 * and it is recorded on the row through `ReviewReplies::markPublishUnconfirmed()`,
 * which refuses every other kind of failure. The ladder still runs; what it
 * meets on the next attempt is a guard immediately before the vendor call.
 *
 * ⚠️ **WHAT THAT COSTS, SAID PLAINLY**: a connection this application never
 * managed to open is recorded the same way, because Laravel raises one
 * `ConnectionException` for a refused connection and for a response that never
 * finished arriving, and `ZernioGbpClient` discards which. So a reply that
 * genuinely never left is left for the owner to approve again rather than
 * retried. That is the deliberate direction — `AutopilotJob::claimIsSpent()`'s
 * own ruling, *"losing a send is recoverable; sending twice is not"* — and
 * narrowing it is a one-line change in a client this lane does not own.
 *
 * ⛔ **THE PARAGRAPH ABOVE IS KEPT WORD FOR WORD AND STOPPED BEING TRUE ON
 * 2026-08-21 (7000–7004).** The lane that owns the client made the change it
 * describes. `ZernioGbpClient` no longer discards which: it reads the libcurl
 * code and builds `GbpRequestFailed::neverSent()` for **6**
 * (`COULDNT_RESOLVE_HOST`) and **7** (`COULDNT_CONNECT`), the two the artefact
 * establishes never left the process. **That is the only class of failure whose
 * behaviour here changes**, and for it the ladder now runs a real second attempt
 * instead of meeting a guard.
 *
 * ⛔ **EVERYTHING ELSE KEEPS TODAY'S BEHAVIOUR, DELIBERATELY AND BY DEFAULT.** A
 * timeout, a `GOT_NOTHING`, a TLS handshake failure, a code the client cannot
 * read, an exception with no libcurl code at all — every one of them still
 * carries `mayHaveBeenSent === true` and is still recorded through
 * `markPublishUnconfirmed()`. `GbpRequestFailed`'s constructor defaults the flag
 * to `true`, so the safe answer is what a caller gets by not thinking about it.
 * **The ruling this paragraph quotes is unchanged** — it is now applied to the
 * failures it was actually written about.
 *
 * ⚠️ **AND THE ARM THIS MAKES REACHABLE IS A REAL ARM WITH A REAL COST.** A
 * never-sent failure now writes nothing to the row, so no owner item is filed
 * and `ReplyPublicationStatus` renders it exactly as it renders a reply whose
 * job has not run yet (6528). That is the same trade the vendor-answered arm
 * below already makes and for the same reason: the retry usually succeeds, and
 * an owner item filed for a DNS blip is an action somebody must take while the
 * ladder quietly finishes behind it.
 *
 * ⛔ **AND THE GUARD BELOW IS NO LONGER THE END OF THE ROAD, WHICH IT WAS UNTIL
 * 2026-08-21 (6955, closed at 7120–7139).** `execute()` refuses the vendor call
 * while `publish_unconfirmed_at` is set, and until this week **nothing in this
 * application ever unset it except an action somebody took on the reply** — so
 * a reply whose one attempt went unanswered was unpublishable for ever unless
 * the owner pressed *Approve again*, blind, destroying the only record that
 * anybody had been unsure. `ReviewReplies::reconcileUnconfirmedPublication()`
 * now clears it when the provider reports **no owner reply on that review**, so
 * the next attempt this job makes is against a listing something actually read.
 * ⚠️ **It clears and does not re-dispatch**, which is 7126's refusal and is
 * unchanged. ⛔ **THE CLAUSE AFTER IT — *"nothing brings this job back on its
 * own"* — STOPPED BEING TRUE ON 2026-08-28 AND IS KEPT BECAUSE IT IS WHAT THE
 * PARAGRAPHS AROUND IT WERE WRITTEN AGAINST** (11040).
 * `reviews:retry-stranded-replies` brings this job back, once per approved
 * reply per owner decision, for rows about which *"this is not on Google"* is
 * provable — which **excludes every row this guard is about**: a reply carrying
 * `publish_unconfirmed_at` is refused by the sweep's own query before it ever
 * reaches this method, so the reconciler clearing the column is still what puts
 * such a row back in play and the owner is still the one who decides. ⚠️ **And
 * that is what bounds 7126's loop at one**: a swept attempt that goes unanswered
 * re-stamps the column, the next provider report clears it, and the claim is
 * already spent — so the reply waits for a person rather than for another
 * sweep.
 *
 * ⚠️ **THIS DOCBLOCK NARRATES A FOUR-CASE LADDER AND THERE ARE FIVE — CORRECTED
 * 2026-08-21 (6954, 7018).** Nothing above is wrong on its own; it simply
 * describes a smaller enum than exists. `ReplyPublicationState` gained
 * **`PublishUnconfirmed`**, and it sits **first in the ladder**, above
 * `PublishingOff`: the other four sentences all end by asserting the reply is
 * not on the listing, and for a row `ReviewReplies::markPublishUnconfirmed()`
 * has stamped, that clause is the unsupported one. ⚠️ **Two lanes wrote the two
 * halves of this in the same wave and neither could edit the other's file** —
 * lane A the render, lane D this job's classification — which is why the
 * correction arrives from the integrator rather than from either.
 *
 * ⚠️ **AND THERE ARE SIX SINCE 2026-08-28, WHICH IS THE SECOND TIME THIS
 * PARAGRAPH HAS BEEN OVERTAKEN** (11203). `ReplyPublicationState::NotEntitled`
 * is the sixth and it sits **second**, under `PublishingOff` and above
 * `NotConnected`; {@see self::canExecute()}'s `not_entitled` arm is in the same
 * place for the same reason, so the run row and the card name one blocker
 * rather than two. ⚠️ **The count is deliberately restated rather than removed**
 * — a paragraph that stops carrying a number stops being falsifiable, and being
 * overtaken twice is what makes it worth keeping visible.
 */
final class PostReplyJob extends AutopilotJob
{
    /**
     * Why the full path was refused, carried from `canExecute()` to `handoff()`.
     *
     * The pattern `SyncGoogleReviewsJob` already uses: the two methods run on
     * the same instance, and re-deriving the reason in `handoff()` would mean
     * two answers that can disagree about one refusal.
     */
    private string $handoffReason = 'unknown';

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $replyId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'reviews.post_reply';
    }

    protected function idempotencyKey(): string
    {
        return 'post-reply:'.$this->replyId;
    }

    /**
     * Earned only when a row actually reaches `posted`. A handoff must release
     * so a later day with a working connection can re-claim (351's shape).
     */
    protected function claimIsSpent(): bool
    {
        $reply = app(ReviewReplies::class)->find($this->replyId);

        return $reply !== null && $reply->status === ReplyStatus::Posted;
    }

    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    /**
     * What the run row carries about the thing it acted on (6680).
     *
     * ⚠️ **WITHOUT THE REPLY ID, `automation_runs` CANNOT SAY WHICH REPLY A RUN
     * WAS ABOUT, AND THAT IS THE WHOLE OF 6528's UNRECONSTRUCTABILITY.** The base
     * class returns a business id and a location id, both of which every reply at
     * that location shares. The one field that named the row was
     * `idempotency_key` — `post-reply:N` — and
     * {@see AutopilotJob::releaseUnearnedClaim()} **nulls it on every
     * attempt that did not publish**, which is precisely the attempt somebody
     * would later need to trace. So the field that identified the reply was
     * erased by exactly the outcomes worth investigating, and three failed runs
     * against three different replies were indistinguishable from three against
     * one.
     *
     * `AnalyzeReviewJob` carries `review_id` here for the same reason and the
     * base class's own docblock names this case: *"which is not answerable from a
     * business id and a location id"*.
     *
     * ⚠️ **IT IS AN ID, NOT CONTENT.** The reply text is customer-facing prose
     * written under a tenant's name and never belongs in a run row; the review's
     * provider id is Google's opaque string and is not needed to find the reply.
     *
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        return parent::input() + ['reply_id' => $this->replyId];
    }

    /**
     * The record an attempt leaves when it did not publish.
     *
     * ⚠️ **A HELPER RATHER THAN A LITERAL PER ARM, BECAUSE THE DEFECT 6528 FOUND
     * WAS AN ARM THAT FORGOT.** Every non-publishing return in this class goes
     * through here, so the reply id cannot be present on the arms somebody
     * remembered and missing from the ones they did not — which is the state this
     * job was actually in.
     *
     * ⛔ **NOTHING HERE MAY IMPLY A PUBLICATION** (2332). `outcome` is what
     * happened to the attempt and never a claim about the listing: `refused` is
     * this application declining to try, `unavailable` is a precondition that was
     * not met, `handoff` is the no-GBP path, and `failed` is the provider
     * declining. None of the four asserts that anything reached Google, and
     * `posted` — the only one that does — is built inline in {@see self::execute()}
     * beside the receipt that justifies it.
     *
     * ⚠️ **AND `unavailable` NOW CARRIES A REASON THAT IS NOT A PRECONDITION**
     * (6822). `publish_unconfirmed` means an earlier attempt reached the vendor
     * and was never answered, so this one refused to ask again — the *outcome*
     * is still honest under the rule above, because it asserts nothing about the
     * listing in either direction, which is the exact state of our knowledge.
     * It is the one reason in this class that says *"we do not know"* rather
     * than *"this did not happen"*, and that distinction is the slice.
     *
     * @return array<string, mixed>
     */
    private function unpublished(string $outcome, string $reason): array
    {
        return [
            'outcome' => $outcome,
            'reason' => $reason,
            'reply_id' => $this->replyId,
        ];
    }

    /**
     * ⚠️ THE APPROVAL CHECK IS FIRST AND THAT ORDERING IS THE POINT (398).
     *
     * It used to sit above a hard `return false` for the missing vendor
     * endpoint, which meant the approval gate could not be falsified through
     * this method at all — something downstream always refused first. The
     * vendor line is gone, so this is now the check that actually decides, and
     * every branch below it is reachable.
     *
     * `replies.status = suggested` is an *undecided* AI draft; publishing one
     * is publishing text nobody read, under the tenant's name, on their public
     * listing. The gate therefore asks `ReviewReplies::isPublishable()` — a
     * predicate over the row — rather than inferring permission from
     * `autopilot_settings`, which answers a different question and can change
     * between generation and posting.
     *
     * ⚠️ AND `isPublishable()` RE-READS THE REVIEW'S RATING FOR A ROW APPROVED
     * BY CONFIGURATION (1855). The reviewer can edit their stars after the
     * draft was approved, and ingest updates the rating without dispatching
     * anything — so the floor is asked again here, at the moment of publishing,
     * rather than trusted from generation time. An owner-approved reply is
     * untouched by that: it is their decision on their own listing (1730).
     *
     * ⚠️ AND THE DUPLICATE IN `execute()` STAYS NOW THAT THIS PATH IS LIVE. It
     * was kept when both checks returned false and cost nothing; it earns its
     * place today, because the two run either side of a queue boundary and the
     * cost of the gap is a public post.
     */
    protected function canExecute(): bool
    {
        $replies = app(ReviewReplies::class);
        $reply = $replies->find($this->replyId);

        if ($reply === null) {
            $this->handoffReason = 'reply_missing';

            return false;
        }

        if (! $replies->isPublishable($reply)) {
            // ⚠️ TWO REASONS, BECAUSE `automation_runs` IS THE ONLY TRACE (1855).
            // A row that was auto-approved and has since stopped clearing the
            // rating floor — the reviewer edited their stars down — is not the
            // same event as a draft nobody approved, and recording both as
            // "not_approved" is how the second one would be read as the first.
            $this->handoffReason = $replies->wasAutoApproved($reply) ? 'auto_approval_lapsed' : 'not_approved';

            return false;
        }

        if (app(DefaultsRegistry::class)->value('gbp.zernio_enabled') !== true) {
            $this->handoffReason = 'integration_disabled';

            return false;
        }

        // ⛔ **THE ACCOUNT, AND NOTHING ELSE IN THIS CLASS ASKS ABOUT IT**
        // (11200, 11203). Every other arm here is a fact about the reply, the
        // integration or the connection; this is the one that asks whether this
        // platform still works for the person whose name goes on the listing.
        // Until 2026-08-28 nothing on the reply path asked it at all, so a
        // tenant whose recorded `ends_at` had been and gone — `status` parked at
        // `active` for ever by `applyAuthorizeNetSubscription()`'s paid-term arm
        // (2748, 8960–8979) — was neither suspended nor paused, cleared every
        // gate, and had replies published under its business name on its public
        // Google listing autonomously.
        //
        // ⚠️ **IT IS `Subscriptions::isEntitled()` AND NOT A NEW POLICY.**
        // `pending_checkout`, `trialing` and `past_due` are all entitled on
        // purpose, so this refuses nobody mid-trial and nobody mid-retry:
        // *"what a delinquent tenant loses and when"* is the owner's and is not
        // decided here. What it refuses is `canceled`, `incomplete`, a no-card
        // trial that ran out, and an account whose own recorded end has passed.
        //
        // ⛔ **IT IS ASKED HERE RATHER THAN IN `ReviewReplies::isPublishable()`,
        // AND THAT IS A MECHANISM ARGUMENT RATHER THAN A TASTE ONE** (11201).
        // `markPosted()` asks `isPublishable()` **after** the vendor has
        // accepted the reply and **throws** when it is false — an ordering 6821
        // records and 6820 built. A predicate that can flip on a wall clock,
        // asked at that point, turns a successful publication into a thrown job,
        // a queue retry and a second post of the same text under somebody else's
        // name. Entitlement is a fact about the account and a clock; that method
        // asks the *row* and must go on doing so.
        //
        // ⚠️ **BELOW THE REGISTRY SWITCH AND ABOVE THE CONNECTION, WHICH IS THE
        // CARD'S LADDER ORDER** — `ReplyPublicationState::NotEntitled`
        // sits in the same place, so the run row and the sentence the owner
        // reads name the same blocker.
        if (! $this->planIsRunning()) {
            $this->handoffReason = 'not_entitled';

            return false;
        }

        $location = $this->location();

        if (! $location instanceof Location) {
            $this->handoffReason = 'location_missing';

            return false;
        }

        $connection = app(GbpConnections::class)->forLocation($location);

        if ($connection === null || ! $connection->isUsable()) {
            $this->handoffReason = 'not_connected';

            return false;
        }

        return true;
    }

    /**
     * Whether this job's business still has a plan that entitles it.
     *
     * ⚠️ **THE TENANT IS ALREADY ESTABLISHED** — {@see AutopilotJob::handle()}
     * calls `Tenancy::set($this->businessId)` before anything else — so this is
     * an ordinary scoped read with RLS beneath it rather than a lookup that
     * could reach another tenant's row.
     *
     * ⚠️ **A MISSING BUSINESS ANSWERS `true`, WHICH IS THE FAIL-OPEN DIRECTION
     * AND IS DELIBERATE.** It is unreachable — the job would not have a tenant
     * — and refusing there would report a lapsed plan for what is really a
     * missing row, which is the conflation 1855 split apart on the arm above.
     * `Subscriptions::isEntitled()` fails open on a missing subscription for
     * 588's reason and this matches it rather than inventing a stricter answer
     * one layer up.
     */
    private function planIsRunning(): bool
    {
        $business = Business::query()->find($this->businessId);

        return ! $business instanceof Business
            || app(Subscriptions::class)->isEntitled($business);
    }

    /**
     * ⛔ **THE HEADLINE BELOW IS FALSE FOR TWO OF THE ARMS IT SPEAKS FOR AND
     * IS CORRECTED HERE — 2026-08-28 (11360).** It is kept word for
     * word, on 4368's rule, because it is the evidence: it is **true** of every
     * arm whose subject is the reply, the location or the connection, and
     * **false** of both arms whose subject is the platform switch or the
     * account. A reader spot-checking any of the first set found it correct and
     * stopped. ⚠️ **A comment true of a strict subset is confirmed by every
     * reader who checks the case it names**, which is why it survived being
     * widened twice: `gbp.zernio_enabled` was already outside it when 6681
     * rewrote the reason, and {@see self::canExecute()}'s `not_entitled` arm
     * joined it on 2026-08-28 without the sentence moving.
     *
     * ⚠️ **THE SET IS STATED BY SUBJECT RATHER THAN AS A COUNT, DELIBERATELY**
     * (8861). Two counts on this class went stale inside a week; a new arm added
     * to `canExecute()` is in the *unduplicated* set until somebody argues it
     * into the other one, which is the reading that stays true without an edit.
     *
     * ⚠️ EVERY GUARD `canExecute()` MADE IS MADE AGAIN, AND NONE OF THEM IS
     * REDUNDANT — BUT THE REASON WRITTEN HERE WAS WRONG AND IS CORRECTED (6681).
     *
     * It said *"the two methods run either side of a queue boundary — a job can
     * sit in the queue while an owner disconnects Google"*. **They do not.**
     * {@see AutopilotJob::handle()} calls `canExecute()` and then
     * `execute()` on two adjacent statements, on the same instance, on the
     * worker. The queue boundary is between *dispatch* and `handle()`, and both
     * methods sit on the same side of it. A reader checking whether a duplicate
     * guard earns its place was being told it covers a window that does not
     * exist — `CLAUDE.md` 314–316, in the docblock of a job that publishes text
     * under somebody else's name.
     *
     * ⚠️ **THE CONCLUSION SURVIVES THE CORRECTION AND THE GUARDS STAY** (398).
     * What they actually cover is narrower and still real: a **concurrent
     * writer** between two adjacent reads — another worker, a webhook, an
     * operator — and a **second caller** of this method that has made no checks
     * at all. Deleting a guard because the stated window was imaginary is how
     * the inner one stops being falsifiable, and the thing on the other side is
     * a public post.
     *
     * ⚠️ **AND THE WINDOW BEING THAT NARROW IS WHY EVERY REFUSAL HERE MUST
     * RECORD** (6682). An arm that fires this rarely is an arm nobody will ever
     * catch in the act, so the run row is the only account of it that will ever
     * exist.
     *
     * ## ✅ The platform switch IS re-asked, and not here — 11360
     *
     * `ZernioGbpClient::assertUsable()` calls its own `assertEnabled()`, which
     * reads `gbp.zernio_enabled`, as the **last statement before the HTTP
     * request** — several queries downstream of anything this method could do,
     * on the far side of every read below. So
     * the property holds for that arm and the headline was wrong about *where*,
     * which is the harder kind of wrong: a reader who goes looking in this
     * method finds nothing and has to decide whether the sentence or the code is
     * the mistake. The `clientRefused` arm below already records
     * `GbpRequestFailed::disabled()` arriving from exactly that window (9145).
     *
     * ⛔ **A DUPLICATE AT THE TOP OF THIS METHOD WOULD BE STRICTLY WEAKER AND
     * WOULD MAKE THE OUTCOME QUIETER.** The client's refusal is rethrown, spends
     * the ladder and lands in `failed_jobs` naming its own reason, where
     * `OperatorAlertKind::FailedJobSpike` is already watching; a return from
     * here writes nothing to the row, files no owner item and closes the run
     * `Succeeded`. **Adding it would move the read earlier, away from the act,
     * and swap a loud outcome for a silent one** — the tidy-looking change that
     * makes an incident invisible.
     *
     * ## ⛔ The plan is asked ONCE on this worker, and that is a decision — 11361
     *
     * Nothing between {@see self::canExecute()}'s `planIsRunning()` and the
     * vendor call re-asks entitlement: `ReviewReplies::isPublishable()` asks the
     * row, and `markPosted()` asks that same predicate afterwards.
     *
     * ⚠️ **THE ONCE-VERSUS-TWICE RULE DOES NOT REFUSE A SECOND ASK HERE, AND
     * SAYING SO IS THE POINT.** That rule — *a guard asked once before the act
     * may absorb any condition; a guard asked twice with the act in between may
     * only absorb conditions that cannot change while the act is happening* —
     * is what 11201 used to keep entitlement out of `markPosted()`, where a
     * false answer **throws** after Google has accepted and the retry publishes
     * a second time. **The top of this method is on the other side of that
     * line**: no act has happened, and every refusal here is a recorded return
     * rather than a throw. **The reason not to add it is therefore its own, and
     * a lane that cites 11201 for it has cited the wrong argument.**
     *
     * ⛔ **THE REASON IS THAT IT WOULD BUY A WINDOW AND COST A SECOND RECORD
     * FOR ONE BLOCKER.** {@see AutopilotJob::handle()} calls `canExecute()` and
     * this method on two adjacent statements, so what a second read covers is a
     * concurrent writer inside one process — while the population the arm was
     * built for (8960–8979: `status` parked at `active` with an `ends_at` long
     * past) is measured in months and is caught whole by the first read. Against
     * that, `canExecute()` refusing writes *"This account has no running plan"*
     * onto the row through `markPostingUnavailable()`, closes the run
     * `HandedOff` with `owner_action: start_plan`, and lights
     * `ReplyPublicationState::NotEntitled` on the card; a refusal from here
     * writes nothing to the row, files no owner action and closes the run
     * `Succeeded`. **One condition would leave two different records depending
     * on which millisecond it changed in**, which is the conflation 1855 split
     * apart and 6528 measured.
     *
     * ⚠️ **AND `planIsRunning()` FAILS OPEN ON A MISSING BUSINESS BY DESIGN**,
     * so a second ask of it answers `true` in the one state where the two reads
     * could differ for a reason other than the clock.
     *
     * ✅ **THE SECOND ASK THAT EARNS ITS PLACE ALREADY EXISTS AND IS SOMEWHERE
     * ELSE.** `Console\Commands\RetryStrandedReplies::sweepBusiness()` asks
     * entitlement as well as this class, and its written reason is not
     * double-posting at all: **every gate the job makes that the sweep does not
     * is a window in which a dispatch spends a one-shot claim on an attempt that
     * never happens.** That is what a second entitlement read is for in this
     * system, and it is made where the claim is spent.
     *
     * ⚠️ **IF A LATER SLICE ADDS IT ANYWAY**, the proof owed is that a
     * mid-act change of entitlement cannot produce a second public post — so it
     * goes above the vendor call and never in `markPosted()` (6820, 6821) — and
     * `Feature\ReplyGuardDuplicationTest` is the test it must argue past rather
     * than edit.
     *
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        $replies = app(ReviewReplies::class);
        $reply = $replies->find($this->replyId);

        if ($reply === null || ! $replies->isPublishable($reply)) {
            return $this->unpublished('refused', 'not_approved');
        }

        $review = $reply->review()->first();

        // `replies.review_id` cascades on delete, so a reply cannot outlive its
        // review and the `$reply === null` branch above answers that case
        // already. This narrows the type rather than adding a second refusal —
        // 1933's distinction, kept.
        if ($review === null) {
            return $this->unpublished('refused', 'review_missing');
        }

        $externalId = $review->google_review_id;

        if (! is_string($externalId) || $externalId === '') {
            // A Google review with no provider id cannot be replied to, and
            // there is no id to guess. Not a vendor failure and not retryable.
            return $this->unpublished('refused', 'review_not_external');
        }

        $location = $this->location();

        if (! $location instanceof Location) {
            return $this->unpublished('unavailable', 'location_missing');
        }

        $connections = app(GbpConnections::class);
        $connection = $connections->forLocation($location);

        // ⚠️ `account_ref === null` IS SUBSUMED BY `isUsable()` AND IS KEPT FOR
        // THE TYPE, NOT FOR THE CHECK (6683). `GbpConnection::isUsable()` already
        // requires a non-null ref, so this disjunct can never be the one that
        // fires — which makes it look like the extra condition that renders this
        // guard different from `canExecute()`'s, and it is not: the two ask the
        // identical question. It stays because `replyToReview()` takes a
        // `string` and static analysis has no way to narrow the property across
        // a method call. Deleting it reddens `composer stan`, not the suite,
        // which is the sort of removal that gets made and then reverted.
        if ($connection === null || ! $connection->isUsable() || $connection->account_ref === null) {
            return $this->unpublished('unavailable', 'not_connected');
        }

        // ⛔ THE RE-CHECK 6525 ASKED FOR, IMMEDIATELY BEFORE THE VENDOR CALL AND
        // NOT AT THE TOP OF THIS METHOD (6823). An earlier attempt that reached
        // Zernio and was never answered wrote `publish_unconfirmed_at`, and the
        // reply may be live on the listing right now — nothing in this
        // application knows. Asking again would publish the same text a second
        // time under somebody else's name to settle a question this application
        // cannot answer, so it refuses and leaves it to the owner, who is the
        // one party who can look. `ReviewReplies::approve()` clears the column,
        // so *Approve again* — the only retry this product has (6524) — is a
        // full attempt rather than a refusal.
        //
        // ⚠️ IT ASKS THE SERVICE, WHICH ASKS THE DATABASE. `$reply` was loaded
        // at the top of this method, three queries ago, and a stale instance is
        // exactly what the second post rides in on.
        if ($replies->publishIsUnconfirmed($reply)) {
            return $this->unpublished('unavailable', 'publish_unconfirmed');
        }

        try {
            $receipt = $connections->clientFor($connection)->replyToReview(
                $connection->account_ref,
                $externalId,
                $reply->text,
            );
        } catch (GbpRequestFailed $e) {
            if ($e->disconnected) {
                // The tenant revoked us. Write the connection down so the
                // dashboard stops claiming it works, tell the owner why their
                // reply is still sitting there, and do not retry — a backoff
                // ladder against a revoked grant is three more failures.
                $connections->refreshHealth($connection, 'system:post_reply');

                $replies->markPostingUnavailable(
                    $reply,
                    'Google is no longer connected for this location, so this reply could not be published.',
                );

                return $this->unpublished('unavailable', 'connection_revoked');
            }

            if ($e->retryable) {
                // ⛔ TWO KINDS OF RETRYABLE, AND ONLY ONE OF THEM MAY BE RETRIED
                // (6525, 6822). A status of `0` is `GbpRequestFailed::unreachable()`
                // — nothing came back at all — and it covers both "we never
                // sent it" and "Zernio took it and the response was lost".
                // Those two are indistinguishable from here, and the queue
                // cannot tell them apart either: it just runs this method again
                // and asks Google to publish the same text under the tenant's
                // name. **Recording the attempt is what makes the retry
                // survivable**, and it is recorded through a method that cannot
                // be reached with any other kind of failure.
                //
                // ⚠️ AND THE THROW SURVIVES, WHICH IS THE HALF THAT MATTERS.
                // The ladder still runs; the guard above is what it meets. That
                // ordering is deliberate — a guard nothing ever reaches is 398's
                // shape, and this one is on the live path on every attempt after
                // an unanswered one, which is where it can be driven red.
                //
                // ⛔ THREE KINDS SINCE 7000, AND THE SENTENCE ABOUT
                // INDISTINGUISHABILITY IS KEPT BECAUSE IT IS WHAT MADE THE
                // DEFECT FINDABLE. `ZernioGbpClient` now reads the libcurl code,
                // so `mayHaveBeenSent` is `false` for exactly the two that
                // establish the request never left. That reply was never
                // published, so there is nothing to be unsure about and nothing
                // to guard: the row stays clean and the ladder runs a genuine
                // second attempt.
                //
                // ⚠️ THE FLAG IS ASKED HERE AS WELL AS INSIDE THE WRITER, AND
                // NEITHER IS REDUNDANT (398). `markPublishUnconfirmed()` refuses
                // a never-sent failure with an `InvalidArgumentException` — so
                // without this condition the job would die on the wrong
                // exception type and the run row would name a programming error
                // instead of a network one. The writer's guard is what stops a
                // *second* caller recording one; this one is what keeps the
                // outcome honest for the caller that exists.
                if ($e->status === 0 && $e->mayHaveBeenSent) {
                    $replies->markPublishUnconfirmed($reply, $e);
                }

                // ⚠️ NOTHING IS WRITTEN ON THE ROW FOR A FAILURE THE VENDOR
                // ANSWERED. The queue brings the job back, and an error message
                // filed for a 429 or a 500 would sit in the owner's feed as an
                // action they need to take while the retry quietly succeeds
                // behind it. A response is a round trip that completed, so the
                // next attempt is a first attempt and may publish freely.
                throw $e;
            }

            // ⛔ THE CLIENT REFUSED TO RUN, SO NOBODY DECLINED ANYTHING (9145).
            // `clientRefused` is not the vendor's verdict: it is `disabled()`
            // when `gbp.zernio_enabled` is off, or `unconfigured()` when the
            // platform API key is unset. The arm below writes *"Google would not accept
            // this reply"* into the owner's feed and stamps
            // `provider_declined_at`, which would be a false statement about a
            // third party on a reply Google has never seen, with the remedy
            // pointed at the tenant when it is ours.
            //
            // ⚠️ THE COMMENT BELOW SAID THIS COULD NOT HAPPEN — "which only a
            // response the vendor returned can produce" — AND `disabled()`
            // ALREADY COULD, on a flag flipped between `canExecute()` and this
            // line. It is kept, corrected, because the claim is what stopped
            // anybody looking.
            //
            // ⛔ RETHROWN RATHER THAN RETURNED, DELIBERATELY, EVEN THOUGH IT IS
            // NOT RETRYABLE. There is no owner action and nothing to write on
            // the row, so a quiet `unavailable` return would leave an approved
            // reply unpublished with no instrument anywhere pointing at it —
            // which is the invisibility this whole slice is about. The throw
            // spends the ladder and lands in `failed_jobs` naming
            // `platform_credential_missing`, where `OperatorAlertKind::FailedJobSpike`
            // is already watching.
            if ($e->clientRefused) {
                throw $e;
            }

            // ⛔ THE ONE ARM THAT MAY SPEAK FOR GOOGLE, AND IT HANDS OVER THE
            // EVIDENCE RATHER THAN A SENTENCE (6720, 6722). `markDeclinedByProvider()`
            // takes the failure itself — which only a response the vendor
            // returned can produce — writes `provider_declined_at`, and refuses
            // a retryable or a `disconnected` one. Both are already ruled out
            // above; passing `$e` rather than re-deriving that is what makes the
            // refusal a real check instead of a restatement.
            $replies->markDeclinedByProvider($reply, $e);

            return $this->unpublished('failed', $e->reason);
        }

        $replies->markPosted($reply, $receipt, 'system:post_reply');

        return [
            'outcome' => 'posted',
            'review_id' => (int) $review->id,
            'reply_id' => (int) $reply->id,
            // ⚠️ The provider's own word, not ours. Google moderates replies and
            // this relay carries no verdict back, so `posted` is an
            // acknowledgement rather than a sighting on the listing (2332).
            'provider_status' => $receipt->providerStatus,
        ];
    }

    /**
     * ⚠️ AN UNDECIDED DRAFT IS NOT A HANDOFF AND MUST NOT LOOK LIKE ONE. Writing
     * "posting is not available yet" onto a reply nobody approved would blame
     * the vendor for a gate this application closed on purpose, and would put an
     * OwnerActionNeeded item in the feed for a card already sitting in the
     * owner's queue.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        $replies = app(ReviewReplies::class);
        $reply = $replies->find($this->replyId);

        if ($reply === null) {
            return $this->unpublished('handoff', 'reply_missing');
        }

        if (! $replies->isPublishable($reply)) {
            return $this->unpublished(
                'handoff',
                $replies->wasAutoApproved($reply) ? 'auto_approval_lapsed' : 'not_approved',
            );
        }

        // ⛔ **A THIRD SENTENCE, BECAUSE THE FALLBACK IS FALSE FOR THIS ARM**
        // (11203). *"reconnect Google or ask us to switch it on"* points the
        // remedy at the integration when the blocker is the plan — 6685's shape
        // exactly, a stored string asserting the wrong cause, on the one arm
        // where the person reading it has stopped paying us and would be told to
        // go and fix something that is already fine.
        //
        // ⚠️ **`error_message` IS STILL NEVER RENDERED** (1748) and this string
        // is still machinery. The sentence the owner actually reads is
        // `ReplyPublicationState::NotEntitled`'s, derived live
        // on the card, and the two are deliberately not the same string: this
        // one is for whoever debugs the row.
        $replies->markPostingUnavailable($reply, match ($this->handoffReason) {
            'not_connected' => 'Google is not connected for this location, so this reply could not be published.',
            'not_entitled' => 'This account has no running plan, so this reply was not published.',
            default => 'Google reply posting is not available yet — reconnect Google or ask us to switch it on.',
        });

        return $this->unpublished(
            'handoff',
            $this->handoffReason === 'unknown' ? 'posting_unavailable' : $this->handoffReason,
        ) + [
            // ⚠️ NOTHING IN `app/` BRANCHES ON THIS AND `connect_google` HAS NO
            // READER EITHER — it is the run row's account of what the owner can
            // do, and leaving this arm at `none` would say there is nothing,
            // which contradicts the card's own next step.
            'owner_action' => match ($this->handoffReason) {
                'not_connected' => 'connect_google',
                'not_entitled' => 'start_plan',
                default => 'none',
            },
        ];
    }
}

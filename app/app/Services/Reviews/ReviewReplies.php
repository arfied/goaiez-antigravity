<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AutopilotActionType;
use App\Enums\ReplyPublicationState;
use App\Enums\ReplyStatus;
use App\Enums\ReviewSource;
use App\Events\ReplyApproved;
use App\Exceptions\GbpRequestFailed;
use App\Exceptions\ReplyGuardrailRefused;
use App\Jobs\Reviews\PostReplyJob;
use App\Models\AutopilotSettings;
use App\Models\Business;
use App\Models\Location;
use App\Models\Reply;
use App\Models\Review;
use App\Modules\X220\Actions\PromptResolveAction;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Gbp\GbpReplyReceipt;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only writer of `replies` (`17` GBP-03 / GBP-05, row 3 slice J).
 *
 * A `suggested` reply is the approval queue. Approving edits the text, moves the
 * row to `approved`, and asks PostReplyJob to publish.
 *
 * ✅ **POSTING NOW REACHES GOOGLE, AND THIS PARAGRAPH USED TO SAY IT COULD NOT**
 * (decision 2330). The vendor's create-reply endpoint was confirmed against
 * their raw OpenAPI document on 2026-08-11, so `PostReplyJob` publishes instead
 * of always handing off. ⚠️ **The handoff path is not a stub that came out with
 * it** — `29` §2 rule 44 requires the whole review engine to run with no GBP
 * access at all, so the honest "we cannot publish this, here is the text"
 * outcome stays and is what a disconnected tenant, a disabled integration or an
 * unapproved draft still gets.
 *
 * ⚠️ **AND `posted` MEANS "THE PROVIDER ACCEPTED IT", NOT "IT IS ON THE
 * LISTING"** (2332). Google moderates replies — `PENDING`, `REJECTED`,
 * `APPROVED` — and the relay carries no verdict back. What the vendor did say
 * is kept on the audit entry rather than dressed up as a state of ours.
 *
 * ⚠️ `approved` IS A DISTINCT STATUS AND THAT IS THE POINT (1724). It used to be
 * `suggested` — the value an untouched AI draft carries — so an approval was
 * indistinguishable from a machine's guess: the queue never cleared, the badge
 * never decremented, and the day PostReplyJob's endpoint arrives it would have
 * published every draft nobody had read. Nothing publishes without
 * `isPublishable()` returning true, and that predicate asks the *row*, never the
 * settings.
 *
 * ⚠️ `approval_requests` IS NOT USED. That table is CONFIRM's store (rule 38 —
 * GBP core fields, spend, first campaign send; rule 37 is the three *patterns*,
 * and this comment cited it for eleven months, as do four other places). A reply
 * draft is not CONFIRM, and the SMS approval loop is unbuilt. Filing replies
 * there would reuse a legal mechanism for a content queue.
 *
 * ⛔ **AND THE CLAUSE THAT USED TO END THAT SENTENCE — *"and leave the CONFIRM
 * readers lying"* — WAS FALSE WHEN IT WAS WRITTEN AND IS CORRECTED 2026-08-23
 * (8567).** **There are no CONFIRM readers.** `approval_requests` has no writer
 * and no reader anywhere in `app/`, `require_confirm_for` has none, and nothing
 * consults `expires_at` or `auto_action_on_expiry` — so filing a reply draft
 * there would have misled nobody, because nobody is reading. ⚠️ **The refusal
 * survives its own argument and is not being re-opened**: `replies.status =
 * suggested` is the queue (1687), and reusing the store CONFIRM will need is a
 * poor trade whether or not a reader exists today.
 *
 * ⚠️ **THIS COMMENT IS THE ONLY OCCURRENCE OF `approval_requests` OUTSIDE
 * `app/Models`, WHICH IS WHY IT IS CORRECTED RATHER THAN DELETED.** It is what
 * makes the table read as referenced to every grep run over this tree — 8555's
 * shape, and the reason six recordings of that absence never produced a seventh
 * reader who noticed. `tests/Feature/ConfirmIsUnbuiltTest.php` asserts this
 * paragraph is **present**, so removing it reddens the build and says why.
 */
final class ReviewReplies
{
    /**
     * The rating below which no configuration may auto-publish (1725).
     *
     * ⚠️ THIS IS A FLOOR, NOT A DEFAULT, AND IT IS WHY IT IS A CONSTANT RATHER
     * THAN A COLUMN. `29` §12.1 makes *"low-star reviews never auto-post"* a
     * build-failing test, and a rule that lives only in a settable number is one
     * an ops admin can switch off by typing `1` — which is exactly how
     * `reply_auto_post_min` failed open when it was being written as minutes.
     * `reply_auto_post_min` may tighten this and can never loosen it.
     */
    public const int AUTO_POST_RATING_FLOOR = 4;

    /** The actor recorded when configuration, rather than a person, decided. */
    public const string AUTO_POST_ACTOR = 'system:auto_post';

    /**
     * The actor recorded when a provider read, rather than a person, settled an
     * unanswered attempt.
     *
     * ⛔ **A CONSTANT AND NEVER A PARAMETER** (7128). Every other actor on this
     * class arrives from its caller, because *who approved this* and *who asked
     * the vendor* are facts only the caller knows. This one is different: the
     * thing being recorded is that **nobody** decided — a scheduled read of a
     * third party's data cleared a publication block — and an actor a caller
     * supplies is one a caller can dress up as a person.
     */
    public const string PUBLISH_RECHECK_ACTOR = 'system:publish_recheck';

    /**
     * The actor recorded when the stranded-reply sweep, rather than a person,
     * asked for a reply to be published again.
     *
     * ⚠️ **ITS OWN WORD RATHER THAN `PUBLISH_RECHECK_ACTOR`.** Those two
     * automated actions read alike on a diff and are opposites on the row: a
     * recheck *clears* a publication block on the strength of something we
     * read, and this one *causes* a publication attempt. An audit trail that
     * spells them the same way cannot answer which of them put text on a
     * listing.
     */
    public const string PUBLISH_RETRY_ACTOR = 'system:publish_retry';

    /**
     * How long an unanswered attempt is given to settle before a provider read
     * counts as evidence against it.
     *
     * ⛔ **THIS IS A GUESS AND IT MUST BE READ AS ONE** (7124). Zernio
     * publishes **no read-after-write semantics of any kind** for
     * `GET /v1/inbox/reviews` — the whole raw `docs.zernio.com/llms-full.txt`
     * corpus (4,177,964 bytes, fetched 2026-08-21) says nothing about how long
     * a reply posted through `POST /v1/inbox/reviews/{reviewId}/reply` takes to
     * appear in the list, and the list response carries an undocumented
     * `meta.lastUpdated` that hints at a cache without describing one. So there
     * is no vendor number to verify this against, and inventing one dressed as
     * a fact would be exactly what `CLAUDE.md` forbids.
     *
     * ⚠️ **WHAT MAKES A GUESS ACCEPTABLE HERE IS THE DIRECTION IT FAILS IN.**
     * Too long and this method does nothing, which is the behaviour of every
     * build before it. Too short and a reply that did land is described to its
     * owner as not having landed — 6958's harm, restored. So it is set well
     * past everything this application can measure: the retry ladder is
     * 60/300/900 seconds (~21 minutes end to end) and the sync runs every
     * fifteen, so an hour is four polls and three ladders.
     *
     * ⚠️ **NOT A REGISTRY KEY** (`CLAUDE.md`: never add a tenant-facing toggle;
     * less support surface). It is not a policy anybody owns — it is the size
     * of our own uncertainty about somebody else's cache, and an Ops row would
     * invite an operator to tune a number neither of us can measure.
     */
    public const int PUBLISH_RECHECK_SETTLE_MINUTES = 60;

    /**
     * How long after approval a reply may first be re-dispatched by the sweep.
     *
     * ⚠️ **DERIVED, NOT GUESSED, AND THE DERIVATION IS THE REASON IT IS NOT
     * `PUBLISH_RECHECK_SETTLE_MINUTES`' SIXTY.** That constant is the size of
     * our uncertainty about somebody else's cache and had no number to check
     * against (7124). This one has one: `AutopilotJob::$tries` is 3 and
     * `backoff()` is a ±25% jittered 60/300/900, so the ladder tops out at
     * 1,575 seconds, and three attempts each capable of burning the worker's
     * own 60-second timeout add 180 more — about 29½ minutes from dispatch.
     * Forty-five is the round figure comfortably past it.
     *
     * ⚠️ **THE COST OF IT BEING TOO SHORT IS A WASTED CLAIM, NEVER A SECOND
     * POST.** A dispatch that races the owner's own in-flight attempt collides
     * on `automation_runs`' unique idempotency key and returns before
     * `execute()`; what is lost is this row's one automatic retry, which the
     * owner's *Approve again* hands back.
     *
     * ⚠️ **NOT A REGISTRY KEY**, on `PUBLISH_RECHECK_SETTLE_MINUTES`' argument
     * exactly: it is arithmetic over two figures on `AutopilotJob`, and an Ops
     * row would invite an operator to tune it out of step with them.
     */
    public const int PUBLISH_RETRY_FLOOR_MINUTES = 45;

    public function __construct(
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
        private readonly ReplyGuardrails $guardrails,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function publishRecheckSettleMinutes(): int
    {
        return $this->registry->int('reviews.publish.recheck_settle_minutes');
    }

    public function publishRetryFloorMinutes(): int
    {
        return $this->registry->int('reviews.publish.retry_floor_minutes');
    }

    /**
     * Every reply still waiting on the owner in the current tenant.
     *
     * ⚠️ `suggested` ONLY. An `approved` row has had its decision taken and must
     * not reappear: it is what the owner just cleared, and re-listing it is how
     * the same reply collects an audit row and a feed item per click.
     *
     * @return Collection<int, Reply>
     */
    public function pendingAcrossTenant(): Collection
    {
        return Reply::query()
            ->with('review')
            ->where('status', ReplyStatus::Suggested)
            ->orderByDesc('id')
            ->get();
    }

    public function pendingCount(): int
    {
        return Reply::query()
            ->where('status', ReplyStatus::Suggested)
            ->count();
    }

    /**
     * Every reply the owner approved that has not reached Google (1738).
     *
     * ⚠️ **THE COMPANION TO `pendingAcrossTenant()`, AND THE PAIR IS THE WHOLE
     * FIX.** 1724 made `Approved` a real state so an owner's decision could be
     * told from a machine's guess, and 1728 stopped `markPostingUnavailable()`
     * touching `status` so a posting outage could not un-approve that decision.
     * Both are right and between them they opened a hole: an `Approved` row is
     * excluded from the queue by the first and kept out of it by the second, so
     * it left the only screen that could show it and appeared on none. What was
     * visible instead was an `OwnerActionNeeded` feed item and an
     * `error_message` on a row nothing renders — 1738, owed since 2026-08-10.
     *
     * ⚠️ **`status` IS THE AUTHORITY AND `posted_at` IS DELIBERATELY NOT IN THE
     * PREDICATE.** `markPosted()` is the only writer of either and it sets both
     * inside one transaction, so a `whereNull('posted_at')` here would be an
     * outer guard that can never fail (398) and would quietly hide a row whose
     * two columns had disagreed — which is the row somebody would most need to
     * see.
     *
     * ⚠️ **NO `Failed` ROWS, BECAUSE NOTHING WRITES THAT STATUS.** `ReplyStatus`
     * carries the case and `recordSuggestion()` will overwrite a `Failed` row,
     * but no writer in `app/` ever produces one: `markPostingUnavailable()` and
     * `markDeclinedByProvider()` both leave `status` alone by 1728's design.
     * Listing a status with no writer would be 272's shape pointed at a query.
     *
     * @return Collection<int, Reply>
     */
    public function awaitingPublicationAcrossTenant(): Collection
    {
        return Reply::query()
            ->with('review')
            ->where('status', ReplyStatus::Approved)
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Approved replies at these locations that nothing has ever put on Google,
     * and that this platform may ask for again on its own (11040).
     *
     * ⛔ **THE POPULATION IS DEFINED BY WHAT CANNOT BE ON THE LISTING, NOT BY
     * WHAT FAILED.** Every reason an approved reply strands — the integration
     * switched off, the location disconnected, the grant revoked, a vendor
     * refusal, a DNS blip, a worker killed mid-call — produces the same
     * `Approved` row, and `awaitingPublicationAcrossTenant()` lists all of them
     * for the owner to read. **A sweep may only re-dispatch the subset about
     * which "this reply is not on Google" is provable**, because a re-dispatch
     * is a second public post under somebody else's name and nobody here can
     * take one back. So the three columns that record a completed round trip
     * are each a refusal:
     *
     *   · `publish_unconfirmed_at` — we asked and were never answered, so the
     *     reply may be live right now (6942). {@see self::publishIsUnconfirmed()}
     *     is `PostReplyJob`'s own guard against exactly this and the sweep must
     *     not walk a row up to it.
     *   · `provider_declined_at` — Google was asked and said no (6720). The same
     *     text asked again is the same answer, and the card already tells the
     *     owner to edit the wording.
     *   · `publish_retry_dispatched_at` — this sweep has already had its one
     *     attempt at this row. **The bound, and the reason it is a column**
     *     (7126).
     *
     * ⛔ **AND THE FOURTH REFUSAL IS NOT A COLUMN AT ALL AND CANNOT BE ONE
     * HERE** — a run this platform's own worker killed mid-flight, which
     * {@see ReplyPublicationStatus::abandonedReplyIds()} answers and 10264
     * establishes is epistemically identical to `publish_unconfirmed_at`. It
     * lives in `automation_runs` rather than on this row, so it is applied by
     * the caller after this query rather than inside it. ⚠️ **That read is
     * durable rather than best-effort**: `AutomationRunRetention::survivorIds()`
     * keeps the newest terminal run of every `(location_id, automation_key,
     * reply_id)` triple **for ever**, so a pruned table cannot hand a killed
     * attempt back to this sweep as a clean row.
     *
     * ⚠️ **THE FLOOR IS ON `approved_at` AND IT IS ARITHMETIC RATHER THAN A
     * GUESS.** `AutopilotJob` retries three times on a jittered 60/300/900
     * ladder — 1,575 seconds at the top of the jitter — and each attempt may
     * burn the worker's own 60-second timeout, so a first dispatch is finished
     * with within about 29½ minutes of approval.
     * {@see $this->publishRetryFloorMinutes()} is comfortably past that. ⛔ **It
     * is not a safety guard and must not be read as one**: a double post is
     * refused by `automation_runs`' unique idempotency key and by
     * `isPublishable()` seeing a `Posted` row, both of which hold at any
     * cadence. What the floor buys is that the sweep does not **spend** its
     * one claim on a dispatch that would collide with the owner's own attempt
     * still in flight.
     *
     * ⚠️ **`ReviewSource::Google` IS BELT AND BRACES AND IS STATED AS SUCH** —
     * `recordSuggestion()` asserts it on the only path that creates a row, so
     * no other kind of reply exists. `ReinviteDeferredReviews`' own words: a
     * sweeper is the easiest place in a codebase to widen a predicate by
     * accident.
     *
     * @param  array<int, int>  $locationIds  Locations with a usable Google connection.
     * @return Collection<int, Reply>
     */
    public function strandedAwaitingPublication(array $locationIds, int $limit): Collection
    {
        if ($locationIds === []) {
            return new Collection;
        }

        return Reply::query()
            ->with('review')
            ->where('status', ReplyStatus::Approved)
            ->whereNull('publish_unconfirmed_at')
            ->whereNull('provider_declined_at')
            ->whereNull('publish_retry_dispatched_at')
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', now()->subMinutes($this->publishRetryFloorMinutes()))
            // ⚠️ A SUBQUERY RATHER THAN `whereHas()`, ON `AutoTopUps`' STATED
            // REASON: the relation's builder is generic, so a
            // `where('source', …)` inside its closure is unverifiable at level
            // 8, while `Review::query()` knows what its own columns are. It
            // carries `BelongsToTenant`'s global scope and the RLS predicate
            // beneath it exactly as the outer query does, so the join cannot
            // widen past this tenant. Both compile to the same `IN (…)`.
            ->whereIn('review_id', Review::query()
                ->whereIn('location_id', $locationIds)
                ->where('source', ReviewSource::Google)
                ->select('id'))
            // Ascending on id: the oldest strandings drain first, and an
            // ascending order raises none of the NULLS-FIRST problems
            // `Architecture\ConventionsTest` exists for.
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Load one reply under the current tenant scope, or null.
     */
    public function find(int $replyId): ?Reply
    {
        return Reply::query()->find($replyId);
    }

    /**
     * The latest reply on each of these reviews, keyed by `review_id`.
     *
     * ⚠️ **THIS EXISTS BECAUSE 1733 REMOVED `Review::replies()` AND `28` §3.7's
     * EXPORT STILL HAS TO READ REPLIES** (2051). The two decisions arrived on
     * different branches and are both right: the relation was a hole straight
     * through 1680's chokepoint, and a tenant's export that silently omitted
     * the replies written under their own name would be an incomplete answer to
     * a subject-access request. `ExportBuilder` merged with `->with('replies')`
     * and Larastan caught it — the relation genuinely no longer exists.
     *
     * ⚠️ **AND THE LINT WOULD NOT HAVE**: it matches `->replies(` with the
     * parenthesis, so the eager-load string `'replies'` and the property read
     * `$review->replies` both pass it. The chokepoint held here because a
     * *type checker* saw what the lint could not, which is worth knowing the
     * next time a pattern-matching lint is called sufficient.
     *
     * Latest by `id` rather than by `created_at`: `created_at` is nullable on
     * this table, and Postgres sorts NULL first on a DESC order — the defect
     * `Architecture\ConventionsTest` exists for.
     *
     * @param  array<int, int>  $reviewIds
     * @return Collection<int, Reply>
     */
    public function latestForReviews(array $reviewIds): Collection
    {
        if ($reviewIds === []) {
            return new Collection;
        }

        // Ascending, then keyed — `keyBy` keeps the LAST row it sees for each
        // key, so the highest id per review is what survives.
        return Reply::query()
            ->whereIn('review_id', $reviewIds)
            ->orderBy('id')
            ->get()
            ->keyBy('review_id');
    }

    /**
     * Whether this review already carries a decision nobody may overwrite.
     *
     * `approved` counts alongside `posted`: regenerating over an approved reply
     * would throw away the owner's edits and their decision, silently, on a row
     * that is queued to be published under their name.
     */
    public function hasDecidedReply(Review $review): bool
    {
        return Reply::query()
            ->where('review_id', $review->id)
            ->whereIn('status', [ReplyStatus::Approved->value, ReplyStatus::Posted->value])
            ->exists();
    }

    /**
     * Whether this row carries a recorded decision to publish it.
     *
     * ⚠️ THE ONLY PREDICATE `PostReplyJob` MAY ASK, and it deliberately reads the
     * row rather than `autopilot_settings`: a settings read answers "would this
     * be auto-posted today", which is a different question from "was publishing
     * this text decided", and the two diverge the moment somebody edits a
     * setting between generation and posting.
     *
     * ⚠️ AND THAT REASONING IS ABOUT **SETTINGS**, NOT ABOUT THE **RATING**
     * (1855). The rating is not configuration — it is a fact about the review,
     * and a Google reviewer may edit theirs after the fact. Ingest's update
     * branch writes the new rating and dispatches nothing (it is insert-only, so
     * a re-sync cannot spam drafts), so a five-star review auto-approved as
     * `system:auto_post` and then edited down to one star kept a queued *"We're
     * glad you chose X"* reply, waiting to publish the day `canExecute()` flips.
     * `29` §19.3's *"low-star reviews never auto-post"* is about the state at
     * publish, not only at draft time, so the floor is re-read here.
     *
     * ⚠️ FOR THE AUTOMATED ACTOR ONLY. An owner who read the review and approved
     * a reply has taken a decision the platform does not overrule on their own
     * listing — 1730's line, and the same one `approve()` draws.
     */
    public function isPublishable(Reply $reply): bool
    {
        if ($reply->status !== ReplyStatus::Approved
            || $reply->approved_at === null
            || $reply->approved_by === null) {
            return false;
        }

        if ($reply->approved_by !== self::AUTO_POST_ACTOR) {
            return true;
        }

        $review = $reply->review()->first();

        // ⚠️ `$review !== null` IS A TYPE FALLBACK, NOT A SECOND REFUSAL, AND IT
        // WAS ASKED WHETHER IT SHOULD BE ONE (1933). `PostReplyJob::handoff()`
        // would report a null review here as `auto_approval_lapsed` — the
        // reviewer-edited-their-stars case — which is exactly the conflation
        // 1855 split apart. But `replies.review_id` is
        // `constrained()->cascadeOnDelete()`, so a reply cannot outlive its
        // review: the row is gone before `handoff()` reads it, and its own
        // `$reply === null` branch answers `reply_missing` already. A
        // `review_missing` reason for this branch would be a distinction nothing
        // can produce — 256's vacuous gate, introduced by a fix wave, on the
        // predicate that guards a public post. Driven rather than reasoned:
        // 'a reply whose review is gone is reported as a missing reply' deletes
        // the review and pins both halves, so changing that FK reopens the
        // question loudly instead of silently.
        return $review !== null && (int) $review->rating >= self::AUTO_POST_RATING_FLOOR;
    }

    /**
     * Whether configuration, rather than a person, took this row's decision.
     *
     * Lives here rather than as a string comparison in `PostReplyJob`, because
     * `AUTO_POST_ACTOR` is this class's word and a job spelling it itself is a
     * second source of truth for who the automated actor is.
     */
    public function wasAutoApproved(Reply $reply): bool
    {
        return $reply->approved_by === self::AUTO_POST_ACTOR;
    }

    /**
     * Persist a freshly generated draft.
     *
     * Idempotent on the review: a second generation replaces an undecided draft
     * rather than stacking rows. Approved and posted replies are never
     * overwritten.
     *
     * ⚠️ THE GUARDRAILS RUN HERE, NOT ONLY IN `ReplyGenerator` (1730). Decision
     * 1680 makes this the writer boundary, and a blocked-phrase rule enforced at
     * one of two call sites holds by convention rather than by construction —
     * `29` §12.1's *"no reply offers compensation, refunds, or legal
     * statements"* is a build-failing test, so it belongs at the chokepoint every
     * machine-written reply passes through.
     *
     * ⚠️ WHAT THIS CATCHES THAT `ReplyGenerator` DOES NOT, STATED NARROWLY
     * BECAUSE IT WAS OVERSTATED (1742). Both checks now use the identical
     * exemption set — before 1741 this one was strictly *weaker* than the
     * generator's, the generator ran first, and `GenerateReplyJob` was the only
     * caller, so this guardrail could not fire on any text the production path
     * could produce. That is 398's shape: an outer guard refusing first leaves
     * the inner one unfalsifiable, and the test proving it fires called it
     * directly. Two things are genuinely left:
     *
     *   1. **Expansion happens between the two checks.** The generator checks
     *      the model's raw output; `applyVariables()` then expands `{{var}}`,
     *      and expansion can complete a forbidden phrase across a placeholder
     *      boundary — a shop named *Gift* plus a model writing *"enjoy your
     *      {{business_name}} card"* is `gift card`, invisible to the earlier
     *      check and caught here. Pinned by a test.
     *   2. **A second caller.** This is the writer boundary 1680 named; it holds
     *      for whatever calls it next, and nothing about that is present-tense
     *      enforcement it is not performing today.
     *
     * ⚠️ AND THE RATING FLOOR IS RE-ASSERTED HERE (1740). It was applied only in
     * `wantsAutoPost()` — the call site — while this method took `bool
     * $wantsAutoPost` on trust and wrote `Approved` without ever reading
     * `rating`, so `recordSuggestion($oneStarReview, $text, wantsAutoPost: true)`
     * auto-approved a one-star reply. `29` §12.1's *"low-star reviews never
     * auto-post"* is build-failing; a rule enforced only at the call site of the
     * writer is not enforced at the writer. Both checks stay: this is not 398's
     * shape, because this one is reachable directly and is pinned by a test that
     * calls it directly.
     *
     * ⚠️ IT TAKES THE `ReplyDraft`, NOT ITS TEXT, AND THAT IS FOR THE AUDIT
     * ENTRY (1936). `reply.auto_approved` records a publication decision nobody
     * took by hand, and *"was this the model's words or the platform's canned
     * template"* is the boundary the "AI writes replies, never reviews" rule
     * rests on — the same distinction `approve()`'s own comment calls the first
     * thing anybody asks after a complaint. It cannot be derived here: the row
     * has no provenance column, and on a first draft the before-state is null,
     * so the entry could not tell `safeTemplate()` from a model reply at all.
     * Provenance is a fact about *what happened upstream*, so the writer has to
     * be told it — which is a different thing from 1857's exemption set, a fact
     * about the tenant that this chokepoint therefore reads for itself.
     * Taking the object rather than a text-plus-flags triple is what stops the
     * two disagreeing.
     */
    public function recordSuggestion(Review $review, ReplyDraft $draft, bool $wantsAutoPost): Reply
    {
        $this->assertGoogleReview($review);

        $text = $draft->text;

        $wantsAutoPost = $wantsAutoPost && (int) $review->rating >= self::AUTO_POST_RATING_FLOOR;

        if (! $this->guardrails->allows($text, $this->substitutedValues($review))) {
            // ⚠️ ITS OWN TYPE, BECAUSE ONE CALLER HAS TO TELL THIS REFUSAL FROM
            // THE OTHER THREE (1854). `GenerateReplyJob` re-files the safe
            // template when the guardrails refuse; it must not do that when the
            // refusal is "that review already carries somebody's decision".
            throw ReplyGuardrailRefused::because(
                'That draft offers a remedy or a legal position and cannot be filed as a reply.',
            );
        }

        $reply = DB::transaction(function () use ($review, $draft, $text, $wantsAutoPost): Reply {
            $existing = Reply::query()
                ->where('review_id', $review->id)
                ->whereIn('status', [ReplyStatus::Draft->value, ReplyStatus::Suggested->value, ReplyStatus::Failed->value])
                ->orderByDesc('id')
                ->first();

            if ($existing === null && $this->hasDecidedReply($review)) {
                throw new InvalidArgumentException(
                    'That review already has an approved or posted reply. Generating another would '
                    .'discard a decision somebody already took.',
                );
            }

            $reply = $existing ?? new Reply(['review_id' => $review->id]);

            // ⚠️ THE FEED ITEM IS FOR A REVIEW THAT HAD NO DRAFT, NOT FOR AN
            // ATTEMPT AND NOT FOR A TEXT THAT CHANGED (1743). Generation retries
            // now (a transport failure hands the claim back), so a run that
            // produced the same text twice would file "reply suggested" twice
            // and report an outage to the owner as activity. Keying that on the
            // *text* fixed only the rarer half: the ordinary outage sequence is
            // fallback, then a retry that reaches the model and returns
            // **different** words — two feed items for one review, in the common
            // case, with the covering test using two 5xx responses so both texts
            // matched and the case never arose. A draft that improves on a
            // fallback is the same suggestion, not a second one.
            $isNewDraft = $existing === null;
            $textBefore = $existing?->text;
            $statusBefore = $existing?->status;

            $reply->text = $text;
            $reply->status = $wantsAutoPost ? ReplyStatus::Approved : ReplyStatus::Suggested;
            $reply->approved_at = $wantsAutoPost ? now() : null;
            $reply->approved_by = $wantsAutoPost ? self::AUTO_POST_ACTOR : null;
            $reply->hold_until = null;
            $reply->posted_at = null;
            $reply->posted_by = null;
            $this->clearPostingFailure($reply);
            $reply->save();

            if ($wantsAutoPost) {
                // ⚠️ KEYED ON THE ROW BECOMING `Approved`, NOT ON THE DRAFT
                // BEING NEW, AND THE TWO CANCELLED (1851). 1743 narrowed the
                // feed write to `$existing === null` so a retried outage could
                // not file two "reply suggested" items; 1744 then hung the
                // *auto-approved* item off the same condition. The ordinary
                // outage sequence is fallback → retry → approval, and on the
                // second pass `$existing` is the fallback row, so the owner's
                // feed said **"A reply draft is ready for you"** while the
                // system had already approved a reply and queued it to publish
                // under their name. Executed: `status=approved`,
                // `feed=[reply_suggested]`.
                //
                // ⚠️ AND IT IS THE ROW'S STATE RATHER THAN A LITERAL
                // BEFORE/AFTER COMPARISON, BECAUSE THE COMPARISON WOULD BE
                // VACUOUS (256). The `whereIn` above loads only `draft`,
                // `suggested` and `failed` rows, and an already-`approved`
                // review is refused outright by `hasDecidedReply()` — so
                // `$statusBefore` can never be `Approved` here and a
                // `$statusBefore !== Approved` conjunct could not fail. This
                // branch *is* the transition into `Approved`; the before-state
                // is recorded in the audit entry below, where it is evidence
                // rather than a condition.
                $this->auditAutoApproval($review, $reply, $textBefore, $statusBefore, $draft);

                // Approved by configuration and queued to publish on a public
                // listing — not the same feed entry as a draft awaiting the
                // owner, and "a reply draft is ready for you" is not a sentence
                // about it (1744).
                $this->activity->record(
                    AutopilotActionType::ReplyAutoApproved,
                    (int) $review->location_id,
                    ['review_id' => (int) $review->id, 'reply_id' => (int) $reply->id],
                );
            } elseif ($isNewDraft) {
                // A draft that improves on a fallback is the same suggestion,
                // not a second one (1743) — so this one stays keyed on the
                // review having had no draft at all.
                $this->activity->record(
                    AutopilotActionType::ReplySuggested,
                    (int) $review->location_id,
                    ['review_id' => (int) $review->id, 'reply_id' => (int) $reply->id],
                );
            }

            return $reply;
        });

        if ($wantsAutoPost) {
            // ⚠️ OUTSIDE THE TRANSACTION, NOT `afterCommit()` INSIDE IT (1745).
            // Laravel runs `afterCommit` callbacks from within
            // `Connection::commit()`, so on the sync driver the job executes
            // *during* the commit and anything it throws propagates out of
            // `DB::transaction()` — which is how a queued reply post could turn
            // a webhook's 200 into a 500 after every row had already committed.
            // `config/queue.php` sets `after_commit => false` on every
            // connection, so the Redis race the old comment here described is
            // real; dispatching after the transaction has returned answers it
            // without putting a job body inside a commit.
            PostReplyJob::dispatch(
                (int) $review->business_id,
                (int) $review->location_id,
                (int) $reply->id,
            );
        }

        return $reply;
    }

    /**
     * The audit entry for an approval nobody took by hand (1852).
     *
     * ⚠️ THE AUTOMATED PATH WROTE NO AUDIT ROW AT ALL, AND IT WRITES THE SAME
     * THREE COLUMNS AS `approve()`. `status = approved`, `approved_at`,
     * `approved_by = 'system:auto_post'` — the same publication decision, taken
     * by configuration instead of by a person, and `29` §2 rule 42 makes the
     * sensitive action the *authorising*, not the *clicking*. `approve()`'s own
     * comment calls the two sides "the first thing anybody would ask for after a
     * complaint"; an owner disputing a published reply they never read is
     * exactly that complaint, and the answer was an empty table. It also undoes
     * 1750's stated bet — a `$fillable` allowlist protecting these three columns
     * "because the bet here is an audit trail" — on the half that had none.
     *
     * Both sides, like `approve()`: on a first draft the before-state is null
     * and says so, and on the retry-after-outage sequence it is the fallback
     * text the model replaced.
     *
     * ⚠️ AND IT RECORDS THE THREE INPUTS THAT DID THE AUTHORISING, NOT ONLY ITS
     * EFFECT (1932). The entry used to carry `{review_id, status, text}` on both
     * sides — the *outcome* of the decision, with none of its grounds — while
     * grounding itself in `29` §2 rule 42, whose whole point is that the
     * sensitive action is the authorising rather than the clicking. Auto-post is
     * `full_auto_post_replies` AND `rating >= reply_auto_post_min` AND
     * `rating >= AUTO_POST_RATING_FLOOR`, and **the rating is the one input that
     * moves afterwards**: a reviewer may edit their stars, which is the entire
     * premise of `isPublishable()`'s re-read (1855). Once they have, the log
     * could not answer *"did the floor hold when this was authorised?"* — the
     * first question anybody asks about a low-star auto-post. All three are read
     * and written here, so the answer survives the edit.
     *
     * ⚠️ AND THE PROVENANCE OF THE TEXT, BECAUSE THE BEFORE/AFTER PAIR DOES NOT
     * CARRY IT. The paragraph above once claimed the two sides were "the
     * difference between the template being approved and the model's words being
     * approved" — true only on the retry-after-outage sequence. On a **first
     * draft**, which is the common case, `before.text` is null and the entry
     * cannot tell `safeTemplate()` from a model reply. `from_model` and
     * `fallback_reason` come off the `ReplyDraft` and say it outright.
     *
     * ⚠️ ON THE `after` SIDE ONLY, DELIBERATELY. These are the values in force at
     * the moment of authorising; the row carries no provenance column and
     * settings are not versioned, so a `before` copy would either repeat these
     * or be invented. What is not claimed is not written.
     */
    private function auditAutoApproval(
        Review $review,
        Reply $reply,
        ?string $textBefore,
        ?ReplyStatus $statusBefore,
        ReplyDraft $draft,
    ): void {
        $settings = AutopilotSettings::query()
            ->where('location_id', $review->location_id)
            ->first();

        $this->audit->recordChange(
            'reply.auto_approved',
            self::AUTO_POST_ACTOR,
            before: [
                'review_id' => (int) $review->id,
                'status' => $statusBefore?->value,
                'text' => $textBefore,
            ],
            after: [
                'review_id' => (int) $review->id,
                'status' => ReplyStatus::Approved->value,
                'text' => $draft->text,
                // The grounds, read at the moment they were acted on. A null
                // pair is the honest record of a location with no settings row
                // — `wantsAutoPost()` refuses that case, so it can only be
                // reached by a caller passing `wantsAutoPost: true` itself.
                'rating' => (int) $review->rating,
                'full_auto_post_replies' => $settings === null ? null : (bool) $settings->full_auto_post_replies,
                'reply_auto_post_min' => $settings === null ? null : (int) $settings->reply_auto_post_min,
                'from_model' => $draft->fromModel,
                'fallback_reason' => $draft->fallbackReason,
            ],
            entity: $reply,
        );
    }

    /**
     * Whether this review should auto-publish after generation.
     *
     * Auto-post is `full_auto_post_replies` AND rating ≥ `reply_auto_post_min`
     * AND rating ≥ the floor above. Posting itself still handoffs until
     * GbpClient gains a create-reply method.
     *
     * ⚠️ `reply_auto_post_min` IS A STAR RATING, NOT A DELAY (1725). `29` §210,
     * `17` GBP-04 and `14` §2.3 all read it as *"auto-post replies at/above
     * this"*; the admin form declared it as minutes with `min:0`, and `rating >=
     * 0` is true for every review ever left — so *"post immediately"* published
     * an AI reply to one-star reviews with nobody in the loop. The two readings
     * disagreed and the disagreement failed open, which is why the floor above
     * exists as well as the corrected form.
     */
    public function wantsAutoPost(Review $review): bool
    {
        if ((int) $review->rating < self::AUTO_POST_RATING_FLOOR) {
            return false;
        }

        $settings = AutopilotSettings::query()
            ->where('location_id', $review->location_id)
            ->first();

        if ($settings === null || ! $settings->full_auto_post_replies) {
            return false;
        }

        return (int) $review->rating >= (int) $settings->reply_auto_post_min;
    }

    /**
     * Owner edited and approved — record the decision and queue the post.
     *
     * ⚠️ NO GUARDRAIL PASS HERE, DELIBERATELY (1730). `ReplyGuardrails` exists
     * because the model is the party nobody controls; these are the owner's own
     * words about their own business, and refusing to publish them because they
     * contain the word "refund" would be the platform overruling a business
     * owner on their own listing. The rule is about what *we* generate under
     * their name, not about what they choose to say.
     *
     * @throws InvalidArgumentException
     */
    public function approve(Reply $reply, string $text, string $actor): Reply
    {
        $this->assertOwned($reply);

        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException('A reply needs some text before it can be approved.');
        }

        if ($reply->status === ReplyStatus::Posted) {
            throw new InvalidArgumentException('That reply is already posted.');
        }

        $reply = DB::transaction(function () use ($reply, $text, $actor): Reply {
            $before = $reply->text;

            $reply->text = $text;
            $reply->status = ReplyStatus::Approved;
            $reply->approved_at = now();
            $reply->approved_by = $actor;
            $this->clearPostingFailure($reply);

            // ⛔ **THE SWEEP'S CLAIM IS HANDED BACK HERE AND IN NO OTHER
            // METHOD, WHICH IS NOT WHERE THE NEIGHBOURS PUT IT** (11041).
            // `clearPostingFailure()` is the obvious home and is the wrong
            // one: one of its four callers is
            // `reconcileUnconfirmedPublication()`, so a claim released there
            // would re-arm on exactly the path whose loop the claim exists to
            // bound — asked, unanswered, cleared, asked again, for ever, with
            // a notification to a member of the public each time (7126). A
            // fresh approval is a person deciding again, which is a new
            // occasion; a provider read is evidence, which is not.
            //
            // ⚠️ **AND `recordSuggestion()` DELIBERATELY DOES NOT DO THIS.**
            // Its `whereIn` loads only `draft`, `suggested` and `failed` rows
            // and `hasDecidedReply()` refuses a review that already carries an
            // approval, so a row it can reach has never been `Approved` and
            // this column is null on it already. A release there would be a
            // line that cannot change anything — 256's shape, in the writer
            // that decides whether a reply gets published on its own.
            //
            // ⚠️ **`markPosted()` DOES NOT EITHER, AND THERE THE COLUMN IS
            // EVIDENCE.** A posted row keeps the stamp so an auditor can tell
            // a publication the owner asked for from one this sweep caused.
            $reply->publish_retry_dispatched_at = null;

            $reply->save();

            // ⚠️ BOTH SIDES, NOT JUST THE OUTCOME (1731). `29` §2 rule 42 and
            // AuditService's own docblock: an entry recording only the new value
            // cannot answer what happened. Here the two sides are the evidence
            // that distinguishes a verbatim model reply from one the owner
            // rewrote — the boundary the "AI never writes a review" rule rests
            // on, and the first thing anybody would ask for after a complaint.
            $this->audit->recordChange(
                'reply.approved',
                $actor,
                before: ['review_id' => (int) $reply->review_id, 'text' => $before],
                after: ['review_id' => (int) $reply->review_id, 'text' => $text],
                entity: $reply,
            );

            return $reply;
        });

        // The same move as `recordSuggestion()`'s, for the same reason (1745)
        // and deliberately not left as the one unfixed half of a pair — a shape
        // corrected in one of two identical places is how the next reader
        // concludes the other one is fine.
        PostReplyJob::dispatch(
            (int) $reply->business_id,
            (int) $reply->review()->firstOrFail()->location_id,
            (int) $reply->id,
        );

        // The reply row has no provenance column linking it to the AiCall that drafted it,
        // so we pass the latest reply.generate prompt.
        $prompt = app(PromptResolveAction::class)->handle(
            (int) $reply->business_id,
            'reply.generate',
        );

        ReplyApproved::dispatch(
            (int) $reply->business_id,
            (int) $reply->id,
            (int) $reply->review_id,
            $reply->review()->firstOrFail()->comment,
            $text,
            $prompt?->id ? (string) $prompt->id : null,
            $prompt?->version ? (string) $prompt->version : null,
        );

        return $reply;
    }

    /**
     * Owner skipped — discard the draft. No public post, no feed spam.
     *
     * @throws InvalidArgumentException
     */
    public function skip(Reply $reply, string $actor): void
    {
        $this->assertOwned($reply);

        if ($reply->status === ReplyStatus::Posted) {
            throw new InvalidArgumentException('A posted reply cannot be skipped.');
        }

        DB::transaction(function () use ($reply, $actor): void {
            $this->audit->record(
                'reply.skipped',
                $actor,
                $reply,
                ['review_id' => (int) $reply->review_id],
            );

            $reply->delete();
        });
    }

    /**
     * Record that the provider accepted this reply for publication.
     *
     * ⚠️ **THE ONLY WRITER OF `posted` / `posted_at` / `posted_by`, AND IT IS
     * DELIBERATELY NOT REACHABLE FROM A SCREEN.** Nothing a person clicks may
     * assert that a reply reached Google: the only party that knows is the
     * provider, and the only code that hears from the provider is the job. A
     * setter on the approval screen would let the queue be cleared by declaring
     * success, which is the one lie this table exists to make impossible.
     *
     * ⚠️ **IT REFUSES A ROW THAT WAS NEVER APPROVED, EVEN THOUGH ITS CALLER
     * ALREADY CHECKED** (398). `PostReplyJob` asks `isPublishable()` twice
     * before it gets here, so on the ordinary path this refusal cannot fire —
     * which is precisely the shape that gets deleted as redundant, and then the
     * day somebody adds a second caller there is nothing between an undecided
     * AI draft and a `posted` row. It is pinned by a test that calls this
     * method directly with a `suggested` row, because that is the only way the
     * branch is falsifiable at all.
     *
     * ⚠️ **THE EARLIER ATTEMPT'S FAILURE IS CLEARED HERE AND THAT IS NOT
     * TIDYING.** A row that published on its second attempt would otherwise keep
     * the first attempt's failure text next to a `posted` status — an owner
     * reading "posting is not available" under a reply that is live on their
     * listing. ⚠️ **AND IT IS TWO COLUMNS SINCE 6720**, which is why it goes
     * through {@see self::clearPostingFailure()}: leaving
     * `provider_declined_at` set on a posted row would say *"Google did not take
     * it"* about the text on the listing.
     *
     * The audit entry carries the provider's own acknowledgement rather than a
     * restatement of what we asked for: `29` §2 rule 42's question is what
     * happened, and after a moderated publish the only honest answer to *"is it
     * live?"* is what the vendor said and what it did not say.
     *
     * ⛔ **THE WRITE IS CONDITIONAL ON THE ROW UNDER LOCK, WHICH IS 6525's HALF
     * OF THE FIX** (6820). The guard above reads the caller's instance, which
     * was loaded before an HTTP round trip to a third party; the transaction
     * below re-reads the row `FOR UPDATE` and asks the same predicate again. Two
     * workers holding two instances of one `approved` row both pass the first
     * check — that is not hypothetical, it is what a released claim and a
     * re-approval can produce between them — and without the second one they
     * write two `reply.posted` audit entries for a single publication and move
     * `posted_at` backwards.
     *
     * ⚠️ **AND THE SECOND CHECK RETURNS WHERE THE FIRST THROWS.** Every caller
     * reaches this method **after** the vendor has accepted the text. An
     * exception here fails the job, the queue retries, and `PostReplyJob`
     * publishes the same reply a second time — so a guard against a double post
     * that threw would cause one. The first check keeps its exception because it
     * runs before anything has been published and its subject is a caller
     * asking to record a publication for a row nobody decided (398).
     *
     * @throws InvalidArgumentException
     */
    public function markPosted(Reply $reply, GbpReplyReceipt $receipt, string $actor): Reply
    {
        $this->assertOwned($reply);

        if (! $this->isPublishable($reply)) {
            throw new InvalidArgumentException(
                'That reply carries no recorded decision to publish it, so it cannot be marked posted.',
            );
        }

        // ⚠️ **THE FEED ITEM IS FILED AFTER THE COMMIT AND ONLY ON THE ARM THAT
        // WROTE**, which is both halves of 7222. The early return above is a
        // second worker finding the publication already recorded, and a feed
        // row there would tell the owner one reply went up twice; filing inside
        // the transaction would put a broadcast on the wire for a row a
        // rollback then removed.
        $published = false;

        $posted = DB::transaction(function () use ($reply, $receipt, $actor, &$published): Reply {
            // ⛔ THE ROW UNDER LOCK DECIDES, NOT THE INSTANCE THE CALLER IS
            // HOLDING (6820). The guard above reads `$reply` — an object loaded
            // before an HTTP call to a third party — and by the time the receipt
            // comes back another worker may have recorded the same publication.
            // `claimRun()` already argues this shape for the run row: the
            // database is the arbiter and a preceding SELECT is a race. Here the
            // consequence of losing it is two `reply.posted` audit entries for
            // one publication, and a `posted_at` that moves backwards.
            $current = Reply::query()->whereKey($reply->getKey())->lockForUpdate()->first();

            // ⚠️ IT RETURNS RATHER THAN THROWS, AND THAT IS THE WHOLE POINT
            // (6821). This method is called **after** the vendor accepted the
            // reply. Throwing here would fail the job, the queue would bring it
            // back, and `execute()` would ask Google to publish the same text
            // again — the defect this slice exists to close, recreated by its
            // own guard. Somebody else recording the publication is not an
            // error; it is the answer to "has this been recorded?" being yes.
            if (! $current instanceof Reply || ! $this->isPublishable($current)) {
                return $current instanceof Reply ? $current : $reply;
            }

            $statusBefore = $current->status;

            $current->status = ReplyStatus::Posted;
            $current->posted_at = now();
            $current->posted_by = $actor;
            $this->clearPostingFailure($current);
            $current->save();

            $this->audit->recordChange(
                'reply.posted',
                $actor,
                before: [
                    'review_id' => (int) $current->review_id,
                    'status' => $statusBefore->value,
                ],
                after: [
                    'review_id' => (int) $current->review_id,
                    'status' => ReplyStatus::Posted->value,
                    // ⚠️ Both nullable, and a null is the record that the
                    // provider told us nothing — not a field nobody filled in.
                    // Google's own moderation verdict never reaches us through
                    // this relay, so "accepted" is the strongest claim on offer.
                    'provider_reply_id' => $receipt->providerReplyId,
                    'provider_status' => $receipt->providerStatus,
                ],
                entity: $current,
            );

            $published = true;

            return $current;
        });

        if ($published) {
            $this->recordReplyPosted($posted);
        }

        return $posted;
    }

    /**
     * Record that posting could not complete.
     *
     * ⚠️ THIS NO LONGER TOUCHES `status`, AND THAT IS THE FIX (1728). It used to
     * write `suggested` unconditionally, which did two wrong things at once: it
     * could demote an already-`posted` row while leaving `posted_at` populated,
     * and — once approval became a real state — it un-approved every reply the
     * owner had just decided on, pushing the card back into the queue for them
     * to approve again, and again. Publishing being unavailable is a fact about
     * the platform, not a reason to discard somebody's decision.
     *
     * ✅ **AND IT NO LONGER DECIDES WHAT THE OWNER IS TOLD, WHICH IS 6685
     * CLOSED** (6720). Two call sites in `app/`, and only one of them ever left
     * this application: `PostReplyJob::execute()` calls it for
     * `connection_revoked`, which the vendor told us; `PostReplyJob::handoff()`
     * calls it for `integration_disabled` and for `not_connected`, **neither of
     * which reaches Google**. Until 6720 `ReplyPublicationStatus` derived
     * `ReplyPublicationState::NotAccepted` — *"We tried to publish this and
     * Google did not take it."* — from `error_message` being non-null, so those
     * two rows asserted a decision a third party had never made. It was hidden
     * only by the ladder's earlier arms, and only while `gbp.zernio_enabled` was
     * false.
     *
     * ⛔ **THE FIX IS NOT A RULE ABOUT WHO MAY CALL THIS METHOD** (6721). 6684
     * wrote that rule down and it held for one wave: the pre-flight arms of
     * `execute()` obeyed it and the two arms of `handoff()`, which pre-dated it,
     * did not — and nothing could tell. What decides the sentence now is
     * `replies.provider_declined_at`, and the **only** writer of that column is
     * {@see self::markDeclinedByProvider()}, which cannot be called without a
     * {@see GbpRequestFailed} the vendor's own response produced. So this method
     * is free to record any failure at all: it has no way to say *"Google
     * declined this"*, whoever calls it and whatever they pass.
     *
     * ⚠️ **`error_message` KEEPS ITS OTHER TWO JOBS** — it is the debugging
     * record, and its presence is what tells a later reader an attempt happened
     * at all. It is still never rendered (1748) and is still not a general
     * "something went wrong" column: `PostReplyJob`'s pre-flight arms of
     * `execute()` continue to record in `automation_runs` and leave it alone
     * (6684), because a run row names the arm and this column names nothing.
     */
    public function markPostingUnavailable(Reply $reply, string $reason): Reply
    {
        $this->assertOwned($reply);

        $reply->error_message = $reason;
        $reply->save();

        $this->recordOwnerActionNeeded($reply, 'reply_posting_unavailable');

        return $reply;
    }

    /**
     * Record that the provider was asked to publish this reply and refused it.
     *
     * ⛔ **THE ONLY WRITER OF `provider_declined_at`, AND THE ONLY ROUTE TO THE
     * ONE SENTENCE ON THE OWNER'S SCREEN THAT SPEAKS FOR GOOGLE** (6720, 6722).
     * `ReplyPublicationState::NotAccepted` reads *"We tried to publish this and
     * Google did not take it."* — a statement about a third party's decision —
     * and it is now derived from this column alone. Nothing else in `app/`
     * assigns it and a lint in `Architecture\ReviewsTest` enumerates the
     * methods here that may.
     *
     * ⚠️ **IT TAKES THE FAILURE RATHER THAN A REASON STRING, WHICH IS
     * `markPosted()`'s SHAPE AND FOR `markPosted()`'s REASON** (6722). That
     * method requires a {@see GbpReplyReceipt} because nothing but a vendor
     * round trip can produce one, so a screen cannot declare a reply published.
     * This one requires a {@see GbpRequestFailed}, which
     * `GbpRequestFailed::from()` builds out of a `Response` the vendor returned.
     * A pre-flight refusal — no connection, no location, the integration off —
     * has no such object to hand and would have to **forge** one to reach this
     * method. That is the difference between a wrong sentence being unreachable
     * and it being unrepresentable, and it is the whole of this slice.
     *
     * ⛔ **AND NOT EVERY `GbpRequestFailed` IS A DECLINE** (6723). A retryable
     * one is a blip the queue will come back for, and writing a refusal for it
     * would put a settled verdict on a row that is about to publish; a
     * `disconnected` one is the vendor refusing **us** rather than refusing this
     * text, and its honest sentence is `NotConnected`. Both are refused here
     * rather than left to the caller to remember, because "the caller
     * remembers" is exactly what 6684 tried and 6685 is.
     *
     * ⚠️ **THE VENDOR'S OWN WORDS ARE NOT STORED, AND THE SENTENCE WRITTEN INTO
     * `error_message` IS OURS.** 1748 permits recording machinery and forbids
     * rendering it; `$failure->reason` is Zernio's `code` and belongs in the
     * `automation_runs` output, where `PostReplyJob` already puts it. What lands
     * on the row is the English `PostReplyJob` used to pass in — kept identical,
     * because it is what `ReplyPublicationStatus`' docblock promises about the
     * three strings this column can hold.
     *
     * @throws InvalidArgumentException
     */
    public function markDeclinedByProvider(Reply $reply, GbpRequestFailed $failure): Reply
    {
        $this->assertOwned($reply);

        if ($failure->retryable) {
            throw new InvalidArgumentException(
                'A retryable failure is not a decision by the provider, so it cannot be recorded as one.',
            );
        }

        if ($failure->disconnected) {
            throw new InvalidArgumentException(
                'A revoked connection is the provider refusing this application, not refusing this reply.',
            );
        }

        // ⛔ THE THIRD GUARD, AND IT IS THE ONE {@see self::markPublishUnconfirmed()}
        // HAS HAD SINCE 7000 WHILE THIS METHOD DID NOT (9145). The sentence below
        // says *Google* would not accept this reply, and `clientRefused` is this
        // application declining to make a call at all — an integration switched
        // off (`gbp.zernio_enabled`), or a platform API key nobody has pasted.
        // Writing either down as the provider's verdict puts a false statement
        // about a third party in the owner's feed, stamps `provider_declined_at`
        // on a reply Google has never seen, and points the remedy at the tenant
        // when it belongs to us.
        //
        // ⛔ IT READS THE FLAG AND NOT `status === 0`, AND THE FIRST DRAFT OF
        // THIS GUARD READ THE PROXY (9145). `GbpRequestFailed::unreadable()`
        // also carries status `0` and *is* a response the vendor returned — a
        // reply comment we could not parse is Google having answered — so the
        // proxy refuses three of this file's own tests and would have suppressed
        // a genuine decline. `CLAUDE.md`'s rule about a door guard reading the
        // column the constraint reads, met in a lane written to close an
        // instance of it.
        //
        // ⚠️ THE DOCBLOCK ABOVE `PostReplyJob`'s CALL SITE SAID THIS ARM COULD
        // ONLY BE REACHED BY "a response the vendor returned" AND IT WAS NOT
        // TRUE — `disabled()` reached it whenever `gbp.zernio_enabled` was
        // flipped between `canExecute()` and the call, and `unconfigured()`
        // would have reached it with no race at all. The caller now refuses a
        // client refusal too; this guard is what makes that refusal a check
        // rather than a restatement (398).
        if ($failure->clientRefused) {
            throw new InvalidArgumentException(
                'A refusal by this client is not a decision by the provider, so it cannot be recorded as one.',
            );
        }

        $reply->error_message = 'Google would not accept this reply. It is still saved here and can be edited and approved again.';
        $reply->provider_declined_at = now();
        $reply->save();

        $this->recordOwnerActionNeeded($reply, 'reply_posting_unavailable');

        return $reply;
    }

    /**
     * Record that we asked the provider to publish and were never answered.
     *
     * ⛔ **THE THIRD OUTCOME OF A VENDOR ROUND TRIP, AND THE ONLY ONE THIS TABLE
     * COULD NOT REPRESENT** (6525, 6822). {@see self::markPosted()} records that
     * the provider accepted; {@see self::markDeclinedByProvider()} records that
     * it refused. This records that it said **nothing at all** — a connection
     * reset, a read timeout, a socket that went away — which is the outcome
     * `PostReplyJob` used to write nowhere at all before rethrowing, so the
     * `$tries` ladder came back to a row that looked untouched and asked Google
     * to publish the same text again. **The row is the only thing that can tell
     * a later attempt an earlier one happened**, because `automation_runs`
     * cannot: `releaseUnearnedClaim()` nulls the one field on it that names this
     * reply, on exactly the attempts that did not publish.
     *
     * ⚠️ **IT TAKES THE FAILURE RATHER THAN A REASON STRING, WHICH IS
     * `markDeclinedByProvider()`'s SHAPE AND FOR ITS REASON** (6722). The two
     * refusals below are the whole of why the parameter is the exception object:
     *
     *   1. **A terminal failure is an answer.** `GbpRequestFailed::unreadable()`
     *      and `::disabled()` both carry status `0` and are not retryable, and
     *      both mean this application refused something rather than a network
     *      swallowing it. Recording them here would put *"we do not know"* on a
     *      row we know perfectly well about.
     *   2. **A failure carrying a status is a response the vendor returned.**
     *      `GbpRequestFailed::from()` is built out of a `Response`, so a 429, a
     *      500 or a 502 is Zernio speaking — the request completed a round trip
     *      and the queue may retry it without risking a second publication. Only
     *      `::unreachable()` produces `retryable` **and** status `0`, and that is
     *      the one case where nothing came back to classify.
     *
     * ⚠️ **AND `0` IS AS FINE AS THE CLASSIFICATION GETS FROM HERE.** Laravel
     * raises one `ConnectionException` for a connection that was refused and for
     * a response that never finished arriving — Guzzle maps `CURLE_COULDNT_CONNECT`
     * and `CURLE_OPERATION_TIMEOUTED` onto the same exception —
     * and `ZernioGbpClient::replyToReview()` discards the underlying reason when
     * it builds `unreachable('connection_failed')`. So *"we never sent it"* and
     * *"we sent it and did not hear back"* arrive here identically, and this
     * column's name states the position honestly rather than guessing which one
     * it was. Narrowing that is a change to the client and is recorded as owed.
     *
     * ⛔ **THE PARAGRAPH ABOVE IS KEPT WORD FOR WORD AND IS NO LONGER TRUE — THE
     * OWED CHANGE WAS MADE ON 2026-08-21 (7000–7004).** It is kept because it is
     * the whole reason the defect was findable: **this file wrote down that it
     * was discarding the one fact separating two opposite outcomes, and the note
     * was believed and left.** Deleting it removes the evidence and the lesson at
     * once (6938's rule, and `CLAUDE.md` 314–316 on what a written-down hazard
     * does to the next reader).
     *
     * ✅ **WHAT IS TRUE NOW.** `ZernioGbpClient::transportFailure()` reads the
     * libcurl code — from `ConnectException::getHandlerContext()['errno']`, and
     * from Guzzle's own `cURL error N:` message prefix when there is no context —
     * and builds `GbpRequestFailed::neverSent()` for **6** (`COULDNT_RESOLVE_HOST`)
     * and **7** (`COULDNT_CONNECT`) only, which carries
     * `mayHaveBeenSent === false`. Everything else, **including anything it
     * cannot read**, still arrives as `unreachable('connection_failed')` and
     * still lands here. So this method is reached by a strictly smaller set of
     * failures and by none it was not reached by before.
     *
     * ⛔ **AND THE THIRD GUARD BELOW IS WHY THAT IS SAFE RATHER THAN MERELY
     * NARROWER.** A `neverSent()` failure recorded here would put *"we do not
     * know whether it went up"* on a reply that demonstrably never left this
     * process — and, worse, would make {@see self::publishIsUnconfirmed()}
     * refuse every later attempt at a reply nobody has ever sent, which is the
     * stranding this slice exists to end. The guard makes that unrepresentable
     * rather than merely unreached: 6721's lesson applied before it can bite,
     * because the previous design was already "unreachable" and that is exactly
     * what the defect was.
     *
     * ⛔ **AND SOMETHING NOW RESOLVES WHAT THIS WRITES, WHICH NOTHING DID UNTIL
     * 2026-08-21** (6955, closed at 7120–7139). When this method landed the
     * column's only clearers were {@see self::recordSuggestion()},
     * {@see self::approve()} and {@see self::markPosted()} — three *actions on
     * the reply*, none of them an answer about its fate — so a row could say
     * *"we do not know"* for ever, and the owner's only available move
     * destroyed the record. {@see self::reconcileUnconfirmedPublication()}
     * settles it against what the provider reports, **in the negative direction
     * only**: a report that there is no owner reply on the review says nothing
     * about whose reply it is not, which is the one report this application is
     * entitled to act on.
     *
     *      * ⛔ **NOTHING HERE CLAIMS A PUBLICATION AND NOTHING CLAIMS A REFUSAL**
     * (2332, 6720). `status` is untouched — 1728's rule — `posted_at` is
     * untouched, and `provider_declined_at` is untouched, so no screen can read
     * this row as *"Google did not take it"*. What the owner is told is filed
     * under its own reason, because *"we could not post it"* is a claim about an
     * outcome nobody knows.
     *
     * ⚠️ **AND A SCREEN NOW READS THIS COLUMN, WHICH IT DID NOT WHEN THE
     * PARAGRAPH ABOVE WAS WRITTEN — CORRECTED 2026-08-21 (6954, 7018).** The
     * clause *"no screen can read this row as 'Google did not take it'"* is
     * still exactly true and is the one that matters. What changed is the
     * silence beside it: `ReplyPublicationStatus` used to ignore this column
     * entirely, so a stamped row rendered as `NotPublishedYet` — *"This is not
     * on Google."* — a positive claim about a listing that may carry the reply.
     * It now derives `ReplyPublicationState::PublishUnconfirmed` from this
     * column, first in the ladder. **A column with a writer and no reader told
     * the owner the opposite of what this method was recording.**
     *
     * @throws InvalidArgumentException
     */
    public function markPublishUnconfirmed(Reply $reply, GbpRequestFailed $failure): Reply
    {
        $this->assertOwned($reply);

        if (! $failure->retryable) {
            throw new InvalidArgumentException(
                'A terminal failure is a settled outcome, so it cannot be recorded as an unanswered attempt.',
            );
        }

        if ($failure->status !== 0) {
            throw new InvalidArgumentException(
                'A failure carrying a status is a response the provider returned, so it cannot be recorded as an unanswered attempt.',
            );
        }

        // ⛔ THE THIRD GUARD, AND IT IS THE ONLY ONE THAT ASKS ABOUT THE REQUEST
        // RATHER THAN THE ANSWER (7000). `mayHaveBeenSent` is `true` on every
        // constructor by default, so a failure only reaches this arm when the
        // client positively read a libcurl code meaning the request never left.
        // The sentence below and the column both say "we sent this and did not
        // hear back", and neither is true of a connection that was never opened.
        if (! $failure->mayHaveBeenSent) {
            throw new InvalidArgumentException(
                'A request that never left this application is not an unanswered attempt, so it cannot be recorded as one.',
            );
        }

        $reply->error_message = 'We sent this reply to Google and did not get an answer back, so we do not know whether it went up.';
        $reply->publish_unconfirmed_at = now();
        $reply->save();

        $this->recordOwnerActionNeeded($reply, 'reply_publish_unconfirmed');

        return $reply;
    }

    /**
     * Whether an earlier attempt reached the provider and was never answered.
     *
     * ⛔ **IT ASKS THE DATABASE, NOT THE INSTANCE, AND THAT IS THE WHOLE VALUE
     * OF IT** (6823). Its one caller is `PostReplyJob::execute()`, immediately
     * before the vendor call — and the instance that caller is holding was
     * loaded several queries earlier, at the top of a method that has since
     * resolved a review, a location and a connection. A predicate reading
     * `$reply->publish_unconfirmed_at` would answer for the row as it was when
     * somebody last read it, which is the state that lets the second post
     * through. The query is tenant-scoped and RLS-covered like every other read
     * here, so "the database" means "this tenant's row" and cannot mean another.
     */
    public function publishIsUnconfirmed(Reply $reply): bool
    {
        return Reply::query()
            ->whereKey($reply->getKey())
            ->whereNotNull('publish_unconfirmed_at')
            ->exists();
    }

    /**
     * Record that the sweep, rather than a person, asked for this reply again.
     *
     * ⛔ **THE CLAIM IS SPENT BY A DISPATCH AND NEVER BY AN EVALUATION, AND
     * THAT ORDERING IS THE WHOLE DESIGN** (11040). A claim keyed on the
     * *subject* — this reply — is consumed the first time the sweep looks at it
     * and declines, so the retry that should have happened never does: a tenant
     * whose integration is switched on while their location is still
     * disconnected would have burned their one attempt on a sweep that
     * dispatched nothing. `reviews:retry-stranded-replies` therefore skips such
     * a row without touching this column and calls this method only after
     * `PostReplyJob::dispatch()` has returned. **The occasion is the dispatch.**
     *
     * ⛔ **IT IS AUDITED, AND THE PRECEDENT IS
     * {@see self::reconcileUnconfirmedPublication()} RATHER THAN A JUDGEMENT
     * CALL.** That method writes `reply.publish_rechecked` under a constant
     * actor because *nobody decided* — a scheduled read of a third party's data
     * moved a reply's publication state with no person in the loop. This is the
     * same shape and one step more consequential: it removes the last thing
     * between an approved reply and text appearing on a public listing under
     * the business's name, on a schedule, hours or weeks after the owner
     * pressed the button. `29` §2 rule 42 asks what was authorised, and the
     * honest answer is *the owner authorised the text and a sweep chose the
     * moment*.
     *
     * ⛔ **IT SAVES A STALE INSTANCE ON PURPOSE, AND THAT IS ONLY SAFE
     * BECAUSE `save()` WRITES THE DIRTY ATTRIBUTES AND NOTHING ELSE.** Its
     * caller loads the row, dispatches, and stamps — and on the `sync` driver
     * the dispatch **runs the job inline**, so by the time this method is
     * reached the database row may already be `Posted` with `posted_at` set and
     * `error_message` cleared, while the instance in hand still says
     * `Approved` with the handoff's sentence on it. `Model::save()` issues
     * `UPDATE … SET publish_retry_dispatched_at = ?` and touches neither.
     * ⛔ **So a second assignment in this method, a `forceFill()`, or a
     * `setRawAttributes()` would silently un-publish a reply that is live on a
     * public listing.** Driven rather than reasoned:
     * `tests/Feature/ReplyRetrySweepTest.php`'s first test asserts `Posted`, a
     * non-null stamp and a null `error_message` on the same row, and it is the
     * whole of what stands between this line and that regression.
     *
     * ⚠️ **A CONSTANT ACTOR AND NEVER A PARAMETER** (7128's rule, verbatim).
     * Every other actor on this class arrives from its caller because *who
     * approved this* is a fact only the caller knows. Here the recorded fact is
     * that nobody did, and an actor a caller supplies is one a caller can dress
     * up as a person.
     *
     * ⛔ **NO ACTIVITY-FEED ITEM, DELIBERATELY** (11042). The feed already
     * speaks for both outcomes of the attempt this dispatch causes —
     * `AutopilotActionType::ReplyPosted` when it lands (7134) and
     * `OwnerActionNeeded` when it does not — and an item filed *here* could
     * only say *"we are going to try again"*, which is the future-tense promise
     * 1747 removed from `approve()`'s toast and 6523 refused as a heading. A
     * feed row is what an owner reads; this is what an auditor reads.
     */
    public function markPublishRetryDispatched(Reply $reply): Reply
    {
        $this->assertOwned($reply);

        $reply->publish_retry_dispatched_at = now();
        $reply->save();

        $this->audit->record(
            'reply.publish_retry_dispatched',
            self::PUBLISH_RETRY_ACTOR,
            $reply,
            ['review_id' => (int) $reply->review_id],
        );

        return $reply;
    }

    /**
     * Settle an unanswered attempt against what the provider now reports.
     *
     * ⛔ **THE FIRST THING IN THIS APPLICATION THAT EVER RESOLVED
     * `publish_unconfirmed_at`, AND 6955 SAID SO IN THE TREE'S OWN WORDS**
     * (7120–7139): *"nothing resolves an unconfirmed row, so the honest
     * sentence is permanent."* The column's three clearers were all **actions
     * on the reply** rather than answers about its fate —
     * {@see self::recordSuggestion()} (unreachable: an unconfirmed row is
     * `Approved` and `hasDecidedReply()` refuses), {@see self::markPosted()}
     * (unreachable: `PostReplyJob::execute()` refuses the vendor call while the
     * stamp is set) and {@see self::approve()}, which is the owner pressing
     * *Approve again* **without having looked**, destroying the only record
     * that anybody was ever unsure.
     *
     * ⛔ **IT RESOLVES IN ONE DIRECTION ONLY, AND THE OTHER DIRECTION IS NOT
     * SHY — IT IS IMPOSSIBLE** (7121). 6832 prescribed a sweep that *"either
     * records it or clears it and re-dispatches"*. **The first half cannot be
     * built from this evidence and must never be**: `GbpReview`'s own docblock
     * has said since the day it was written that `hasOwnerReply` *"does not
     * mean we replied, and it must never be reported as a reply we sent"*.
     * Somebody else's owner reply, a reply typed into Google's own dashboard,
     * and — 7123 — a reply of ours that Google **rejected** in moderation all
     * report the same `true`. So a positive report may never reach
     * {@see self::markPosted()} and this method never calls it. A negative
     * report attributes nothing to anybody: *"there is no owner reply on this
     * review"* settles ours without naming whose it is not.
     *
     * ⚠️ **THE SETTLE WINDOW IS A GUESS AND IT IS DECLARED AS ONE**
     * (7124). See {@see $this->publishRecheckSettleMinutes()}.
     *
     * ⛔ **IT CLEARS AND DOES NOT RE-DISPATCH, WHICH IS HALF OF 6832 REFUSED
     * ON PURPOSE** (7126). Re-dispatching from here is unbounded: the fresh
     * attempt can be unanswered too, which re-stamps the column, which the next
     * provider report clears again — a reply re-published on a public listing
     * on a loop, with a notification to a member of the public each time, and
     * `updateReply`'s `PUT` semantics mean the customer sees one reply and no
     * evidence of any of it. Bounding that needs a per-reply record of how many
     * times this has already fired, which is a column this slice does not add.
     * ✅ **THAT COLUMN EXISTS SINCE 2026-08-28 AND THIS METHOD STILL DOES NOT
     * RE-DISPATCH** (11040, 11041). `replies.publish_retry_dispatched_at` is
     * the per-reply record 7126 named as missing, and it is written by
     * `reviews:retry-stranded-replies` rather than here — because the loop
     * described above forms precisely when the *clearer* is also the
     * *dispatcher*. The sweep refuses a row carrying `publish_unconfirmed_at`
     * outright, so the only way an unanswered reply becomes a candidate again
     * is this method clearing it — and by then the claim is spent, which bounds
     * the loop at one. ⛔ **Nothing here may be changed to re-dispatch on the
     * strength of that column existing**; the split is the whole bound.
     * What clearing on its own buys is real and is the whole of the defect:
     * the reply becomes publishable again, the card stops saying *"we do not
     * know"*, and the owner's *Approve again* is a retry against a listing we
     * have actually read rather than a coin flip that destroys the record.
     *
     * ⚠️ **THE OBSERVATION TIME IS THE CALLER'S BECAUSE ONE CALLER'S IS NOT
     * `now()`** (7125). `gbp:sync` reads live, so its observation is the moment
     * of the read. A `review.updated` webhook carries Zernio's own
     * `timestamp` — *"set once when the event payload is built, before delivery
     * is queued. Retries and redeliveries keep the original value"* — and their
     * documented retry schedule runs to **~51 hours**, so a delivery may
     * describe a review as it stood two days ago. Treating that as `now()`
     * would settle an attempt against a report that pre-dates it.
     *
     * @param  ?bool  $providerReportsOwnerReply  `GbpReview::$ownerReplyReported`
     *                                            — `null` means the payload did
     *                                            not say, which is not a
     *                                            negative and does nothing.
     * @param  CarbonInterface  $observedAt  When the provider was asked, not
     *                                       when this method was called.
     * @return ?Reply The row this settled, or null when nothing was settled.
     */
    public function reconcileUnconfirmedPublication(
        Review $review,
        ?bool $providerReportsOwnerReply,
        CarbonInterface $observedAt,
    ): ?Reply {
        $this->assertGoogleReview($review);

        // ⛔ `!== false` RATHER THAN `=== true`, SO `null` FALLS ON THE SAME
        // SIDE AS A REPLY BEING PRESENT. The only value that may settle
        // anything is a boolean `false` the vendor actually sent.
        if ($providerReportsOwnerReply !== false) {
            return null;
        }

        $settledBefore = $observedAt->copy()->subMinutes($this->publishRecheckSettleMinutes());

        // A cheap existence check before the locking transaction, which is the
        // shape `publishIsUnconfirmed()` already uses: this runs once per
        // updated review on every fifteen-minute sync, and the overwhelmingly
        // common answer is "there is no such row".
        $exists = Reply::query()
            ->where('review_id', $review->id)
            ->whereNotNull('publish_unconfirmed_at')
            ->where('publish_unconfirmed_at', '<=', $settledBefore)
            ->exists();

        if (! $exists) {
            return null;
        }

        return DB::transaction(function () use ($review, $settledBefore): ?Reply {
            // ⛔ THE ROW UNDER LOCK DECIDES (6820, 6823). Between the check
            // above and here, `approve()` may have cleared the column and
            // dispatched a fresh attempt — and clearing the failure state of a
            // reply that is in flight would overwrite an `error_message` the
            // new attempt is about to write.
            $reply = Reply::query()
                ->where('review_id', $review->id)
                ->whereNotNull('publish_unconfirmed_at')
                ->where('publish_unconfirmed_at', '<=', $settledBefore)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $reply instanceof Reply) {
                return null;
            }

            $unconfirmedAt = $reply->publish_unconfirmed_at;

            $this->clearPostingFailure($reply);

            // ⚠️ SET **AFTER** THE CLEAR, WHICH NULLS IT. `error_message` is
            // machinery and is never rendered (1748, 6720): what an owner reads
            // is derived by `ReplyPublicationStatus`, which is forbidden from
            // reading this column at all. It is here so whoever debugs the row
            // can tell a reply nobody has tried from one that was tried,
            // unanswered, and then read back.
            $reply->error_message = 'We asked Google to publish this reply and got no answer. A later read reported no owner reply on that review, so it did not go up.';
            $reply->save();

            // `29` §2 rule 42. What was authorised is what matters: this
            // removes the one thing standing between an approved reply and a
            // public listing, on the strength of a third party's report, with
            // no person in the loop. Both sides, and the report that did it.
            $this->audit->recordChange(
                'reply.publish_rechecked',
                self::PUBLISH_RECHECK_ACTOR,
                before: [
                    'review_id' => (int) $reply->review_id,
                    'publish_unconfirmed_at' => $unconfirmedAt?->toIso8601String(),
                ],
                after: [
                    'review_id' => (int) $reply->review_id,
                    'publish_unconfirmed_at' => null,
                    // ⚠️ The vendor's own answer, not our conclusion from it.
                    'provider_reports_owner_reply' => false,
                ],
                entity: $reply,
            );

            // ⚠️ **THE EXISTING REASON, NOT A NEW ONE, AND THE SENTENCE IS NOW
            // EXACTLY TRUE** (7127). `OwnerAttention` renders
            // `reply_posting_unavailable` as *"We wrote a reply to one of your
            // reviews and could not post it. It is saved for you."* — which is
            // precisely what has just been established, and is the sentence
            // 6824 split away from *"we do not know"* for the state this one
            // has stopped being. A third literal would need a row in
            // `Architecture\ActivityTest`'s enumeration and an arm in
            // `OwnerAttention`, and neither file is this lane's.
            $this->recordOwnerActionNeeded($reply, 'reply_posting_unavailable');

            return $reply;
        });
    }

    /**
     * Forget an earlier attempt's failure, both halves of it, in one place.
     *
     * ⛔ **A CHOKEPOINT BECAUSE THE COLUMNS MUST NEVER BE CLEARED APART**
     * (6724). ⚠️ **IT IS THREE COLUMNS SINCE 6820 AND THIS DOCBLOCK SAID TWO** —
     * `publish_unconfirmed_at` joins them, and it is the one whose survival is
     * worst: a row that published on its second attempt, still carrying *"we do
     * not know whether it went up"*, is a reply live on a public listing that
     * this application is refusing to publish. `error_message` was nulled in
     * three methods —
     * {@see self::recordSuggestion()}, {@see self::approve()} and
     * {@see self::markPosted()} — and `provider_declined_at` and
     * `publish_unconfirmed_at` now have to be nulled in exactly the same three.
     * Three separate triples of lines is three chances to clear one and not the
     * others, and the row that results is the worst one this slice can produce:
     * a reply live on a public listing, or freshly re-approved and in flight,
     * still telling its owner that Google refused it. `markPosted()`'s own
     * docblock already argued this for the single column and the argument
     * trebles with the third.
     *
     * ⛔ **AND IT IS FOUR CALLERS SINCE 7120, NOT THREE — THE SENTENCE ABOVE IS
     * KEPT AND DATED.** {@see self::reconcileUnconfirmedPublication()} is the
     * fourth, and it is the only one whose *reason* for clearing is evidence
     * rather than a decision: the other three clear because somebody acted on
     * the reply; this one clears because the provider was read and reported no
     * owner reply on the review at all. The chokepoint argument is unchanged
     * and simply gains a caller — what would go wrong without it here is the
     * same thing, a settled row still telling its owner *"we do not know
     * whether it went up"*.
     *
     * ⚠️ **IT DOES NOT SAVE.** Every caller is already inside a transaction
     * assembling other columns on the same row, and a `save()` here would make
     * two round trips out of one and put a half-written row between them.
     */
    private function clearPostingFailure(Reply $reply): void
    {
        $reply->error_message = null;
        $reply->provider_declined_at = null;
        $reply->publish_unconfirmed_at = null;
    }

    /**
     * Say on the owner's own feed that a reply of theirs went up.
     *
     * ⛔ **`AutopilotActionType::ReplyPosted` SHIPPED WITH THE STAGE 0 SCHEMA
     * AND THIS IS ITS FIRST WRITER** (7134, 7222). Decision 1688 parked the
     * case as *"reserved for a real publish"* while `PostReplyJob` could only
     * hand off, and `ReplyPosted`'s own docblock still explains at length why a
     * **draft** may not be filed under it. That argument was read for months as
     * though it also excused the case having no writer at all — so from the day
     * `execute()` began publishing (2026-08-11), **a reply reaching Google was
     * the one thing in this whole flow the owner's feed never mentioned**: a
     * draft appears, an approval appears, a failure appears, and the success is
     * silent. `29` §2 rule 42 wants every automated action in the feed, and this
     * is the action.
     *
     * ⚠️ **IT IS HERE RATHER THAN ON `PostReplyJob`, AND THAT IS NOT WHERE THE
     * NEIGHBOURS PUT IT.** `AutopilotJob::activityAction()` is how a job speaks
     * to the feed and `PostReplyJob`'s returns `null`; filing from there would
     * make the item depend on the job's own view of what happened, which is the
     * instance loaded before an HTTP round trip. {@see self::markPosted()} is
     * the only writer of `posted`, it decides under a row lock, and 6820 already
     * established that the lock is the arbiter. **The feed row and the audit row
     * now come from the same decision**, which is the property that stops them
     * disagreeing.
     *
     * ⚠️ **NO TITLE, DELIBERATELY.** `ReplyPosted` is not in
     * `ActivityService::MAY_CARRY_ITS_OWN_TITLE` and must not be added: the one
     * thing a title here could add is *which* review, and a review is a member
     * of the public's words — 6293's rule, and the field this screen prints
     * verbatim onto an append-only table.
     *
     * ⚠️ **THE PROVIDER'S REPLY ID IS NOT IN THE BAG.** It is a Google handle,
     * `metadata` is never rendered (`OwnerAttention`), and the audit entry
     * already carries the receipt for whoever is reconstructing a publication.
     * A second copy in a second table buys nothing and is one more place a
     * vendor identifier has to be erased from.
     */
    private function recordReplyPosted(Reply $reply): void
    {
        $this->activity->record(
            AutopilotActionType::ReplyPosted,
            (int) $reply->review()->firstOrFail()->location_id,
            [
                'reply_id' => (int) $reply->id,
                'review_id' => (int) $reply->review_id,
            ],
        );
    }

    /**
     * File the owner-facing feed item both failure writers owe.
     *
     * Hoisted out of {@see self::markPostingUnavailable()} when
     * {@see self::markDeclinedByProvider()} split off it (6725), so the two
     * cannot drift into filing different items — or into one of them filing
     * none, which is the way a reply stops being mentioned anywhere at all.
     *
     * ⚠️ **THE ITEM IS UNREVISED AND IS STILL WRONG FOR ONE STATE** (6533):
     * `AutopilotActionType::OwnerActionNeeded` is filed unconditionally, and for
     * a reply blocked by the platform switch there is no owner action.
     *
     * ⛔ **IT WAS OFFERED TO THE SLICE THAT OWNS BOTH FILES AND REFUSED IN
     * WRITING — 7226, AND THE OLD SENTENCE'S "ONE-LINE CHANGE" IS THE PART TO
     * STOP BELIEVING.** The discrimination is trivially *derivable* here —
     * `PostReplyJob::canExecute()` reads `gbp.zernio_enabled` **before** it
     * looks at the connection, so a false switch and the `integration_disabled`
     * arm are the same fact and this method could ask the registry itself. What
     * is not one line is **the sentence**. There is no case in
     * `AutopilotActionType` for *"we could not do a thing and there is nothing
     * for you to do"* about a reply. `ReplyFailed` looks like it and is not:
     * its title promises *"we will try again"*, and {@see ReplyPublicationState}
     * has already ruled in writing that **no case may promise a future post**.
     * ⛔ **THE CLAUSE *"`PostReplyJob` is dispatched once by `approve()`,
     * nothing re-dispatches it"* IS SUPERSEDED AND IS KEPT HERE BECAUSE THE
     * CONCLUSION IS UNCHANGED** (11040):
     * `reviews:retry-stranded-replies` re-dispatches, **once**, and only for a
     * row about which *"this is not on Google"* is provable — so *"we will try
     * again"* is still a promise this application cannot keep for the four
     * states the sweep refuses, and `ReplyFailed` is still the wrong case. Taking it means rewriting an
     * owner-facing title and flipping `needsOwner()` in the same change — which
     * is a slice, not a line.
     *
     * ⚠️ **AND THE OWNER IS NOT MISLED ABOUT THE FACT, ONLY ABOUT THE FLAG.**
     * `ReplyPublicationState::PublishingOff` already renders the honest
     * sentence on the reply queue, which is the screen this state belongs to.
     * What is wrong is one feed row carrying `needsOwner()`. Hoisting stays
     * useful for whoever takes it.
     *
     * ⛔ **THE REASON IS THE CALLER'S BECAUSE THE SENTENCE IS DIFFERENT** (6824).
     * It was a literal here, and a third writer arrived whose outcome is not the
     * other two's: `OwnerAttention` renders `reply_posting_unavailable` as *"We
     * wrote a reply to one of your reviews and could not post it"*, and for an
     * unanswered attempt **nobody knows whether it was posted**. Reusing the
     * string would have told an owner a thing had not happened when it may well
     * have — 2332's family, said to the person in the one state where they are
     * the only party who can go and look. Both literals still live in this file,
     * which is what `Architecture\ActivityTest`'s enumeration reads.
     */
    private function recordOwnerActionNeeded(Reply $reply, string $reason): void
    {
        $locationId = (int) $reply->review()->firstOrFail()->location_id;

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            $locationId,
            [
                'reply_id' => (int) $reply->id,
                'review_id' => (int) $reply->review_id,
                'reason' => $reason,
            ],
        );
    }

    /**
     * The tenant-configured values this application substitutes into a reply.
     *
     * ⚠️ THE TENANT'S NAMES, NEVER THE COMMENT AND NEVER THE REVIEWER'S DISPLAY
     * NAME (1739). What the reviewer *wrote* must still be matched — a model
     * parroting "we will refund you" back out of a review is the case the
     * guardrails exist for — and their chosen *name* is no different: it is a
     * string a stranger picked, and exempting it let them switch the rule off.
     * `ReplyGenerator::draft()` degrades a hostile label at source instead.
     *
     * The set itself lives on `ReplyGuardrails` so this and the generator cannot
     * pass different lists (1741). The two `find()` calls stay because a `Review`
     * is all this method is given; they are two primary-key lookups per write,
     * on a path that has just made an HTTP call to a model.
     *
     * @return list<string>
     */
    private function substitutedValues(Review $review): array
    {
        return ReplyGuardrails::tenantValues(
            Business::query()->find($review->business_id),
            Location::query()->find($review->location_id),
        );
    }

    private function assertGoogleReview(Review $review): void
    {
        if ((int) $review->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'That review belongs to another tenant.',
            );
        }

        if ($review->source !== ReviewSource::Google) {
            throw new InvalidArgumentException(
                'Reply generation is for Google reviews only. First-party feedback uses the destination invite path.',
            );
        }
    }

    private function assertOwned(Reply $reply): void
    {
        if ((int) $reply->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'That reply belongs to another tenant.',
            );
        }
    }
}

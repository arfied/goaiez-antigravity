<?php

declare(strict_types=1);

namespace App\Livewire\Account;

use App\Enums\ReplyPublicationState;
use App\Enums\ReplyStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\Reply;
use App\Services\Billing\Subscriptions;
use App\Services\Gbp\GbpConnections;
use App\Services\Links\BookingLink;
use App\Services\Reviews\ReplyPublicationStatus;
use App\Services\Reviews\ReviewReplies;
use App\Services\Visibility\VisibilitySyncHistory;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Reply approval queue (`17` GBP-05) — edit, approve, or skip, and the list of
 * approved replies that are not on Google (1738).
 *
 * Under More in the owner's nav. Approving queues `PostReplyJob`.
 *
 * ⛔ **THIS SAID `PostReplyJob` "TODAY ALWAYS HANDOFFS — GbpClient HAS NO
 * CONFIRMED CREATE-REPLY ENDPOINT" AND HAD SAID IT FOR TEN DAYS — CORRECTED
 * 2026-08-21 (6521).** Decision **2330** confirmed
 * `POST /v1/inbox/reviews/{reviewId}/reply` against Zernio's raw OpenAPI
 * document on 2026-08-11 and `PostReplyJob::execute()` has published ever
 * since. The handoff path survives because `29` § 2 rule 44 requires the review
 * engine to run with zero GBP access — it is the disconnected, disabled and
 * unapproved outcome, not the only outcome. **A stale paragraph in the file a
 * reader trusts is what makes the code beside it read as considered**
 * (CLAUDE.md 314–316), and this docblock had already been corrected once for
 * exactly that, two paragraphs down.
 *
 * ⛔ **THAT HALF STOPPED BEING TRUE ON 2026-08-28 AND THE SENTENCE IS KEPT
 * BECAUSE IT IS WHAT EVERY STRING ON THIS SCREEN WAS WRITTEN AGAINST** (11040).
 * It read: *"⚠️ **AND NOTHING RE-DISPATCHES A REPLY THAT DID NOT LAND — THAT
 * HALF IS STILL TRUE.** `ReviewReplies::approve()` dispatches once. No
 * scheduler retries it, nothing watches for `gbp.zernio_enabled` being switched
 * on or for a location being reconnected."* `reviews:retry-stranded-replies`
 * runs every fifteen minutes and is exactly that watcher —
 * `App\Console\Commands\RetryStrandedReplies`, named in prose rather than
 * through an `{@see}` because Pint would import a console command into a
 * Livewire component to resolve the link.
 *
 * ⚠️ **WHAT SURVIVES WHOLE IS THE HALF THAT CARRIES THE RULE.** The sweep gives
 * each approved reply **one** automatic attempt per owner decision, bounded by
 * `replies.publish_retry_dispatched_at` — and it refuses every row about which
 * *"this reply is not on Google"* is unprovable, which is four of the five
 * cases a card can carry. **So approving again is still the retry** for a reply
 * Google declined, for one whose attempt went unanswered, for one this
 * platform's own worker killed mid-flight, and for one the sweep has already
 * had its turn at (1755, 6524). `PostReplyJob`'s failure message still promises
 * the owner the reply *"can be edited and approved again"*, and this list is
 * still the surface that makes that promise reachable.
 *
 * ⛔ **AND NOT ONE STRING BELOW MOVES INTO THE FUTURE TENSE ON THE STRENGTH OF
 * THIS.** 6523 refused *"waiting to post"* because it would name a queue that
 * did not exist; a queue existing on a branch is not a queue anybody has
 * watched run in production, and 7048 records the heading as the owner's to
 * rule on and deliberately unchanged. The question is raised at 11044.
 *
 * ⚠️ **NEITHER LIST MAY PROMISE A FUTURE POST.** 1747 removed one such
 * promise from `approve()`'s toast; see {@see ReplyPublicationState} for why
 * every string here is in the past or present tense.
 *
 * ⛔ **AND THE SECOND LIST IS NOT *"approved replies that are not on Google"*,
 * WHICH IS WHAT THE FIRST LINE OF THIS DOCBLOCK STILL CALLS IT** (6947). It is
 * every `Approved` row this application has not recorded as posted, and one of
 * `ReplyPublicationState`'s five cases —`PublishUnconfirmed` — is precisely the
 * row where that is not the same thing. The old line is kept because it is what
 * every string on the screen was written against; the heading it describes is
 * ruled by 6523 and is raised rather than changed.
 *
 * ⛔ **AND BOTH "FIVE" COUNTS ABOVE BECAME SIX ON 2026-08-28 — KEPT AND DATED
 * ON `ReplyPublicationState`'s OWN RULE** (11200–11202).
 * {@see ReplyPublicationState::NotEntitled} is the sixth, and it is the first
 * case whose subject is the **account** rather than the reply, the integration
 * or the listing: `Subscriptions::isEntitled()`'s answer, asked once for the
 * whole list by {@see self::publicationStates()} and handed down.
 *
 * ⛔ **"IT GROWS A FIFTH MEMBER" WAS WRONG AND IS CORRECTED HERE — 11452.**
 * The sweep does refuse it, and that is not what puts a reply in the set where
 * the button is the only path left. `NotEntitled` is **transient**, exactly
 * like `PublishingOff` and `NotConnected`: the entitlement gate skips without
 * touching the column, so the sweep takes the reply the hour the plan resumes.
 * **The four situations do not grow by one** (11365). ⛔ **What IS true is that
 * approving again is the retry only AFTER the plan is running:**
 * `PostReplyJob::canExecute()` refuses a lapsed account whoever pressed the
 * button, which is 11207 and is the owner's to confirm or reverse. The card's
 * own next step says *start your plan, then approve this reply again*, in that
 * order, for exactly this reason.
 *
 * ⚠️ A REAL `GET` IS REQUIRED. `Livewire::test()` never renders the layout (570).
 */
#[Layout('components.account.layout')]
final class ReplyQueue extends Component
{
    /** @var array<int, string> */
    public array $drafts = [];

    public function mount(ReviewReplies $replies): void
    {
        abort_if(Tenancy::id() === null, 403);

        // ⚠️ **ONE ARRAY FOR BOTH LISTS, KEYED BY REPLY ID.** `replies.id` is a
        // single sequence, so the two lists cannot collide, and a second public
        // property would be a second thing `approve()` has to unset. It also
        // means the retry on an approved card reaches the **existing**
        // `approve()` with no new entry point — 1755's decision costs no new
        // method and no new authorisation surface.
        foreach ($this->pending($replies)->merge($this->awaiting($replies)) as $reply) {
            $this->drafts[$reply->id] = $reply->text;
        }
    }

    public function approve(ReviewReplies $replies, int $replyId): void
    {
        abort_if(Tenancy::id() === null, 403);

        $reply = $replies->find($replyId);
        abort_if($reply === null, 404);

        $text = trim($this->drafts[$replyId] ?? $reply->text);

        // Read before the write, because `approve()` moves it. A row that is
        // already `Approved` is 1755's retry rather than a first decision, and
        // the two owe the owner different sentences.
        $wasAlreadyApproved = $reply->status === ReplyStatus::Approved;

        // ⛔ READ BEFORE THE WRITE FOR THE SECOND TIME, AND THIS ONE IS READ
        // BECAUSE `approve()` DESTROYS IT (6948). `clearPostingFailure()` nulls
        // `publish_unconfirmed_at` inside the transaction — it has to, because
        // `PostReplyJob`'s pre-vendor guard reads it and a surviving stamp would
        // make the reply permanently unpublishable (6838) — so after this line
        // there is no way left to tell that the earlier attempt was never
        // answered, and the toast below is the last surface that can say so.
        $publishWasUnconfirmed = $reply->publish_unconfirmed_at !== null;

        try {
            $replies->approve($reply, $text, $this->actor());
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        // ⚠️ **NOT UNSET ANY MORE, AND THAT IS THE RETRY WORKING.** The row
        // does not leave the screen on approval now — it moves to the second
        // list — so dropping its draft here would blank the textarea the owner
        // is about to edit and re-approve. `render()` would refill it from the
        // row, but only after a round trip, and only with the saved text.

        // ⚠️ THIS PROMISED A SEND NOTHING PERFORMS (1747), AND THEN SAID THE
        // OPPOSITE OF THE TRUTH FOR TEN DAYS (6522). It read *"we will post it
        // when Google posting is available"* until 1747 replaced it with
        // *"posting to Google is not switched on"* — which decision **2330**
        // falsified on 2026-08-11: `PostReplyJob` publishes, so a connected
        // tenant was told nothing had been sent about a reply now live under
        // their name on a public listing. That is worse than the promise it
        // replaced, because 2332 forbids this application claiming a reply is
        // published and says nothing about claiming one is not.
        //
        // What is true at this instant, for every tenant, is that the decision
        // is recorded and the reply is not on Google — the job has not run. So
        // the toast states that and points at the list where the answer will
        // be, which is the half 1747 could not write because the list did not
        // exist.
        //
        // ⛔ **AND THE RETRY STRING WAS THE SAME FALSEHOOD 6522 REMOVED FROM THE
        // OTHER ONE, ON THE ONE ROW THAT CAN CARRY IT** (6948). *"Approved
        // again. It is still not on Google."* is a claim about a third party's
        // listing, and for a reply whose attempt reached Zernio and was never
        // answered nobody here is entitled to it — the reply may be public under
        // the owner's name at the moment they read the toast. It said this to
        // nobody's knowledge because **no test pinned the string at all**: both
        // branches are pinned now.
        Toaster::success(match (true) {
            $publishWasUnconfirmed => 'Approved again. We never heard back about the earlier attempt, so check that review on Google.',
            $wasAlreadyApproved => 'Approved again. It is still not on Google.',
            default => 'Approved. It is not on Google yet — it now shows under “Approved, not yet on Google” below.',
        });
    }

    /**
     * Put the business's booking link into a draft they are about to publish
     * (T176 P7, R12–R14).
     *
     * ⚠️ **THE OWNER'S ACTION, NEVER THE MODEL'S, AND THAT IS THE POINT.**
     * `ReplyGenerator` is deliberately not taught to write a booking link into a
     * draft: `29`'s standing rule is that AI writes replies but never a review,
     * and R13's line is that the agent never invents a link. A generator that
     * sometimes emits a URL is one whose output has to be checked for invented
     * ones on every draft. A button the owner presses is a link they chose, in
     * text they can then edit character by character before approving.
     *
     * ⚠️ **NO GUARDRAIL PASS, FOR `approve()`'s REASON (1730).** What lands in
     * the textarea is the business's own address for their own scheduler; a
     * booking page at `refunds.example.com` is theirs to link to, and refusing it
     * would be the platform overruling a business owner on their own listing.
     *
     * ⚠️ **THE RAW DESTINATION, NOT A SHORT LINK (R14).** A review reply is
     * public text with no recipient and no conversation — `shortLinkFor()` cannot
     * be called without one and should not be: a per-send token on a page the
     * whole internet reads is not a per-send token. `BookingLink`'s docblock is
     * the full argument.
     *
     * ⚠️ **INSERTING TWICE IS A NO-OP, AND IT SAYS SO.** The obvious mis-click on
     * this screen is pressing the button again after scrolling; appending the
     * URL a second time would publish it twice on a public listing under the
     * business's name. Reporting it as an insertion that happened would be worse
     * than the duplicate.
     *
     * ⚠️ **AND WHAT THAT CHECK LOOKS AT IS THE WHOLE INSERTED LINE, WHICH IS
     * NARROWER THAN "IS THIS ADDRESS ALREADY HERE"** (1858's rule: a pass says
     * what it looked at). An owner who typed the URL themselves, or who kept the
     * address and rewrote the label, gets it a second time. That is the owner's
     * own text in front of them in an editable box, one keystroke from being
     * removed, and it is deliberately not worth a fuzzier match that could
     * silently refuse to insert a link they meant to add.
     */
    public function insertBookingLink(ReviewReplies $replies, BookingLink $booking, int $replyId): void
    {
        abort_if(Tenancy::id() === null, 403);

        // The same ownership check `approve()` and `skip()` make, for the same
        // reason: `$drafts` is public Livewire state, so the id arriving here is
        // whatever the browser sent. It decides nothing on its own.
        $reply = $replies->find($replyId);
        abort_if($reply === null, 404);

        $line = $booking->forReviewReply();

        // R13: no link set, no insertion — and the control that called this is
        // not rendered either, so this is the fail-closed path rather than the
        // ordinary one.
        if ($line === null) {
            return;
        }

        $draft = trim($this->drafts[$replyId] ?? $reply->text);

        if (str_contains($draft, $line)) {
            Toaster::info('That booking link is already in this reply.');

            return;
        }

        $this->drafts[$replyId] = $draft === '' ? $line : $draft."\n\n".$line;

        // Nothing is published by this — the reply still has to be approved, and
        // approving still publishes nothing while Google posting is off. Saying
        // "added" rather than "sent" is `22`'s rule that a string names what the
        // person controls.
        Toaster::success('Booking link added. Edit it, then approve when you are happy.');
    }

    public function skip(ReviewReplies $replies, int $replyId): void
    {
        abort_if(Tenancy::id() === null, 403);

        $reply = $replies->find($replyId);
        abort_if($reply === null, 404);

        try {
            $replies->skip($reply, $this->actor());
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        unset($this->drafts[$replyId]);
        Toaster::success('Skipped.');
    }

    public function render(
        ReviewReplies $replies,
        BookingLink $booking,
        ReplyPublicationStatus $publication,
        GbpConnections $connections,
        VisibilitySyncHistory $history,
        Subscriptions $subscriptions,
    ): View {
        abort_if(Tenancy::id() === null, 403);

        $pending = $this->pending($replies);
        $awaiting = $this->awaiting($replies);

        foreach ($pending->merge($awaiting) as $reply) {
            $this->drafts[$reply->id] ??= $reply->text;
        }

        return view('livewire.account.reply-queue', [
            'replies' => $pending,
            'awaiting' => $awaiting,

            // ⚠️ **DERIVED HERE, NEVER IN THE BLADE.** The view renders one
            // sentence per card and asks nothing; a template that reached the
            // registry per card would be both a query per row and a second
            // place the ladder's ordering could be got wrong.
            'publicationStates' => $this->publicationStates($awaiting, $publication, $connections, $subscriptions),

            'locations' => Location::query()->orderBy('id')->get()->keyBy('id'),

            // R13, and it is a boolean rather than the link because this screen
            // never renders the address — the owner sees it once it is in their
            // own draft. A business with no booking link set gets no control at
            // all here: no disabled button, no "add one in settings" prompt.
            'canInsertBookingLink' => $booking->forReviewReply() !== null,

            // ⛔ **"When a new Google review comes in, a suggested reply appears
            // here" IS A PROMISE ABOUT A PIPELINE, AND THE FIRST STEP OF IT CAN
            // BE BROKEN** (10120–10139). An owner whose review sync has been
            // failing read that sentence over an empty queue and had every
            // reason to conclude their customers had stopped writing. This is
            // what our last look actually did, account-wide —
            // `VisibilitySyncHistory::reviewAbsenceAcross()`, the same reader
            // `Account\Home` uses, over the sweep's own enumeration.
            //
            // ⚠️ **IT IS THE FIRST STEP AND NOT THE WHOLE CHAIN, WHICH IS SAID
            // OUT LOUD RATHER THAN IMPLIED.** A draft also needs
            // `autopilot_settings.auto_reply` on and `GenerateReplyJob` to have
            // run; neither is answered here, so a null means only that reading
            // is not what stopped it.
            //
            // ⛔ **BEHIND THE SAME SWITCH THE REST OF THE FEATURE IS BEHIND, AND
            // THE FIRST DRAFT WAS NOT — FOUND BY RENDERING THE SCREEN.** With
            // `gbp.zernio_enabled` false there is no run row for anybody, so the
            // reader answers `NeverRead` and this screen printed *"the first
            // read happens on its own"* about a feature that is switched off
            // platform-wide. **That is the defect this slice exists to close,
            // introduced by the slice closing it** — and no test the lane had
            // written could see it, because every one of them set the flag true.
            //
            // ⚠️ **`publishingIsSwitchedOn()` RATHER THAN A SECOND
            // `$defaults->value('gbp.zernio_enabled')`.** It is the same key,
            // this screen already reads it through that method for the
            // publication ladder, and a re-typed twin of a predicate is
            // `CLAUDE.md`'s own named failure. ⚠️ **Its NAME is narrower than
            // the key** — one registry row gates reading, publishing and the
            // whole Google reviews screen — and that is raised rather than
            // renamed from here.
            //
            // ⚠️ **SILENCE RATHER THAN A THIRD COPY OF "Not open yet".**
            // `Account\Connections` is the screen that says the feature is not
            // open, and `Account\Home` says it beneath the count; this screen's
            // note is about our READING, and when there is no reading to have an
            // opinion about it has nothing to say.
            //
            // ⚠️ **DERIVED HERE, NEVER IN THE BLADE** — the rule stated on
            // `publicationStates` above.
            'googleReadNote' => $publication->publishingIsSwitchedOn()
                ? $history->reviewAbsenceAcross($connections->usableLocationIds()->all())?->sentence()
                : null,
        ]);
    }

    /**
     * @return Collection<int, Reply>
     */
    private function pending(ReviewReplies $replies): Collection
    {
        return $replies->pendingAcrossTenant();
    }

    /**
     * @return Collection<int, Reply>
     */
    private function awaiting(ReviewReplies $replies): Collection
    {
        return $replies->awaitingPublicationAcrossTenant();
    }

    /**
     * One {@see ReplyPublicationState} per approved reply, keyed by reply id.
     *
     * ⚠️ **THREE QUERIES FOR THE WHOLE LIST, NOT THREE PER CARD** — one more
     * than before 10260–10269. The registry is asked once, every connection is
     * loaded once by `forLocations()`, and every abandoned run is loaded once
     * by {@see ReplyPublicationStatus::abandonedReplyIds()}, keyed by
     * `location_id` and by reply id respectively — the shape this loop needs
     * and the reason neither is asked in a loop.
     *
     * @param  Collection<int, Reply>  $awaiting
     * @return array<int, ReplyPublicationState>
     */
    private function publicationStates(
        Collection $awaiting,
        ReplyPublicationStatus $publication,
        GbpConnections $connections,
        Subscriptions $subscriptions,
    ): array {
        if ($awaiting->isEmpty()) {
            return [];
        }

        $switchedOn = $publication->publishingIsSwitchedOn();
        $byLocation = $connections->forLocations();
        $abandoned = $publication->abandonedReplyIds($awaiting->pluck('id')->map(fn ($id): int => (int) $id)->all());

        // ⚠️ **ONE ENTITLEMENT READ FOR THE WHOLE LIST, INSIDE THE `isEmpty()`
        // GUARD ABOVE** (11202). Every card here belongs to the tenant in
        // context, so the answer cannot differ between them — and a screen with
        // nothing awaiting publication must not pay a subscription query to
        // find that out. ⚠️ **`Business::query()->find()` rather than a
        // relation**, on `Publishing::attempt()`'s shape: this component is
        // reached with a tenant established and nothing else.
        $planIsRunning = ($business = Business::query()->find(Tenancy::idOrFail())) instanceof Business
            && $subscriptions->isEntitled($business);

        $states = [];

        foreach ($awaiting as $reply) {
            // ⚠️ NAMED ARGUMENTS, BECAUSE THREE ADJACENT BOOLEANS ARE
            // POSITIONALLY SWAPPABLE AND NOTHING WOULD NOTICE.
            $states[(int) $reply->id] = $publication->for(
                $reply,
                $byLocation->get($reply->review?->location_id),
                publishingIsSwitchedOn: $switchedOn,
                planIsRunning: $planIsRunning,
                lastAttemptAbandoned: isset($abandoned[(int) $reply->id]),
            );
        }

        return $states;
    }

    private function actor(): string
    {
        return 'user:'.(string) auth()->id();
    }
}

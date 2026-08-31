<?php

declare(strict_types=1);

namespace App\Services\Agent;

use App\Enums\AutopilotActionType;
use App\Enums\OutreachChannel;
use App\Enums\OutreachPurpose;
use App\Enums\ShortLinkPurpose;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\ReviewAsk;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Consent\ConsentService;
use App\Services\Feedback\FeedbackPages;
use App\Services\Messaging\MessageLog;
use App\Services\ShortLinks\ShortLinks;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Skill 13's bridge to SL-1 — T176 §2.2 row 13, patch P12.
 *
 * *"Customer expresses satisfaction, resolved thread → offer the /f/{slug} link
 * once — permit-checked, invite-ledger-deduped, tenant toggle."*
 *
 * ## ⛔ WHAT THIS IS, AND THE THREE THINGS IT IS NOT
 *
 * It hands the assistant **the address of the tenant's own feedback page**, so
 * that a happy customer can be pointed at the door every first-party review
 * comes through. That is the entire behaviour. It is emphatically not:
 *
 *  - **not a review, and no AI writes one.** The one rule this codebase will not
 *    bend: *"AI writes replies, posts and content — never a review."* Nothing
 *    here composes, suggests, seeds or pre-fills a rating or a word of one. What
 *    the model is given is a URL and permission to say it once.
 *  - **not a Google-review path, and cannot become one.** First-party reviews
 *    and Google reviews are two pipelines. This one ends at `/f/{slug}`; what
 *    happens on that page — the rating, the per-destination threshold, the
 *    picker — is `ReviewRouter`'s and is untouched here. **A Google review is
 *    never held, hidden, approved or moderated by anything, including this.**
 *  - **not a destination click and never reported as a review** (113). The short
 *    link this mints targets the feedback page, and a click on it means somebody
 *    opened a form. No platform gives us a completion callback, so nothing here
 *    claims one.
 *
 * ## ⛔ THE INVITE THRESHOLD DOES NOT LIVE HERE AND MUST NOT BE COPIED HERE
 *
 * `reviews.default_invite_threshold` (4, tenant-settable 1–5, per **destination**)
 * governs which public destinations are offered to somebody **after** they have
 * given a rating. This bridge runs **before** any rating exists — it offers the
 * page, not a destination — so the threshold has nothing to decide. Trustpilot's
 * forced `0` and Yelp's confirmed-listing-only rule (1161) are for the same
 * reason absent: **no destination is selected, seeded, defaulted or derived on
 * this path.** A reader who reaches for the threshold here has confused the door
 * with the room, and the copy on the feedback page is where that distinction is
 * explained to a business (2663).
 *
 * ⚠️ **AND BELOW-THRESHOLD CUSTOMERS STILL REACH TRIAGE (114) BECAUSE THIS PATH
 * CANNOT AFFECT THAT EITHER.** Everyone who follows this link reaches the same
 * page, and every rating it captures is kept.
 *
 * ## The gates, in the order they are asked
 *
 * Cheapest and most decisive first, and **the mint is last**, because a
 * `short_links` row is a side effect and the other five answers are free:
 *
 *  1. **A contact.** No contact, nobody to ask and no ledger to check.
 *  2. **This thread has not asked already** — the `review_asks` row.
 *  3. **The invite ledger** — `MessageLog::reviewInviteFor()`. If the ordinary
 *     review-invite path has already written to this person, the assistant does
 *     not ask a second time in a different voice. ⚠️ **This is the "deduped"
 *     half of §2.2's own sentence and it is deliberately the *broad* read**: any
 *     invite on any channel, not merely one about this thread.
 *  4. **A feedback page** for the thread's location — through
 *     {@see FeedbackPages::forLocation()} and never `FeedbackPage::query()`,
 *     which is decision 624's chokepoint and a lint in `ReviewsTest`.
 *  5. **A permit.** {@see ConsentService::decide()} — the same question, with the
 *     same purpose, that `AnswerAgentTurnJob` asks before it sends.
 *  6. **The mint.**
 *
 * ⚠️ **THE TENANT TOGGLE IS NOT IN THAT LIST, AND ITS ABSENCE IS DELIBERATE.**
 * `AssistantToggle::ReviewAsk` is and-ed on top by {@see AgentSkills}, which is
 * where every toggle in the assistant is read. Asking it here as well would give
 * a business that switched the ask off the owner-facing reason *"needs a review
 * link"* instead of *"you switched this off"* — P4's documented ordering
 * argument, and the two have different remedies.
 *
 * ## ⚠️ THE PERMIT IS ASKED TWICE AND THAT IS 398 RESPECTED, NOT VIOLATED
 *
 * This asks so that the link never reaches a prompt for somebody we may not
 * message; `AnswerAgentTurnJob` asks again at the send, and **that** is the one
 * that makes the refusal true. Deleting either leaves a real hole: without this
 * one a suppressed contact's short link is minted and put in front of a model;
 * without that one nothing stops the send. Both have their own test.
 */
final class ReviewAskBridge
{
    public function __construct(
        private readonly FeedbackPages $pages,
        private readonly MessageLog $messages,
        private readonly ShortLinks $links,
        private readonly ConsentService $consent,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * Whether skill 13 has something to offer on this thread (R13's question).
     *
     * ⛔ **NO MINT, AND THAT IS WHY THIS IS A SEPARATE METHOD RATHER THAN
     * `offerFor() !== null`.** {@see AgentSkills} asks this **every turn**, and a
     * grounding check that wrote a `short_links` row would leave one dead token
     * per turn for every thread of every tenant — a read pretending to be a read.
     */
    public function groundedFor(Conversation $conversation): bool
    {
        return $this->eligible($conversation) !== null;
    }

    /**
     * The link, minted, or null when any gate refused.
     *
     * ⚠️ **CALLED ONCE PER TURN AND ONLY WHEN SKILL 13 IS LIT.** Null here is an
     * ordinary outcome and is never surfaced to the customer: the assistant
     * simply answers their message without asking for anything.
     */
    public function offerFor(Conversation $conversation): ?ReviewAskOffer
    {
        $eligible = $this->eligible($conversation);

        if ($eligible === null) {
            return null;
        }

        [$customer, $page] = $eligible;

        $target = $this->feedbackUrl($page);

        if ($target === null) {
            return null;
        }

        $link = $this->links->mint($target, ShortLinkPurpose::FeedbackPage, $customer);

        return new ReviewAskOffer($this->links->urlFor($link), $link, $page);
    }

    /**
     * Spend the one ask, now that a message carrying it has left.
     *
     * ⛔ **CALLED AFTER THE SEND AND ONLY WHEN THE BODY ACTUALLY CARRIED THE
     * URL** — {@see ReviewAskOffer::appearsIn()}. See that method for why the
     * offer is not enough.
     *
     * ⚠️ **THE UNIQUE INDEX IS THE ARBITER AND A COLLISION IS NOT AN ERROR.**
     * Two turns of one thread racing is the case; the loser has nothing to do,
     * because the winner recorded the same fact. Returning null rather than
     * throwing keeps a send that already happened from failing a job after the
     * message is on a handset.
     *
     * ⛔ **NO MESSAGE TEXT IN EITHER BOOK.** The feed and the audit log carry the
     * conversation and the fact; the words live on the `outreach_messages` row
     * the sender wrote, which is the one record of them.
     */
    public function record(Conversation $conversation, ReviewAskOffer $offer): ?ReviewAsk
    {
        $businessId = Tenancy::idOrFail();

        if ((int) $conversation->business_id !== $businessId) {
            // A hydrated model from another tenant — the one thing neither the
            // global scope nor RLS can see, and `AgentThreadStates` refuses it
            // the same way.
            return null;
        }

        // The ordinary case, and the cheap one: this thread has already asked.
        if (ReviewAsk::query()->where('conversation_id', $conversation->getKey())->exists()) {
            return null;
        }

        try {
            // ⛔ **A SAVEPOINT, NOT A BARE `try`, AND POSTGRES IS WHY.** A unique
            // violation aborts the *whole* enclosing transaction — every
            // subsequent statement fails with `25P02` until it unwinds — so
            // catching the exception alone leaves the caller holding a dead
            // transaction and the real symptom lands somewhere else entirely.
            // `DB::transaction()` inside an open transaction issues a SAVEPOINT
            // and rolls back to it, which is what makes this recoverable. Found
            // by *recording twice on one thread is a no-op* failing with `25P02`
            // on a count three lines later rather than on the insert.
            $ask = DB::transaction(fn (): ReviewAsk => ReviewAsk::query()->create([
                'location_id' => $conversation->location_id,
                'conversation_id' => (int) $conversation->getKey(),
                'customer_id' => $conversation->customer_id,
                'short_link_id' => $offer->link->getKey(),
                'offered_at' => now(),
            ]));
        } catch (Throwable) {
            // The loser of a race. The winner recorded the same fact, so there
            // is nothing to do and nothing has gone wrong.
            return null;
        }

        $locationId = $conversation->location_id;

        $this->activity->record(
            action: AutopilotActionType::AssistantAskedForAReview,
            locationId: is_numeric($locationId) ? (int) $locationId : null,
            metadata: ['conversation_id' => (int) $conversation->getKey()],
        );

        $this->audit->record(
            action: 'agent.review_ask.offered',
            actor: 'assistant',
            entity: $ask,
            metadata: ['conversation_id' => (int) $conversation->getKey()],
        );

        return $ask;
    }

    /**
     * Gates 1 to 5, with no side effect.
     *
     * @return array{Customer, FeedbackPage}|null
     */
    private function eligible(Conversation $conversation): ?array
    {
        Tenancy::idOrFail();

        $customerId = $conversation->customer_id;

        if ($customerId === null) {
            return null;
        }

        $customer = Customer::query()->find($customerId);

        if (! $customer instanceof Customer) {
            return null;
        }

        // Gate 2 — this thread has asked already.
        if (ReviewAsk::query()->where('conversation_id', $conversation->getKey())->exists()) {
            return null;
        }

        // Gate 3 — the invite ledger. ⚠️ **ANY INVITE, ANY CHANNEL.**
        if ($this->messages->reviewInviteFor($customer) !== null) {
            return null;
        }

        $location = $this->locationFor($conversation);

        if (! $location instanceof Location) {
            return null;
        }

        $page = $this->pages->forLocation($location);

        if (! $page instanceof FeedbackPage) {
            return null;
        }

        // Gate 5 — the permit. ⚠️ **`Transactional`, MATCHING THE TURN THIS RIDES
        // INSIDE.** The link travels in the assistant's answer to a message the
        // customer sent; classifying the same message two ways depending on
        // whether it happens to end with a link would be a fiction, and
        // `AnswerAgentTurnJob`'s own T69 argument covers the whole turn.
        $decision = $this->consent->decide($customer, OutreachChannel::Sms, OutreachPurpose::Transactional);

        // ⚠️ **`isGranted()`, NOT A BARE `instanceof`** (wave 37 lane E,
        // decision 10505). The two are behaviourally identical today —
        // `SendDecision`'s own constructor guarantees `permit !== null` iff
        // `reason === null` — but `isGranted()` carries the
        // `@phpstan-assert-if-false !null $this->reason` annotation its
        // docblock says exists so a caller can reach `$decision->reason`
        // without re-checking, and a bare `instanceof` is the one shape that
        // reads past it. This method still returns only `null` on refusal —
        // see the class docblock's own "no mint, no side effect" rule below —
        // so `$decision->reason` is deliberately not carried any further than
        // this call.
        if (! $decision->isGranted()) {
            return null;
        }

        return [$customer, $page];
    }

    /**
     * Which shop this thread is about.
     *
     * ⚠️ **`AgentSkills::hasPlacesData()`'s RULE, APPLIED TO A DIFFERENT NOUN.**
     * The thread's location when it names one; a business with exactly one
     * location when it does not; **a refusal for a business with several**,
     * because sending somebody to the wrong shop's feedback page files their
     * rating against a branch they never visited and the customer cannot tell.
     */
    private function locationFor(Conversation $conversation): ?Location
    {
        $locationId = $conversation->location_id;

        if ($locationId !== null) {
            return Location::query()->find($locationId);
        }

        if (Location::query()->count() !== 1) {
            return null;
        }

        return Location::query()->first();
    }

    /**
     * The page's public address.
     *
     * ⚠️ **GUARDED ON THE ROUTE EXISTING, WHICH IS `FirstWeekPath`'s OWN
     * PRECEDENT.** The feedback routes are registered in `routes/web.php`; a
     * context that has not loaded them (a console command with a trimmed route
     * set) would otherwise throw from inside a queued job rather than declining.
     */
    private function feedbackUrl(FeedbackPage $page): ?string
    {
        return Route::has('feedback.show')
            ? route('feedback.show', ['slug' => $page->slug])
            : null;
    }
}

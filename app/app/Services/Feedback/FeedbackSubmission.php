<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\OutreachChannel;
use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Jobs\AnalyzeReviewJob;
use App\Jobs\Reviews\DraftRecoveryOutreachJob;
use App\Jobs\SendOptInConfirmationJob;
use App\Jobs\SendReviewInviteJob;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\Review;
use App\Services\Consent\ConsentCapture;
use App\Services\Consent\ConsentService;
use App\Services\Crm\CustomerEditor;
use App\Services\Reviews\PhiAnalysisConsent;
use App\Services\Reviews\ReviewRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One customer's feedback, persisted.
 *
 * ALL THREE WRITES OR NONE. A customer carrying consent and no feedback is a
 * state nothing downstream expects, and a review whose consent write failed is
 * worse — the person ticked a box and there is no record they did.
 *
 * WHY THIS IS SLICE C'S AND NOT SLICE D'S. BUILD-PLAN puts POST /api/feedback
 * and the customer upsert in slice D. But FPR-01's own acceptance criteria are
 * "submits successfully on mobile in under 30 seconds" and "consent is recorded
 * with proof when given, and absent when not", and ConsentService::record()
 * takes a Customer — so recording consent forces customer creation, and FPR-01
 * cannot be verified without a working POST. Slice D added the rest: the
 * duplicate collapse below, and the AnalyzeReviewJob dispatch that turns this
 * row into moderation, sentiment and themes.
 *
 * ROUTING RUNS HERE, IN THIS TRANSACTION (row 3 slice E). `routing_decision`,
 * `routed_destinations`, `routed_at` and `status` are written by ReviewRouter
 * before this method returns, and a below-threshold rating opens its recovery
 * conversation in the same commit. Do not move it behind the queue: `29` §12.1
 * makes that recovery path a build-failing test, and a queued routing job means
 * a window in which the review exists and the path does not.
 *
 * Dispatching analysis is still not routing. AnalyzeReviewJob writes
 * `moderation_flags`, `flagged_at`, `sentiment` and `themes`, and deliberately
 * touches neither `status` nor `routing_decision` — display fails closed on its
 * verdict, routing is indifferent to it (decision 348).
 */
final class FeedbackSubmission
{
    public function __construct(
        private readonly ConsentService $consent,
        private readonly ReviewRouter $router,
        private readonly CustomerEditor $customers,
        private readonly PhiAnalysisConsent $phiConsent,
    ) {}

    public function submit(FeedbackPage $page, Location $location, FeedbackInput $input): Review
    {
        return DB::transaction(function () use ($page, $location, $input): Review {
            $customer = $this->upsertCustomer($page, $location, $input);

            $duplicate = $this->recentDuplicate($location, $customer, $input);

            // CONSENT IS RECORDED EITHER WAY, and that asymmetry is the point.
            // Collapse is about the review; a person who resubmits the same words
            // but this time ticks a box has given a fresh grant with fresh proof —
            // a new timestamp, IP hash and user agent — and consent records are
            // append-only so exactly that trail survives. Skipping it would lose a
            // permission somebody actually gave.
            if ($duplicate instanceof Review) {
                if ($customer instanceof Customer) {
                    $this->recordConsent($page, $location, $customer, $input);
                }

                // ⚠️ THE SAME ASYMMETRY, ONE TABLE OVER, AND HERE IT IS LOAD-
                // BEARING RATHER THAN MERELY CONSISTENT (2079-2081). A person
                // whose first submission appeared to go nowhere types the same
                // words again — and if they tick the box this time, the review
                // their words landed on is the one that may now be analysed.
                // Recorded BEFORE reanalyseIfUnmoderated() below, which is what
                // re-dispatches the job that reads it.
                $this->recordPhiAnalysisConsent($page, $duplicate, $input);

                // SYMMETRIC WITH reanalyseIfUnmoderated() BELOW, FOR THE SAME
                // REASON (decisions 351, 356): resubmitting the same words is
                // what a person does after a submission that appeared to go
                // nowhere, and their retry is the natural repair trigger for
                // anything that did not happen the first time — routing
                // included. For an already-routed duplicate this is one
                // in-memory null check and an immediate return, no query, no
                // write. For a duplicate whose routing_decision is somehow
                // still null, it is the repair.
                $this->router->route($duplicate);

                $this->reanalyseIfUnmoderated($location, $duplicate);

                return $duplicate;
            }

            $review = Review::query()->create([
                'location_id' => $location->id,
                'customer_id' => $customer?->id,
                'source' => ReviewSource::FirstParty,
                'status' => ReviewStatus::Pending,
                'rating' => $input->rating,

                // Cleaned, the same as recentDuplicate() compares against — so
                // write and lookup share one normalisation. Storing the raw
                // value would agree with the lookup only by the accident of
                // Laravel's global TrimStrings pre-cleaning the HTTP path; any
                // other caller passing padded text would store what the lookup
                // can never match, and collapse would silently stop firing for
                // that customer.
                'comment' => $this->clean($input->comment),
                'reviewer_name' => $this->clean($input->name),

                // The customer's own timestamp for their own review. `ingest_method`
                // stays null on purpose: its vocabulary is api|email|manual and a
                // person typing into our own form is none of the three.
                'review_create_time' => now(),
            ]);

            if ($customer instanceof Customer) {
                $this->recordConsent($page, $location, $customer, $input);
            }

            // ⚠️ NO `$customer instanceof Customer` GUARD, AND THAT IS THE WHOLE
            // REASON THIS IS A SEPARATE TABLE (2079-2081, 2931). An anonymous
            // reviewer — no phone, no email, so no customer row — can still tick
            // this box, because it is not permission to contact them and there
            // is nobody to contact. `consent_records.customer_id` is NOT NULL,
            // so their undertaking would have had nowhere to live.
            $this->recordPhiAnalysisConsent($page, $review, $input);

            // ROUTED INSIDE THE TRANSACTION, NOT AFTER IT. Routing compares
            // integers against two tables and calls nothing external, so there
            // is nothing to queue for — and queueing would open a window in
            // which this review exists and the customer's recovery path does
            // not. `29` §12.1 makes "a below-threshold customer always reaches
            // triage" a build-failing test, so that window is the one thing this
            // slice may not have.
            //
            // ORDER AGAINST AnalyzeReviewJob IS NOT AN ACCIDENT AND IS ALSO NOT
            // A DEPENDENCY. Routing never reads moderation_flags, sentiment or
            // themes (decision 348): if it waited on a verdict, an exhausted
            // monthly cap could strand a 2-star review short of triage, and the
            // build-failing test would pass in CI — where nothing is capped —
            // and fail in production.
            $this->router->route($review);

            // AFTER COMMIT, NOT INSIDE. The job loads the review by id on another
            // connection; dispatched inside the transaction it can start before the
            // insert is visible and find nothing. Slice D's own idempotency key
            // makes a redelivery harmless, but "harmless" is not "correct".
            AnalyzeReviewJob::dispatch(
                $location->business_id,
                $location->id,
                $review->id,
            )->afterCommit();

            // `17` FPR-04's email and SMS channels, and the same afterCommit
            // reasoning — it loads the review by id on another connection.
            //
            // ⚠️ DISPATCHED UNCONDITIONALLY, WITH EVERY GATE INSIDE THE JOB, AND
            // THAT IS DELIBERATE. A cheaper version checks "was this review
            // invited" here and skips the dispatch — which puts a second copy of
            // the invitation rule in the submission path, where it can disagree
            // with `ReviewInvites::eligible()`, which is the shape decision 381
            // and `ReviewInvites`' own docblock both warn about. The job costs a
            // queue row and returns `['invited' => false]`; that is cheaper than
            // two implementations of one rule.
            //
            // ⚠️ It is a no-op today for every tenant: `review_invite.email_
            // enabled` seeds **false** while open question H is unbuilt (714),
            // and `review_invite.sms_enabled` seeds **false** until the 10DLC
            // campaign is approved (1603). The dispatch ships anyway rather than
            // waiting, because a sender with no caller is decision 272's shape
            // and this is the caller.
            SendReviewInviteJob::dispatch(
                $location->business_id,
                $location->id,
                $review->id,
            )->afterCommit();

            // The other half of the same fork — T176 P15. A rating at or below
            // the triage threshold reaches a conversation the owner has to work,
            // and until now it reached them with the customer's complaint and no
            // words to answer it. This drafts those words; it sends nothing.
            //
            // ⚠️ DISPATCHED HERE RATHER THAN FROM `ReviewRouter`, AND THE REASON
            // IS A SENTENCE THAT HAS TO STAY TRUE. That class's docblock says
            // routing "sends no message, spends nothing, and calls no vendor" —
            // which is why it may run inside this transaction and why it fails
            // open. A dispatch from inside it would falsify all three the moment
            // somebody read the claim rather than the code (2505's shape).
            //
            // ⚠️ AND UNCONDITIONALLY, WITH THE TRIAGE GATE INSIDE THE JOB, ON
            // `SendReviewInviteJob`'s stated reasoning immediately above: a
            // second copy of the triage rule in the submission path is a copy
            // that can disagree with the router's.
            DraftRecoveryOutreachJob::dispatch(
                $location->business_id,
                $location->id,
                $review->id,
            )->afterCommit();

            return $review;
        });
    }

    /**
     * A resubmission of the same words is a second chance at analysing them.
     *
     * COLLAPSE USED TO SWALLOW THE ONE DISPATCH THAT WOULD RECOVER THE REVIEW.
     * A `moderation_flags` of null means no model ever looked, which
     * `Review::displayable()` reads as not displayable — so from the customer's
     * side the submission went nowhere, and the natural thing to do next is type
     * the same words again. That second submission collapsed into the first and
     * dispatched nothing at all, which made the person's own retry the one input
     * guaranteed not to help.
     *
     * SAFE TO DO EAGERLY because `AnalyzeReviewJob`'s idempotency key is stable
     * per review: a review that is mid-analysis or already analysed still holds
     * its claim, so this dispatch opens no run row and spends nothing. A review
     * whose claim was released — the outage case — is exactly the one it
     * re-claims. It is the sweeper's job done sooner, by the person who noticed
     * first.
     *
     * Only when the column is still null: a flagged or clean review has a verdict
     * and re-running it would pay a second time for an answer already stored.
     */
    private function reanalyseIfUnmoderated(Location $location, Review $duplicate): void
    {
        if ($duplicate->moderation_flags !== null) {
            return;
        }

        AnalyzeReviewJob::dispatch(
            $location->business_id,
            $location->id,
            $duplicate->id,
        )->afterCommit();
    }

    /**
     * The same person saying the same thing again, inside a day.
     *
     * WHAT THIS CATCHES is a double-tap, a back-button repost, or a flaky
     * connection retried by hand — where a second row means a second AI call, a
     * second triage conversation for slice E to open, and a customer
     * double-counted in every future report.
     *
     * WHAT IT DELIBERATELY DOES NOT CATCH is a changed mind. A customer who
     * submits 2 stars and immediately adds a corrected 4-star has said two
     * different things and must not lose the second, so the match is on rating
     * *and* wording, not on the pair of them being close together. A genuine
     * repeat visit next month falls outside the window by construction.
     *
     * ANONYMOUS SUBMISSIONS NEVER COLLAPSE. With no customer there is no "same
     * person", and matching on text alone would merge two strangers who both
     * wrote "Great!" into one review — silently deleting one of them.
     *
     * THIS IS SELECT-THEN-INSERT, NOT A CONSTRAINT. Nothing in the schema makes
     * "same customer, rating and comment inside a day" unique, so two genuinely
     * concurrent submissions can both run this query, both find nothing, and
     * both insert — the window this method builds is sequential-only.
     *
     * `review_create_time` IS NULLABLE, and a null fails the `>=` comparison and
     * therefore never matches. A future first-party importer that leaves it
     * unset would be invisible to this dedupe rather than colliding with it —
     * failing open, which is the direction this particular mistake should fail.
     */
    private function recentDuplicate(Location $location, ?Customer $customer, FeedbackInput $input): ?Review
    {
        if (! $customer instanceof Customer) {
            return null;
        }

        $comment = $this->clean($input->comment);

        $query = Review::query()
            ->where('location_id', $location->id)
            ->where('customer_id', $customer->id)
            ->where('source', ReviewSource::FirstParty)
            ->where('rating', $input->rating)
            ->where('review_create_time', '>=', now()->subDay())
            // NOT ->latest(): Postgres sorts NULL first on a DESC order-by and an
            // ConventionsTest lint fails the build on any DESC ordering that is
            // not on `id`.
            ->orderBy('id', 'desc');

        // whereNull rather than where(..., null): the latter builds `= NULL`,
        // which is never true in SQL, so a rating-only resubmission with no
        // comment would never match and would collapse nothing. An if/else
        // rather than a discarded ternary: the ternary was correct only because
        // the builder mutates in place, and a future refactor toward an
        // immutable builder would silently stop collapsing rating-only
        // resubmissions with no warning from this line.
        if ($comment === null) {
            $query->whereNull('comment');
        } else {
            $query->where('comment', $comment);
        }

        return $query->first();
    }

    /**
     * Find or create the person, on phone first and then email.
     *
     * NO CONTACT MEANS NO CUSTOMER, and that is FPR-01's optional fields rather
     * than a failure: an anonymous submission produces a review with a null
     * customer_id and no consent record, because there is nothing to consent
     * about and nobody to consent. Slice E's recovery path for that person is
     * on-screen only, which BUILD-PLAN §2.6.5 question 4 already settled.
     */
    private function upsertCustomer(FeedbackPage $page, Location $location, FeedbackInput $input): ?Customer
    {
        $phone = $this->clean($input->phone);
        $email = $this->cleanEmail($input->email);
        $name = $this->clean($input->name);

        if ($phone === null && $email === null) {
            return null;
        }

        $existing = $this->findByIdentifier('phone', $phone)
            ?? $this->findByIdentifier('email', $email);

        if (! $existing instanceof Customer) {
            return Customer::query()->create([
                'location_id' => $location->id,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'first_seen_at' => now(),
                'last_activity_at' => now(),
            ]);
        }

        // THE IDENTIFIER BACKFILL IS NOT COSMETIC. A customer first seen by
        // email who later leaves a phone number would otherwise keep no phone —
        // and SMS consent recorded against them would be a record permit() can
        // never authorise, because it resolves the identifier off the customer.
        // Guarded by an existence check so it cannot collide with another
        // customer already holding that identifier; if two requests race, the
        // unique index throws and the whole transaction rolls back, which is the
        // honest outcome.
        if ($existing->phone === null && $phone !== null && ! $this->identifierTaken('phone', $phone)) {
            $existing->phone = $phone;
        }

        if ($existing->email === null && $email !== null && ! $this->identifierTaken('email', $email)) {
            $existing->email = $email;
        }

        if ($existing->name === null && $name !== null) {
            $existing->name = $name;
        }

        $existing->last_activity_at = now();
        $existing->save();

        // ⚠️ **A DELETED CONTACT WHO COMES BACK IS BROUGHT BACK** (1543), and the
        // seven-day undo does not bound this — that window is the owner's, and
        // this is the person themselves walking in. Deliberately NOT 1504's
        // archive rule, where a submission from an archived contact leaves the
        // owner's standing instruction alone: an archived contact is still in
        // the book and their review is visible on a profile the owner can open,
        // whereas a tombstone's is not. Leaving them deleted would attach the
        // review — and, below the invite threshold, a triage conversation with
        // an unhappy customer waiting for a reply — to a contact no screen
        // shows, which is decision 358's shape with a person at the end of it.
        //
        // The row is resolved by identifier above, which works only because a
        // tombstone keeps its email and phone where a merged-away row has them
        // cleared (1523). No branch is needed for the alternative: the unique
        // indexes make a second row impossible anyway.
        $this->customers->resurrect($existing, 'feedback_page:'.$page->slug);

        return $existing;
    }

    private function findByIdentifier(string $column, ?string $value): ?Customer
    {
        return $value === null
            ? null
            : Customer::query()->where($column, $value)->first();
    }

    private function identifierTaken(string $column, string $value): bool
    {
        return Customer::query()->where($column, $value)->exists();
    }

    /**
     * One consent record per ticked box — for the identifier the resolved
     * customer actually carries, never for the one merely typed this visit.
     *
     * StoreFeedbackRequest's docblock proves "a ticked box with no contact" can
     * never reach here — but it validates the submitted *input*, not the
     * resulting customer row, and upsertCustomer() can resolve the two apart.
     * Phone is looked up before email, so a third submission carrying both a
     * phone already owned by customer B and an email already owned by customer
     * A resolves to B and skips the email backfill (identifierTaken() is true),
     * leaving `$customer` with no email at all. Writing an email consent record
     * against a customer with no email is the exact dead record the docblock
     * says cannot exist — and the mirror case is worse: if `$customer` already
     * holds a *different* phone, an SMS record would authorise texting a number
     * the person submitting this form never saw the disclosure about.
     *
     * So the invariant is checked here, against the row that is actually about
     * to be written against, immediately before writing it — comparing the
     * customer's own stored identifier (after upsertCustomer()'s backfill, so a
     * legitimate "customer had none before" case still passes) to what this
     * submission typed. A mismatch skips only that channel's record; the review
     * and the customer are left intact, because losing one channel's consent is
     * recoverable by asking again and losing the feedback is not.
     *
     * CapturedBy::Platform, hardcoded, and that is what the case means rather
     * than a shortcut: this page is our surface, rendered by us, with our stored
     * proof — which is exactly what CaptureSurface::FeedbackPage->isSelfRendered()
     * asserts and what ConsentCapture::requirePlatformCaptureBeOurs() requires.
     *
     * DO NOT READ THIS AS SETTLING LANE A VERSUS LANE B. Deriving captured_by
     * from businesses.messaging_mode was considered and rejected — that would
     * reinterpret slice A's enum, whose docblock defines the split as capture
     * provenance rather than as which number sends. Decision 294 has the Lane A
     * shared-number problem open, and it belongs to row 4 with the
     * platform-scoped opt_outs table that resolves it.
     *
     * ⛔ **AND THE OPT-IN CONFIRMATION IS DISPATCHED FROM HERE, INSIDE THIS
     * LOOP, WHICH IS THE ONE PLACE IT MAY BE** (3269). The campaign filing
     * declares a welcome text after consent is captured, and `OptInConfirmations`
     * is what sends it. The obvious home is beside the two dispatches in
     * `submit()`, and that is wrong: **the identifier invariant above is per
     * channel and lives in this method**. A submission whose SMS record is
     * skipped — because the resolved customer carries a different phone from the
     * one typed this visit — must not be texted, and a dispatch one level up
     * cannot see that it was skipped. So the dispatch sits *after* the `continue`
     * that enforces it, on the SMS branch only, and a text is owed exactly when
     * a record was written.
     */
    private function recordConsent(FeedbackPage $page, Location $location, Customer $customer, FeedbackInput $input): void
    {
        $businessName = $location->businessName();

        $channels = [
            [OutreachChannel::Sms, $input->smsConsent, ConsentDisclosure::smsText($businessName), $this->clean($input->phone), $customer->phone],
            [OutreachChannel::Email, $input->emailConsent, ConsentDisclosure::emailText($businessName), $this->cleanEmail($input->email), $customer->email],
        ];

        foreach ($channels as [$channel, $given, $disclosureText, $submittedIdentifier, $customerIdentifier]) {
            if (! $given) {
                continue;
            }

            if ($customerIdentifier === null || $customerIdentifier !== $submittedIdentifier) {
                continue;
            }

            $this->consent->record(
                $customer,
                $channel,
                new ConsentCapture(
                    capturedBy: CapturedBy::Platform,
                    captureSurface: CaptureSurface::FeedbackPage,

                    // ExpressWritten rather than Express: `24` §3.2's wording is
                    // the full TCPA written-consent disclosure, and a record
                    // claiming less than the words it shows would understate its
                    // own evidence.
                    consentType: ConsentType::ExpressWritten,
                    disclosureVersion: ConsentDisclosure::versionFor($channel),
                    method: 'checkbox',

                    // `disclosure_text` alongside the version, per `24` §3.2:
                    // "store the proof, not just the boolean — exact wording,
                    // version ID, timestamp, URL, IP hash, user agent, checkbox
                    // state." ConsentCapture::REQUIRED_PROOF stays version-only
                    // by slice A's settled design (not reopened here), but
                    // nothing stops carrying the words too: a version is only a
                    // pointer, and a dispute years later is easier to answer
                    // with the words themselves sitting in the row. Read off
                    // ConsentDisclosure for the channel being recorded, so the
                    // stored words are exactly the ones the page rendered.
                    proof: $input->proof + [
                        'checkbox_state' => 'checked_by_user',
                        'disclosure_text' => $disclosureText,
                    ],
                ),

                // Who put the record there, for the audit log. Not the customer —
                // the surface. `29` §2 rule 42 wants the actor, and "a public
                // request to this page" is the honest one.
                actor: 'feedback_page:'.$page->slug,
            );

            if ($channel === OutreachChannel::Sms) {
                // ⚠️ **`afterCommit()`, FOR THE TWO DISPATCHES IN `submit()`'s
                // OWN REASON**: the job loads the customer by id on another
                // connection, and dispatched inside the transaction it can start
                // before the insert is visible and find nothing. Here that would
                // mean a consented customer silently never confirmed.
                //
                // ⚠️ **AND EVERY GATE IS INSIDE THE JOB, DELIBERATELY.** A
                // cheaper version reads `sms.optin_confirmation_enabled` here and
                // skips the dispatch, which puts a second copy of one rule on the
                // submission path where it can disagree with the first — decision
                // 381's shape, and the reason `SendReviewInviteJob` is dispatched
                // unconditionally too. The dispatch costs a queue row.
                //
                // ⚠️ **SMS ONLY, AND EMAIL IS NOT AN OVERSIGHT.** The declared
                // message is a *text* on a 10DLC campaign; an email welcome is a
                // different artefact with a different regulator and a bounce
                // problem that is still open question H.
                SendOptInConfirmationJob::dispatch(
                    $location->business_id,
                    $location->id,
                    $customer->id,
                )->afterCommit();
            }
        }
    }

    /**
     * The reviewer's undertaking not to include health information.
     *
     * ⚠️ WRITTEN WHATEVER THE TENANT'S CLASSIFICATION IS, AND THE ALTERNATIVE
     * WAS WORSE (2937). Only a covered entity's page renders the box, so for
     * everybody else this is unreachable through the form and a ticked field
     * means a forged POST — which records a row that grants nothing, because
     * `PhiAnalysisConsent::withholds()` answers false for a tenant that is not a
     * covered entity before it ever looks for one. Adding a classification check
     * here would put a second reader of that column in a third file and make the
     * page and the writer able to disagree, for no protection at all.
     *
     * ⚠️ AND A TENANT RECLASSIFIED UPWARD LATER KEEPS THESE. A practice that
     * signs up as `pii` and is raised to `phi` next month has real undertakings
     * from the reviewers who gave them, rather than a table that starts empty on
     * the day the gate starts mattering.
     */
    private function recordPhiAnalysisConsent(FeedbackPage $page, Review $review, FeedbackInput $input): void
    {
        if (! $input->phiAnalysisConsent) {
            return;
        }

        // The surface, not the person — `29` §2 rule 42 wants the actor, and "a
        // public request to this page" is the honest one. Identical to
        // recordConsent()'s, deliberately: the two records are written by the
        // same POST and an audit reader comparing them should see one actor.
        $this->phiConsent->record($review, $input->proof, 'feedback_page:'.$page->slug);
    }

    private function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * The one identifier this boundary is safe to normalise on the spot.
     *
     * Decision 293 defers normalisation to row 4's inbound webhook boundary,
     * on the reasoning that every internal read and write already derives
     * from `ConsentService::identifierFor()`, so they agree by construction.
     * That reasoning does not reach here: the hosted feedback page is itself
     * an inbound boundary — a person typing free text — and "Sam@Example.com"
     * then "sam@example.com" would otherwise become two customer rows with
     * a split consent history between them.
     *
     * Email only. Lowercasing is safe because local-parts are
     * case-insensitive in every practical sense, the unique index is on the
     * stored value, and every downstream read sees what was stored. Phone is
     * deliberately left alone: normalising a number needs a country context
     * this form does not have, and a half-normalisation that produces a value
     * row 4's sender does not recognise is worse than leaving it as typed.
     */
    private function cleanEmail(?string $value): ?string
    {
        $cleaned = $this->clean($value);

        return $cleaned === null ? null : Str::lower($cleaned);
    }
}

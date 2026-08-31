<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\CaptureSurface;
use App\Enums\DataClassification;
use App\Enums\ImpersonationCapability;
use App\Enums\ProofHashDomain;
use App\Models\Business;
use App\Models\Review;
use App\Models\ReviewPhiConsent;
use App\Services\AuditService;
use App\Services\Consent\ConsentProof;
use App\Services\Feedback\ConsentDisclosure;
use App\Services\Impersonation\Impersonation;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Whether one review's words may reach a model, and the record that says so
 * (2079-2081, replacing decision 421's per-tenant withhold).
 *
 * ⚠️ THE WITHHOLDING SERVICE `PhiTest`'S OWN LINT ASKED FOR. That lint had grown
 * to eleven permitted readers of `businesses.data_classification`, three of them
 * carrying a byte-identical private `phiWithheld()`, and its note said plainly
 * what to do about it: *"the thing to extract is a withholding service that owns
 * the refusal, not an accessor that launders the read; both readers would then
 * be that service, and this list would shrink for a reason instead of by
 * relocation."* `AnalyzeReviewJob`, `GenerateReplyJob` and `ReanalyseReviews`
 * now ask this class; the list shrank by two.
 *
 * ⛔ `IngestKnowledgeSourceJob` KEEPS ITS OWN READ AND ITS OWN ENTRY, AND THAT
 * IS A RULING RATHER THAN AN OVERSIGHT (2938). It withholds the tenant's *own
 * uploaded documents* — a patient handout, an intake form, a spreadsheet
 * somebody exported without thinking. There is no reviewer in that path to ask
 * anything of, and therefore no consent to read: routing it through this class
 * would make it ask a question whose answer is always "no record exists", which
 * happens to give the right behaviour today and would silently acquire the wrong
 * one the moment anybody generalised the writer. Its withhold stays per-tenant
 * and unconditional.
 *
 * ⚠️ WHAT A RECORD HERE DOES NOT DO. It is not a BAA. It does not make a
 * provider we hold no agreement with a lawful recipient of health information
 * that arrives anyway — a reviewer who ticks the box and then writes about their
 * root canal has produced exactly the exposure rule 24 exists to prevent, and
 * this row does not cure it. And it cannot be relied on to have worked: the
 * undertaking is the reviewer's and the obligation stays ours. It reduces
 * exposure; it discharges nothing.
 */
final class PhiAnalysisConsent
{
    /**
     * The disclosure versions whose wording describes **drafting outbound copy**
     * (4466).
     *
     * ⛔ **`phi-analysis-2026-08-12.1` IS ABSENT AND THAT IS THE WHOLE POINT.**
     * Its words were *"software will read your comment to sort and summarise it
     * for the business"* — an inbound read, described honestly, and describing
     * nothing else. `DraftRecoveryOutreachJob` uses the same text to write a
     * message the business may send **back to the reviewer**, which that
     * sentence does not cover to any reader of it. A row captured under it may
     * still gate analysis, because analysis is exactly what it described.
     *
     * ⚠️ **AN EXPLICIT LIST RATHER THAN THE LIVE CONSTANT.** Spelling this as
     * `[ConsentDisclosure::PHI_ANALYSIS_VERSION]` would make every future bump
     * admit itself — including one that *narrowed* the wording back — and "do
     * these words describe drafting?" is the one question here nobody may answer
     * by construction. ⚠️ **The opposite risk is real and is pinned**: a bump
     * that forgets this list stops drafting silently for every covered entity,
     * so `ReviewPhiConsentTest` fails the build when the live constant is not a
     * member. The test forces a decision; it does not make one.
     *
     * @var list<string>
     */
    private const array DRAFTING_VERSIONS = [
        'phi-analysis-2026-08-16.1',
    ];

    public function __construct(
        private readonly AuditService $audit,
        private readonly Impersonation $impersonation,
    ) {}

    /**
     * The gate, in the one direction that matters: may this review's text go?
     *
     * ⚠️ FAILS CLOSED ON BOTH HALVES. A business row that cannot be read at all
     * answers "not a covered entity" — the same answer decision 421's inlined
     * read gave, because `?->` on a missing row yields null — and a covered
     * entity with no consent row answers "withhold". The second is the one that
     * carries the ruling: absence of a record is a refusal, never a permission.
     */
    public function withholds(int $businessId, int $reviewId): bool
    {
        if (! $this->handlesHealthInformation($businessId)) {
            return false;
        }

        return ! $this->existsFor($reviewId);
    }

    /**
     * The same gate for **drafting a message back to the reviewer** (4466).
     *
     * ⛔ **STRICTLY NARROWER THAN `withholds()`, AND IT MUST STAY THAT WAY.** A
     * consent row authorises the processing its stored wording described, and
     * nothing else — that is the entire reason 2079–2081 made this a record with
     * a version rather than a boolean. Rows captured under
     * `phi-analysis-2026-08-12.1` described an inbound read; drafting is not
     * one, so those rows gate analysis and refuse drafting, and the covered
     * entity gets `recoverySafeTemplate()` for that reviewer.
     *
     * ⚠️ **DO NOT "SIMPLIFY" THIS TO `withholds()`.** The two questions are
     * different questions that happen to share an answer for every row captured
     * from this commit onward, which is exactly the shape that gets collapsed by
     * somebody reading the code and not the wording. ⛔ **And never rewrite it to
     * admit the old version** (2081's rule, one table over): that is the safety
     * net being widened to approve of the thing it caught.
     */
    public function withholdsDrafting(int $businessId, int $reviewId): bool
    {
        if (! $this->handlesHealthInformation($businessId)) {
            return false;
        }

        return ! ReviewPhiConsent::query()
            ->where('review_id', $reviewId)
            ->whereIn('disclosure_version', self::DRAFTING_VERSIONS)
            ->exists();
    }

    /**
     * Whether a stored disclosure version covers drafting.
     *
     * Public only so the pin in `ReviewPhiConsentTest` can ask it without
     * reflecting into a private constant — a lint that reads a private is a lint
     * one refactor away from asserting about nothing.
     */
    public static function versionCoversDrafting(string $version): bool
    {
        return in_array($version, self::DRAFTING_VERSIONS, true);
    }

    /**
     * Is this tenant a covered entity?
     *
     * Public because `ReanalyseReviews` needs the two halves apart: it filters a
     * whole business's candidate reviews in SQL rather than asking `withholds()`
     * once per row, and it may only add that filter for a tenant it applies to.
     */
    public function handlesHealthInformation(int $businessId): bool
    {
        return Business::query()
            ->whereKey($businessId)
            ->first()?->data_classification === DataClassification::Phi;
    }

    public function existsFor(int $reviewId): bool
    {
        return ReviewPhiConsent::query()->where('review_id', $reviewId)->exists();
    }

    /**
     * The review ids this tenant's reviewers have consented to, as a subquery.
     *
     * A scoped Eloquent builder rather than a plucked array: `ReanalyseReviews`
     * sweeps up to a hundred reviews per business per tick, and the tenant
     * predicate has to survive being used inside `whereIn` — which it does, for
     * the reason that command's `exhausted()` already sets out at length.
     *
     * @return Builder<ReviewPhiConsent>
     */
    public function consentedReviewIds(): Builder
    {
        return ReviewPhiConsent::query()->select('review_id');
    }

    /**
     * Record one reviewer's undertaking, with its proof.
     *
     * MODELLED ON `FeedbackSubmission::recordConsent()`, WHICH IS THE LIVE
     * CALLER OF THE OTHER KIND. Same shape: the wording version and the words
     * themselves are read off `ConsentDisclosure` so the stored text is exactly
     * what the page rendered, `checkbox_state` is stamped here so `ConsentProof`
     * can refuse anything that is not a box a person ticked, and the actor is
     * the surface rather than the customer — "a public request to this page" is
     * the honest one for `29` §2 rule 42.
     *
     * ⚠️ THE PROOF IS VALIDATED BEFORE THE ROW EXISTS, NOT AFTER. `ConsentProof`
     * throws, and it throws inside the caller's transaction, which is what makes
     * an unproved record unrepresentable rather than merely unusual — the same
     * property `ConsentCapture` has and for the same reason.
     *
     * @param  array<string, mixed>  $proof  url, ip_hash, user_agent, locale.
     */
    public function record(Review $review, array $proof, string $actor): ReviewPhiConsent
    {
        // ⚠️ SUPPORT CANNOT AUTHOR CONSENT — `ConsentService::record()`'s line,
        // for its reason. An agent holding a live act-as session who opens a
        // tenant's own feedback page and submits it would otherwise manufacture
        // the one record that decides whether a patient's words go to a vendor.
        $this->impersonation->refuse(ImpersonationCapability::RecordConsent);

        $this->assertBelongsToTenant($review);

        $blob = $proof + [
            'checkbox_state' => 'checked_by_user',
            'disclosure_text' => ConsentDisclosure::phiAnalysisText(),
        ];

        // Constructed for its guards AND for what it returns — the scoped
        // array is what the column stores. CaptureSurface::FeedbackPage is the
        // truth here rather than a convenience: this box is only ever rendered
        // on the page we serve, so `isSelfRendered()` is what makes url, ip_hash
        // and user_agent mandatory rather than optional.
        //
        // ⚠️ **AND THE PERSON HERE IS A PATIENT.** The scoping matters more on
        // this table than on any of the other four: an unscoped hash joins a
        // named review of a healthcare tenant to whatever else saw that address
        // ({@see ProofHashDomain}).
        $blob = (new ConsentProof(
            $blob,
            CaptureSurface::FeedbackPage,
            'checkbox',
            ProofHashDomain::ReviewPhiConsents,
        ))->proof;

        return DB::transaction(function () use ($review, $blob, $actor): ReviewPhiConsent {
            $consent = ReviewPhiConsent::create([
                'review_id' => $review->id,
                'disclosure_version' => ConsentDisclosure::PHI_ANALYSIS_VERSION,
                'method' => 'checkbox',
                'proof' => $blob,
                'created_at' => now(),
            ]);

            // NO PROOF BLOB AND NO REVIEW TEXT in the metadata: the audit log is
            // read by more people than this table is, and the proof is one
            // lookup away through the entity reference.
            $this->audit->record('review.phi_analysis_consented', $actor, $consent, [
                'review_id' => $review->id,
                'disclosure_version' => ConsentDisclosure::PHI_ANALYSIS_VERSION,
            ]);

            return $consent;
        });
    }

    /**
     * Refuse to record an undertaking about somebody else's review.
     *
     * `ConsentService::assertBelongsToTenant()`'s check, one table over and for
     * its reason: `business_id` comes from the ambient tenant while `review_id`
     * comes from the passed model, so without this a record could name tenant
     * B's review while filing under tenant A. This is the *wrong tenant* case
     * CLAUDE.md warns RLS cannot catch — RLS would happily write the row, since
     * the business_id in it is the session's own.
     */
    private function assertBelongsToTenant(Review $review): void
    {
        if ($review->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That review belongs to another tenant. A reviewer\'s undertaking is filed '
            .'against the acting business, so this would record consent to analyse text '
            .'that business has never received.',
        );
    }
}

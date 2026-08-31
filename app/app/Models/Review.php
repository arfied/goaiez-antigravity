<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Contracts\TenantScoped;
use App\Enums\ReviewSentiment;
use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Enums\RoutingDecision;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A review, from either pipeline (DATA-MODEL §5.3).
 *
 * `source` decides which rules apply. First-party reviews flow through
 * routing, gating, and triage; Google reviews are ingested facts that are
 * never held, hidden, approved, or moderated (`29` §2 rule 1). Public
 * surfaces filter `status IN (approved, displayed)` (§5.14).
 *
 * THE @property BLOCK RESTATES casts()'S 14 NON-SCALAR ENTRIES (of 18 —
 * `rating`, `is_platform`, `display_on_website` and `display_on_facebook`
 * already match their raw database column type and need no help). Larastan
 * cannot see a cast declared via the `casts(): array` method —
 * `parseModelCastsMethod` is off, and its runtime fallback calls `getCasts()`
 * on an instance built with `newInstanceWithoutConstructor()`, which skips the
 * trait initialiser that merges `casts()` into `$this->casts` — so every
 * non-scalar cast column needs restating here or AnalyzeReviewJob's enum
 * comparisons and array writes type-check against the raw database column
 * instead.
 *
 * `themes` AND `moderation_flags` ARE `array`, NOT `list<string>`. Every
 * writer in this codebase — ModerationVerdict::forStorage(),
 * ReviewInsights::themeValues() — produces a `list<string>`, but nothing
 * enforces that shape on the jsonb column itself, and a `list<string>`
 * annotation that jsonb read-back could someday falsify would silence a real
 * error at every call site rather than catch one.
 *
 * @property ReviewSource $source
 * @property ReviewStatus $status
 * @property ?ReviewSentiment $sentiment
 * @property ?array<int|string, mixed> $themes
 * @property ?array<int|string, mixed> $moderation_flags
 * @property ?array<string, mixed> $raw_payload
 * @property ?RoutingDecision $routing_decision
 * @property ?array<int|string, mixed> $routed_destinations
 * @property ?Carbon $routed_at
 * @property ?Carbon $invite_deferred_at
 * @property ?Carbon $review_create_time
 * @property ?Carbon $review_update_time
 * @property ?Carbon $google_invite_sent_at
 * @property ?Carbon $approved_at
 * @property ?Carbon $flagged_at
 */
final class Review extends Model implements TenantScoped
{
    use BelongsToTenant;

    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /**
     * ⛔ **THIS LIST IS THE WHOLE OF IT, AND TWO ARTEFACTS HAVE CITED IT AS
     * PROTECTING COLUMNS IT HAS NEVER NAMED** (9088). `source`,
     * `google_review_id`, `display_on_website`, `moderation_flags`,
     * `flagged_at` and every routing column are **mass-assignable**, so
     * `Review::query()->create(['source' => 'google'])` writes a row
     * byte-for-byte indistinguishable from an ingested Google review, and
     * `$guarded` is between it and the table in no sense at all. Anything
     * describing this property as a layer over the two pipelines is describing
     * something else.
     *
     * ⚠️ **IT IS DELIBERATELY NOT WIDENED, AND THE THREAT MODEL IS WHY.** Mass
     * assignment is a protection against *untrusted input reaching `fill()`*,
     * and there is no such path here: both writers — `FeedbackSubmission` and
     * `GoogleReviewIngest`, named bare because a `{@see}` here becomes an
     * import on the next `composer lint` and a model may not import a service —
     * construct literal payloads key by key, no file under `app/Http/` names
     * `ReviewSource` at all, and
     * factories run unguarded by design. Adding `source` here would rewrite
     * both writers to buy a guard against a caller that does not exist, and the
     * next reader would cite *it* as the layer. **The layer that exists is
     * `GbpTest`'s single-writer chokepoint over the value**, plus the two
     * CHECKs that refuse moderating or routing a Google row once it is written.
     * `business_id` is what this list is actually for — the tenant boundary.
     *
     * @var list<string>
     */
    protected $guarded = ['id', 'business_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => ReviewSource::class,
            'status' => ReviewStatus::class,
            'sentiment' => ReviewSentiment::class,
            'rating' => 'integer',
            'is_platform' => 'boolean',
            'display_on_website' => 'boolean',
            'display_on_facebook' => 'boolean',
            'themes' => 'array',
            'moderation_flags' => 'array',
            'raw_payload' => 'array',
            'routing_decision' => RoutingDecision::class,
            'routed_destinations' => 'array',
            'routed_at' => 'datetime',
            'invite_deferred_at' => 'datetime',
            'review_create_time' => 'datetime',
            'review_update_time' => 'datetime',
            'google_invite_sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'flagged_at' => 'datetime',
        ];
    }

    /**
     * Reviews it is safe to show the public.
     *
     * ⚠️ THIS IS A FILTER, AND `29` §12.1 19.3 FORBIDS EMITTING A FILTERED
     * AGGREGATE RATING. The obvious use in slice G — "the average of
     * displayable()" — is precisely the prohibited thing: rule 5 requires
     * accurate schema only, never a filtered or 5-star-only aggregate, and
     * misleading review markup is a manual-action risk as well as an FTC one.
     * Use this to choose which reviews to *render*. Compute any rating average
     * from the unfiltered set.
     *
     * THREE INDEPENDENT CONDITIONS, ON PURPOSE. `status` is the pipeline column
     * and slice E owns it — routing writes `approved` and `in_triage`. If
     * moderation also wrote `status = flagged`, the two would contend for one
     * column and **job ordering would decide whether abuse is published.** So
     * withholding lives in its own pair of columns, and a routing bug that
     * writes `approved` still cannot make flagged content visible.
     *
     * THE THIRD CLAUSE IS THE ONE PEOPLE DELETE. `flagged_at IS NULL` alone
     * reads as "not flagged", but a review no model has ever looked at is also
     * not flagged — an exhausted monthly cap or a vendor outage leaves
     * `moderation_flags` null, and without this clause every review written
     * during an outage would publish unmoderated. Display fails closed;
     * routing (slice E) fails open, and the two are deliberately opposite.
     *
     * AND IT APPLIES TO FIRST-PARTY ROWS ONLY, WHICH IT DID NOT. The clause was
     * source-agnostic while `AnalyzeReviewJob` — the column's only writer —
     * correctly refuses to touch a Google row, so a Google review's
     * `moderation_flags` is null by construction and the scope named "reviews it
     * is safe to show the public" hid **one hundred per cent of them,
     * permanently**. Slice G would have rendered no Google reviews at all with
     * nothing visible to explain it. The repair that suggests itself — writing
     * `[]` on Google ingest — is worse than the bug: it records that a model
     * moderated a Google review, which is `29` §2 rule 1's pipeline confusion
     * written into the data. So the moderation requirement is conditioned on the
     * pipeline it belongs to. `flagged_at IS NULL` still applies to both, and a
     * CHECK constraint keeps it null on the Google side.
     *
     * THE EXEMPTION IS `source <> first_party`, NOT `source = google`, AND THAT
     * IS DELIBERATELY WIDER THAN THE CHECK CONSTRAINT. `ReviewSource` also
     * carries `facebook`, `yelp` and `apple`; none of them has an ingest path
     * today, and none would have a moderation verdict if it did, because this
     * pipeline is first-party text only. Requiring a verdict from a pipeline
     * that never runs for them would hide them all on the day somebody adds the
     * ingest, which is the bug above with a different platform's name on it. The
     * database CHECK stays narrow instead: it names Google because rule 1 names
     * Google, and a prohibition is only worth enforcing where a rule exists to
     * break. ⚠️ `29` §2 rule 3 forbids Yelp as an invite *destination*, which is
     * a different table and does not bear on displaying a Yelp review already
     * written.
     *
     * @param  Builder<Review>  $query
     */
    #[Scope]
    protected function displayable(Builder $query): void
    {
        $query
            ->whereIn('status', [ReviewStatus::Approved, ReviewStatus::Displayed])
            ->whereNull('flagged_at')
            ->where(function (Builder $query): void {
                $query
                    ->where('source', '!=', ReviewSource::FirstParty)
                    ->orWhereNotNull('moderation_flags');
            });
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /*
     * ⚠️ THERE IS DELIBERATELY NO `replies()` RELATION HERE (1733).
     *
     * It existed, it had no caller anywhere in `app/`, `resources/` or
     * `tests/`, and it was a hole straight through decision 1680's chokepoint:
     * `$review->replies()->create([...])` writes a `replies` row without
     * `assertGoogleReview()`, without the guardrail pass, without an activity
     * item and without an audit entry — and the lint that is supposed to keep
     * `ReviewReplies` the only writer never saw it, because it matches
     * `Reply::` and `new Reply`, neither of which appears in that expression.
     * So the chokepoint's own decision was not enforced by the thing written to
     * enforce it.
     *
     * Removed rather than kept-and-linted because it earned nothing: a relation
     * with no reader is decision 272's shape wearing an Eloquent hat. The lint
     * in `tests/Feature/Architecture/ReviewsTest.php` now also refuses
     * `->replies(` and `table('replies')`, so re-adding it turns the build red
     * with the reason attached rather than relying on this comment being read.
     */
}

<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\AutopilotActionType;
use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Models\Location;
use App\Models\Review;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The display queue (`17` FPR-05, row 3 slice G).
 *
 * The one place a human decides whether a first-party review appears on the
 * owner's own website. Auto-approval already exists and is not here: slice E's
 * `ReviewRouter` approves a 5-star when `auto_approve_5_star` is on (decision
 * 375), which is FPR-05's "auto above threshold if enabled". This is the "else
 * one-tap" half.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ A GOOGLE REVIEW CAN NEVER REACH EITHER METHOD, AND THE REASON IS SUBTLER
 * THAN THE RULE
 * ---------------------------------------------------------------------------
 * ⛔ **THAT HEADING IS TRUE OF THESE TWO METHODS AND WAS READ AS A STATEMENT
 * ABOUT THE COLUMN, WHICH IT NEVER WAS — CORRECTED 2026-08-24 (9240–9243).**
 * 9091(c) put it plainly: *"`ReviewDisplay::assertDecidable()` is the only
 * layer, and a raw `UPDATE` hiding a Google review is refused by nothing."*
 * The guard below reads an in-memory attribute, once, before a `forceFill()`;
 * `Review::query()->where(…)->update(['display_on_website' => false])`
 * consults no guard, fires no model event and reaches this class in no sense
 * at all. ✅ **What holds the column now is two `BEFORE UPDATE` triggers on
 * `reviews`** — `reviews_google_is_never_hidden` and
 * `reviews_source_never_leaves_google`, driven by `GoogleReviewHiddenTest`.
 * This paragraph is still the argument for the refusal; it was never the
 * mechanism.
 *
 * `29` §2 rule 1: Google reviews cannot be held, hidden, approved, or
 * moderated. Decision 386 draws the distinction this class depends on — that
 * "approved" forbids subjecting a Google review to an approval **decision**,
 * not the string `approved` appearing in a bookkeeping column. Slice I's
 * importer will write that string mechanically on ingest so
 * `Review::displayable()` lets Google rows through at all, and that is not a
 * decision anybody made about the review.
 *
 * This class is the decision. So it refuses a Google row outright, in both
 * directions: approving one would be an approval decision, and rejecting one
 * would be hiding it. Neither is ours to make.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ `display_on_website` WAS A DEAD COLUMN AND IS NOW THIS SERVICE'S OUTPUT
 * ---------------------------------------------------------------------------
 * It has existed since the reviews table was created, defaults `false`, and had
 * no writer anywhere — while `Review::displayable()`, the scope named for it,
 * never consulted it. So the feed had a choice between ignoring a column whose
 * name promises exactly what the feed does, or honouring one that is false on
 * every row in the database.
 *
 * Resolved by making approval set it: `displayable()` stays the *moderation*
 * gate (status, flagging, and that a first-party review was actually analysed —
 * decisions 345 and 358, untouched here), and `display_on_website` becomes the
 * per-review switch the widget reads on top of it. `ReviewRouter`'s
 * auto-approval sets it too, in the same attribute write, or every
 * auto-approved 5-star would be invisible in the feed — decision 358's shape
 * exactly, and the reason that one line is tested.
 *
 * `display_on_facebook` is left alone and stays dead. It belongs to social
 * posting, which no row has built, and giving it a writer here would be
 * inventing a surface to justify a column.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ WHAT THIS COSTS SLICE I, STATED HERE BECAUSE NOTHING ELSE WILL SAY IT
 * ---------------------------------------------------------------------------
 * Because the feed requires `display_on_website` and this service refuses to
 * set it on a Google row, **a Google review can never appear in the widget**.
 * That is free today — review sync does not exist and there are no Google rows
 * anywhere — and stops being free the moment slice I lands, when an owner with
 * 200 Google reviews sees none of them on their own website.
 *
 * The resolution is slice I's and it is not "route Google through this class".
 * Not showing a Google review in a business's own marketing widget is **not
 * hiding it under rule 1** — the rule governs the Google listing, and nobody is
 * entitled to appear on somebody's website. So slice I may set
 * `display_on_website` on ingest, mechanically, exactly as decision 386 has it
 * write `approved` mechanically. A test in `WidgetFeedTest` pins the current
 * behaviour so that change is deliberate rather than accidental.
 *
 * ⛔ **SLICE I LANDED AND THAT WHOLE SECTION IS THE PRE-SLICE-I READING — BOTH
 * ARE KEPT RATHER THAN ONE DELETED (4368's rule), BECAUSE THE ARGUMENT IS
 * STILL RIGHT AND ITS PREMISE HAS MOVED. Corrected 2026-08-24 (9240–9243).** `GoogleReviewIngest` writes
 * `display_on_website = true` on **every** ingested Google review, on its
 * insert path and again on its update path. So *"a Google review can never
 * appear in the widget"* is false, and — this is the half that matters —
 * **the platform's own baseline is now *show them all***.
 *
 * ⚠️ **AGAINST THAT BASELINE THE PARAGRAPH ABOVE STOPS COVERING THE CASE IT
 * WAS WRITTEN FOR.** *"Nobody is entitled to appear on somebody's website"* is
 * an argument about a **uniform absence**, and a uniform absence suppresses
 * nothing. Setting one row's column to `false` is not that: it removes **one
 * named review** from a surface where its siblings still appear, which is FTC
 * 16 CFR §465.7's subset-display shape and rule 1's *hidden* verb.
 * `WidgetTest`'s `min_stars_to_show` refusal draws the same line from the
 * other side and calls this column *"a per-review editorial act on one named
 * review"*.
 *
 * ✅ **The database now expresses exactly that distinction**, which is why it
 * could be built at all where 9091(c)'s CHECK could not: a Google review that
 * was never shown may stay unshown, and an `UPDATE` that takes a shown one
 * down is refused. Read the migration
 * `make_hiding_a_google_review_impossible` before arguing either half again.
 */
final class ReviewDisplay
{
    public function __construct(
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * Reviews waiting on the owner.
     *
     * FIRST-PARTY AND `pending` ONLY. A Google review is never actionable here
     * (see the class docblock), and anything already approved, rejected, in
     * triage or resolved has had its decision made.
     *
     * ⚠️ FLAGGED REVIEWS ARE EXCLUDED, AND THAT IS NOT THE SAME AS HIDDEN. A
     * review withheld by moderation is not waiting on the owner, because there
     * is nothing they can do about it — nothing in this codebase can clear
     * `flagged_at`, which decision 358's neighbourhood established when
     * `ReviewHeld`'s feed title was corrected for promising a say-so that does
     * not exist. Listing them here would rebuild that promise in a screen.
     *
     * @return Collection<int, Review>
     */
    public function pendingFor(Location $location): Collection
    {
        return Review::query()
            ->where('location_id', $location->id)
            ->where('source', ReviewSource::FirstParty)
            ->where('status', ReviewStatus::Pending)
            ->whereNull('flagged_at')
            // `id`, never `created_at`. 40 of 43 tables carry a nullable
            // `created_at` and Postgres sorts NULL *first* on a DESC order by,
            // so `latest('created_at')` would put undated rows ahead of every
            // dated one. Slice A's lint fails the build on anything else.
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Show this review on the owner's website.
     *
     * @throws InvalidArgumentException
     */
    public function approve(Review $review, string $actor): Review
    {
        $this->assertDecidable($review, 'approved');

        return $this->decide($review, $actor, display: true);
    }

    /**
     * Keep this review off the owner's website.
     *
     * NOT A DELETION AND NOT A MODERATION VERDICT. The review stays, the
     * customer's words stay, triage and routing are untouched — this says only
     * that the owner does not want it quoted on their own site, which is theirs
     * to decide about a review they solicited and host.
     *
     * @throws InvalidArgumentException
     */
    public function reject(Review $review, string $actor): Review
    {
        $this->assertDecidable($review, 'rejected');

        return $this->decide($review, $actor, display: false);
    }

    /**
     * Write the decision, the feed line and the audit row atomically.
     *
     * ALL THREE OR NONE, for the reason slice F's hand-off gives: they are three
     * views of one event, and a status change with no audit row is a decision
     * `29` §19.3 cannot account for. The transaction is also what makes decision
     * 379's `ShouldDispatchAfterCommit` do its job on the activity broadcast.
     */
    private function decide(Review $review, string $actor, bool $display): Review
    {
        return DB::transaction(function () use ($review, $actor, $display): Review {
            $review->forceFill([
                'status' => $display ? ReviewStatus::Approved : ReviewStatus::Rejected,
                'display_on_website' => $display,
                'approved_at' => $display ? now() : null,

                // An actor label, not a user id — the column's own migration
                // comment says so, because autopilot approves most reviews.
                'approved_by' => $display ? $actor : null,
            ])->save();

            $this->activity->record(
                $display ? AutopilotActionType::ReviewApproved : AutopilotActionType::ReviewHeld,
                (int) $review->location_id,
                // The review id and nothing else. No comment, no reviewer name:
                // the feed is owner-visible on every staff screen and the id is
                // enough to find the rest under scope.
                ['review_id' => (int) $review->id],
            );

            $this->audit->record(
                $display ? 'review.display_approved' : 'review.display_rejected',
                $actor,
                $review,
                ['location_id' => (int) $review->location_id],
            );

            return $review;
        });
    }

    /**
     * Refuse anything this service has no business deciding.
     *
     * @throws InvalidArgumentException
     */
    private function assertDecidable(Review $review, string $outcome): void
    {
        if ((int) $review->business_id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'That review belongs to another tenant. A display decision writes a '
                .'status, a feed item and an audit row against the acting business, so '
                .'this would record one business\'s decision under another\'s name.',
            );
        }

        if ($review->source === ReviewSource::Google) {
            throw new InvalidArgumentException(sprintf(
                'A Google review can never be %s. `29` §2 rule 1: Google reviews cannot '
                .'be held, hidden, approved, or moderated. Rule 1 forbids the *decision*, '
                .'not the `approved` string that slice I\'s importer writes mechanically '
                .'so displayable() lets Google rows through at all (decision 386).',
                $outcome,
            ));
        }

        if ($review->flagged_at !== null) {
            throw new InvalidArgumentException(
                'That review was withheld by moderation and is not the owner\'s to '
                .'publish. Nothing in this codebase can clear `flagged_at`, so approving '
                .'it would change a status and still show nothing — a control that looks '
                .'like it worked and did not.',
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Visibility;

use App\Contracts\PlacesClient;
use App\Enums\CompetitorAbsenceReason;
use App\Exceptions\PlacesBudgetExhausted;
use App\Exceptions\PlacesRequestFailed;
use App\Models\Competitor;
use App\Models\CompetitorSnapshot;
use App\Models\Location;
use App\Services\Places\PlaceSummary;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * The one reader/writer of `competitors` / `competitor_snapshots` (`28` §5.5).
 *
 * Proposes 3–5 peers from Places nearby-search (silent default, owner-confirmable
 * later). Normal surface never names them (196).
 */
final class CompetitorSignals
{
    public const MAX_COMPETITORS = 5;

    public function __construct(
        private readonly PlacesClient $places,
        private readonly VisibilitySyncHistory $history,
        private readonly ReviewLossDetection $reviewLoss,
    ) {}

    /**
     * Refresh the set for one location from Places and snapshot ratings.
     *
     * ⚠️ **PLACES FAILURES PROPAGATE — THEY USED TO BE SWALLOWED INTO A `0`.**
     * A budget exhaustion or a failed request returning "kept zero competitors"
     * is indistinguishable from "asked Google, there are none nearby", so the
     * job recorded `outcome: refreshed, competitors: 0` and closed `Succeeded`.
     * A permanently broken integration then reads healthy in `automation_runs`
     * forever, which is the one state an ops queue exists to make impossible.
     * The caller catches and says which of the two happened; this method's job
     * is to not destroy the distinction. `SyncSearchConsoleJob` treats its own
     * reading failures exactly this way.
     *
     * @return int number of competitors kept after refresh
     *
     * @throws PlacesBudgetExhausted when this tenant's daily Places ceiling is spent
     * @throws PlacesRequestFailed when Google refused or could not be reached
     */
    public function refresh(Location $location): int
    {
        Tenancy::idOrFail();

        $placeId = $location->google_place_id;

        if ($placeId === null || trim($placeId) === '') {
            return 0;
        }

        $subject = $this->places->details($placeId);

        if ($subject === null) {
            return 0;
        }

        // ⛔ **THE LOCATION'S OWN GOOGLE RATING IS RECORDED HERE, BEFORE THE
        // PEER LOOKUP, AND IT USED TO BE RECORDED AFTER IT** (6810). This is
        // the only writer of `locations.current_rating` on the platform — the
        // column the migration calls *"Google's own rating"*, which
        // `Admin\LocationSettings` renders and {@see self::compare()} reads —
        // and it sat inside the competitor transaction at the bottom of this
        // method, past three early returns. So a listing Google gives us a
        // rating for but **no `primaryType`** could never have that rating
        // recorded, however many nights the sweep ran: the peer search needs a
        // type and our own star count does not. Those accounts showed an
        // operator *"we do not know"* about a number we had been handed, which
        // is 2496's lesson pointed at a screen.
        //
        // ⚠️ **IT IS OUR FACT AND NOT THE PEER SET'S**, which is why it is no
        // longer in the peer set's transaction: a `PlacesBudgetExhausted` or a
        // refusal from `nearby()` below now leaves the rating updated rather
        // than discarding a good reading because a second call failed.
        //
        // ⚠️ **GOOGLE'S NUMBER, NEVER BLENDED WITH THIS PLATFORM'S** (`29` §2
        // rule 1). {@see \App\Services\Reviews\TrueRating} is the first-party
        // aggregate and the two pipelines never mix; nothing here reads a
        // `reviews` row.
        $this->recordOurOwnRating($location, $subject);

        if (
            $subject->latitude === null
            || $subject->longitude === null
            || $subject->primaryType === null
        ) {
            return 0;
        }

        $nearby = $this->places->nearby(
            $subject->latitude,
            $subject->longitude,
            $subject->primaryType,
            self::MAX_COMPETITORS + 1,
        );

        $others = array_values(array_filter(
            $nearby,
            static fn (PlaceSummary $candidate): bool => $candidate->placeId !== $subject->placeId,
        ));
        $others = array_slice($others, 0, self::MAX_COMPETITORS);

        return DB::transaction(function () use ($location, $others): int {
            // Drop auto-proposed peers that are no longer in the set. Owner-
            // confirmed rows survive a refresh that forgot them — confirmation
            // is a deliberate choice (silent default otherwise, §5.5).
            $keepIds = array_map(
                static fn (PlaceSummary $p): string => $p->placeId,
                $others,
            );

            Competitor::query()
                ->where('location_id', $location->id)
                ->where('source', 'auto')
                ->where('confirmed', false)
                ->when(
                    $keepIds !== [],
                    fn ($q) => $q->whereNotIn('place_id', $keepIds),
                    fn ($q) => $q,
                )
                ->delete();

            $now = CarbonImmutable::now();
            $kept = 0;

            foreach ($others as $peer) {
                $name = $peer->displayName ?? 'Nearby business';

                // ⚠️ `source` is set ON CREATE ONLY. It was in an
                // updateOrCreate() update payload, which stamped 'auto' back
                // over every row on every nightly refresh — including one an
                // owner had confirmed. That is the provenance the delete filter
                // twenty lines up keys off, so overwriting it would eventually
                // let a refresh delete a peer the owner had chosen. The name is
                // Google's and does get refreshed.
                $competitor = Competitor::query()->firstOrNew([
                    'location_id' => $location->id,
                    'place_id' => $peer->placeId,
                ]);

                if (! $competitor->exists) {
                    $competitor->source = 'auto';
                }

                $competitor->name = $name;
                // Google's listed website, refreshed with the name. Null stays null:
                // a peer with no site is a fact, not a blank to fill.
                $competitor->website_url = $peer->websiteUri;
                $competitor->save();

                // ⛔ **`$peer->userRatingCount ?? 0` UNTIL 2026-08-26, AND THE
                // FILE HELD BOTH ANSWERS AT ONCE.** Thirty-five lines below,
                // {@see self::recordOurOwnRating()} refused to overwrite a known
                // count with an absent one and said so in three paragraphs; here
                // the same absence was written down as *"this business has no
                // reviews"* — a fabricated fact about a third party, in a
                // tenant-owned table, indistinguishable ever after from a peer
                // who genuinely has none. Two spellings of one decision in one
                // file is what it looks like when nobody decided it once.
                //
                // ⚠️ **THE ANSWER IS NOT "NULL WHENEVER GOOGLE SENT NOTHING".**
                // Nearby Search's mask asks for `rating` and `userRatingCount`
                // together and Google omits either when it holds its default, so
                // both absent is a genuine zero and is stored as one.
                // {@see PlaceSummary::knownReviewCount()} is where that
                // corroboration lives, and it answers null only where the
                // response contradicts itself or the mask never asked.
                CompetitorSnapshot::query()->create([
                    'competitor_id' => $competitor->id,
                    'review_count' => $peer->knownReviewCount(),
                    'rating' => $peer->rating,
                    'captured_at' => $now,
                ]);

                $kept++;
            }

            return $kept;
        });
    }

    /**
     * `locations.current_rating` and `locations.review_count` — Google's own
     * two numbers about this business, and the only writer of either.
     *
     * ⚠️ **A NULL RATING LEAVES THE COLUMN ALONE RATHER THAN CLEARING IT.**
     * Google returns no rating for a listing with no reviews *and* for one it
     * simply did not include in the response, and the two are indistinguishable
     * from here. Writing `null` on the second would turn a number we knew last
     * night into *"we do not know"* — and `Admin\LocationSettings` renders both
     * as the same em dash, so nobody would ever find out.
     *
     * ⚠️ **`forceFill()` because both are guarded**, which is deliberate: they
     * are Google's numbers, so no mass-assigned payload from a form may reach
     * them.
     */
    private function recordOurOwnRating(Location $location, PlaceSummary $subject): void
    {
        if ($subject->rating === null) {
            return;
        }

        $location->forceFill([
            'current_rating' => $subject->rating,
            // ⚠️ **THE COALESCE IS THE CARRY-FORWARD AND IS DELIBERATE**,
            // but its left-hand side is now the corroborated reading rather
            // than the raw field. A listing whose reviews were all removed
            // reports no count *and* no rating, and this method has already
            // returned on the null rating above — so in practice the fallback
            // fires only where the response contradicts itself, which is
            // exactly when keeping last night's number is the right answer.
            'review_count' => $subject->knownReviewCount() ?? $location->review_count,
        ])->save();

        // ⚠️ **`$subject->knownReviewCount()` DIRECTLY, NEVER THE CARRIED-
        // FORWARD VALUE JUST WRITTEN ABOVE.** `locations.review_count` keeps
        // last night's number when today's read corroborated nothing, which
        // is the right behaviour for a screen that must show *something*. A
        // history table exists to tell today's read apart from a carried-
        // forward one, so it gets today's own answer — including a null —
        // never the fallback that would make an unread day look identical to
        // an unchanged one.
        $this->reviewLoss->record($location, $subject->rating, $subject->knownReviewCount());
    }

    /**
     * Location ids that carry a Google place id — the inverse of a per-location
     * refresh, for the daily fan-out. Kept here so the console command never
     * becomes a second reader of `competitors` and never invents its own rule
     * for "which locations get a job".
     *
     * @return list<int>
     */
    public function locationIdsWithPlaceId(): array
    {
        Tenancy::idOrFail();

        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            Location::query()
                ->whereNotNull('google_place_id')
                ->where('google_place_id', '!=', '')
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        ));
    }

    /**
     * What the Normal surface may say about neighbours.
     */
    public function compare(Location $location): CompetitorComparison
    {
        Tenancy::idOrFail();

        if ($location->google_place_id === null || trim((string) $location->google_place_id) === '') {
            return CompetitorComparison::noPlaceId();
        }

        $competitors = Competitor::query()
            ->where('location_id', $location->id)
            ->with('latestSnapshot')
            ->orderBy('id')
            ->get();

        if ($competitors->isEmpty()) {
            // ⛔ **AN EMPTY TABLE IS NOT AN EMPTY NEIGHBOURHOOD** (9820–9839).
            // This returned `noNeighbours()` — *"We have not found nearby
            // businesses to compare yet"* on the owner's screen — for a location
            // whose nightly sync had never run, for one whose sync hit **our own
            // daily Places cost ceiling**, and for one refused by a kill switch,
            // as well as for a genuinely quiet high street.
            //
            // ⚠️ **THE JOB HAS KNOWN THE DIFFERENCE SINCE IT SHIPPED AND
            // NOTHING READ IT.** `SyncCompetitorSignalsJob::execute()` carries
            // *"'Could not ask Google' and 'asked, nobody is nearby' must not
            // close the same way"* and records exactly that on the run row —
            // the writer knew, the reader did not, which is 314–316 with the
            // true-sibling tell.
            $reason = $this->history->competitorAbsence((int) $location->getKey());

            return $reason === null
                ? CompetitorComparison::noNeighbours()
                : CompetitorComparison::absent($reason);
        }

        $ratings = [];

        foreach ($competitors as $competitor) {
            $snapshot = $competitor->latestSnapshot;

            if ($snapshot === null || $snapshot->rating === null) {
                continue;
            }

            $ratings[] = (float) $snapshot->rating;
        }

        if ($ratings === []) {
            return CompetitorComparison::absent(CompetitorAbsenceReason::NoRatings);
        }

        $average = array_sum($ratings) / count($ratings);
        $ours = $location->current_rating !== null
            ? (float) $location->current_rating
            : null;

        // Two counts, because they are two different facts and the sentence used
        // to print one under the other's name: a peer Google has no rating for
        // is still nearby, so `count($ratings)` understated "how many are near
        // you" every time an unrated peer was in the set.
        return CompetitorComparison::measured(
            neighbourCount: $competitors->count(),
            ratedCount: count($ratings),
            averageRating: $average,
            ourRating: $ours,
        );
    }
}

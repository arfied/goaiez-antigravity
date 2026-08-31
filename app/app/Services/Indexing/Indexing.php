<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Contracts\IndexNowKeys;
use App\Enums\IndexingEngine;
use App\Enums\IndexingMethod;
use App\Enums\IndexingRefusal;
use App\Enums\IndexingStatus;
use App\Models\IndexingSubmission;
use App\Models\Location;
use App\Services\Content\Publishing;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Telling search engines a page exists — and **the only writer of
 * `indexing_submissions`**.
 *
 * `BUILD-PLAN` §2.11.3 slice E, decisions 5680–5699.
 *
 * ⛔ **THE TABLE SHIPPED WITH STAGE 0 AND HAD ZERO WRITERS UNTIL THIS CLASS** —
 * `CLAUDE.md`'s 272, and §2.11.1's audit of the tree records it. A chokepoint
 * lint lands with the first writer rather than after the second, on
 * `ContentQuality`'s reasoning one slice earlier.
 *
 * ## Two lanes, and the second one forks on Google's own rule
 *
 * **IndexNow** carries the URL to every participating engine at once — Amazon,
 * Bing, Naver, Seznam.cz, Yandex and Yep — because the protocol requires that a
 * submission to any one endpoint be *"shared across all IndexNow-enabled search
 * engines"* (5681).
 *
 * **Google** takes exactly one of two routes and never both, because Google's
 * documentation makes them mutually exclusive by content type: a job posting or
 * a livestream goes to the Indexing API, and everything else is announced
 * through the sitemap named in `robots.txt`. ⚠️ **THIS FORK IS A ROUTING CHOICE
 * AND NOT THE GATE** — {@see IndexingApi} runs the gate itself, on every call,
 * for a caller that never came through here. Pre-filtering here and calling that
 * the protection would be 398's shape: an outer guard refusing first, leaving
 * the inner one unfalsifiable.
 *
 * ## What actually happens on this deployment
 *
 * ⛔ **EVERY INDEXNOW ATTEMPT IS REFUSED AND EVERY REFUSAL IS A ROW.** No key
 * file can be served until the WordPress plugin exists (5581), so
 * {@see UnhostedIndexNowKeys} answers with a reason and {@see IndexNowSubmitter}
 * is never reached. That is the honest state and it is *recorded* rather than
 * skipped, which is the difference between a feature that is waiting and one
 * that is missing.
 *
 * ✅ **THE SITEMAP LANE IS LIVE TODAY AND FINDS THE COMMON CASE ALREADY DONE**
 * (5683) — WordPress core has named its sitemap index in `robots.txt` since 5.5.
 * See {@see SitemapAnnouncement}.
 *
 * ## Where it is called from
 *
 * {@see Publishing::writeToTheSite()}, after the page is on the site. ⛔ **NEVER
 * ON THE HAND-OFF PATH**: T4 puts nothing on a website, and announcing a URL
 * that does not exist yet to six search engines is a 404 we asked for.
 *
 * ⚠️ **NO SWITCH OF ITS OWN AND NO SECOND PAUSE CHECK.** `AutopilotJob` refuses
 * a paused or suspended tenant before any side effect, and `actuation.enabled`
 * plus the adapter's own health decide whether a page reaches a website at all —
 * this only ever runs downstream of a write that already passed both. A second
 * copy of either guard is the pair 398 warns about, and the inner one would be
 * unfalsifiable.
 */
final class Indexing
{
    /**
     * How long the same URL is left alone before it is announced again.
     *
     * ⛔ **THE VENDOR'S OWN FLOOR, NOT A FIGURE OF OURS.** IndexNow's FAQ:
     * *"wait at least 5 minutes between updates before resubmitting"*, and
     * *"Avoid submitting the same URL many times a day unless there are
     * meaningful content changes"* (`indexnow.org/faq`, fetched 2026-08-20).
     * Picking a rounder, larger number would have been inventing a vendor limit,
     * which is the failure `CLAUDE.md` names four times over.
     *
     * ⚠️ **IT IS ALSO THIS SLICE'S IDEMPOTENCY.** A retried job announcing the
     * same URL leaves one row per lane, not one per retry — which is why a
     * suppressed lane writes nothing at all rather than a row saying it did
     * nothing. The row from a moment ago is the record, and a report reads it.
     */
    public const int COOLDOWN_MINUTES = 5;

    public function __construct(
        private readonly IndexNowKeys $keys,
        private readonly IndexNowSubmitter $indexNow,
        private readonly SitemapAnnouncement $sitemap,
        private readonly IndexingApi $indexingApi,
    ) {}

    /**
     * Announce one published URL down both lanes.
     *
     * @param  PageMarkup  $markup  What the page actually carries. ⛔ **Passed in
     *                              rather than inferred**: the Indexing API's
     *                              eligibility is a property of the page's
     *                              structured data, and a caller that guessed it
     *                              would be guessing at Google's spam policy.
     */
    public function announce(Location $location, string $url, PageMarkup $markup): IndexingOutcome
    {
        $this->assertBelongsToTenant($location);

        $attempts = [];
        $suppressed = 0;

        $lanes = [
            [IndexingEngine::IndexNow, IndexingMethod::IndexNow, fn (): IndexingAttempt => $this->indexNowLane($location, $url)],
            [IndexingEngine::Google, $markup->eligibleForIndexingApi() ? IndexingMethod::IndexingApi : IndexingMethod::Sitemap,
                fn (): IndexingAttempt => $markup->eligibleForIndexingApi()
                    ? $this->indexingApi->submit($location, $url, $markup)
                    : $this->sitemap->check($location)],
        ];

        foreach ($lanes as [$engine, $method, $run]) {
            if ($this->announcedRecently($location, $url, $engine, $method)) {
                $suppressed++;

                continue;
            }

            $attempt = $run();
            $this->record($location, $url, $attempt);
            $attempts[] = $attempt;
        }

        return new IndexingOutcome($attempts, $suppressed);
    }

    /**
     * The newest attempt per engine for one location — the report surface.
     *
     * ⛔ **THE READ LIVES BESIDE THE WRITE BECAUSE THE CHOKEPOINT LINT IS ABOUT
     * REACH, NOT ABOUT WRITING.** Every route to the model — an import, a
     * relationship, a container resolve — is an offence, so a separate report
     * service would either need its own permit entry or would have to be handed
     * hydrated rows by this class anyway. One permitted file is a chokepoint;
     * two is an allowlist with a second entry nobody argued for.
     *
     * ⚠️ **NEWEST PER ENGINE RATHER THAN THE NEWEST N ROWS.** A location
     * publishing several pages an hour would otherwise fill the panel with one
     * lane and hide the other, and the question an operator has is *"is each
     * route working"* rather than *"what happened most recently"*.
     *
     * @return list<IndexingReportLine>
     */
    public function latestPerEngine(Location $location): array
    {
        $this->assertBelongsToTenant($location);

        $lines = [];

        foreach (IndexingEngine::cases() as $engine) {
            $row = IndexingSubmission::query()
                ->where('location_id', $location->id)
                ->where('engine', $engine->value)
                // ⚠️ **`NULLS LAST` SPELT OUT EVEN THOUGH THE COLUMN IS
                // `NOT NULL`** — this codebase's convention lint requires it of
                // every descending order, because the day a column becomes
                // nullable is not the day anybody re-reads the queries over it.
                ->orderByRaw('attempted_at DESC NULLS LAST')
                ->orderByDesc('id')
                ->first();

            if (! $row instanceof IndexingSubmission) {
                continue;
            }

            // ⚠️ **THE STORED VALUES RATHER THAN THE CAST ONES, AND IT IS NOT
            // A STYLE CHOICE.** Static analysis types these attributes from the
            // schema — `string` — while the runtime casts hand back enums and a
            // `Carbon`, so an `instanceof` here is dead code at level 8 and a
            // bare cast would be a lie in the other direction. Reading the raw
            // column and re-hydrating is the one spelling that is true to both,
            // and `from()` throws on a value no case matches rather than
            // silently picking one.
            $lines[] = new IndexingReportLine(
                $engine,
                $this->rawString($row, 'url') ?? '',
                IndexingStatus::from($this->rawString($row, 'status') ?? ''),
                ($reason = $this->rawString($row, 'reason')) === null ? null : IndexingRefusal::from($reason),
                ($attempted = $this->rawString($row, 'attempted_at')) === null ? null : Carbon::parse($attempted),
            );
        }

        return $lines;
    }

    /**
     * One stored column as it sits in the database, or null when it is null.
     */
    private function rawString(IndexingSubmission $row, string $column): ?string
    {
        $value = $row->getRawOriginal($column);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * IndexNow, or the reason there is no key to submit with.
     */
    private function indexNowLane(Location $location, string $url): IndexingAttempt
    {
        $outcome = $this->keys->for($location);

        if ($outcome->key === null) {
            return IndexingAttempt::refused(
                IndexingEngine::IndexNow,
                IndexingMethod::IndexNow,
                $outcome->refusalOrFail(),
            );
        }

        return $this->indexNow->submit($outcome->key, [$url]);
    }

    /**
     * Whether this lane already ran for this URL inside the cooldown.
     */
    private function announcedRecently(
        Location $location,
        string $url,
        IndexingEngine $engine,
        IndexingMethod $method,
    ): bool {
        return IndexingSubmission::query()
            ->where('location_id', $location->id)
            ->where('url', $url)
            ->where('engine', $engine->value)
            ->where('method', $method->value)
            ->where('attempted_at', '>=', Carbon::now()->subMinutes(self::COOLDOWN_MINUTES))
            ->exists();
    }

    /**
     * The row.
     *
     * ⛔ **`reason` IS SET EXACTLY WHEN THE STATUS IS `Refused`, AND
     * `submitted_at` EXACTLY WHEN AN ENGINE TOOK THE REQUEST.** The database
     * carries the half of that which can be written without naming status
     * strings in SQL; this method carries the rest, and the assertion below is
     * what makes it a property of the writer rather than of the caller's care.
     */
    private function record(Location $location, string $url, IndexingAttempt $attempt): IndexingSubmission
    {
        $refused = $attempt->status === IndexingStatus::Refused;

        if ($refused !== ($attempt->reason !== null)) {
            throw new InvalidArgumentException(
                'An indexing attempt carries a reason exactly when it was refused by us. '
                .'A vendor saying no is IndexingStatus::Rejected with its answer in `response`.'
            );
        }

        $now = Carbon::now();

        return IndexingSubmission::create([
            'location_id' => $location->id,
            'url' => $url,
            'engine' => $attempt->engine,
            'method' => $attempt->method,
            'status' => $attempt->status,
            'reason' => $attempt->reason,
            'response' => $attempt->response,
            'attempted_at' => $now,
            'submitted_at' => $attempt->status->wasAccepted() ? $now : null,
        ]);
    }

    /**
     * ⛔ **A LOCATION FROM ANOTHER TENANT IS A PROGRAMMING ERROR, NOT A
     * REFUSAL** — `ActuationTiers`' rule. Row-level security would refuse the
     * insert anyway; this refuses before the fetch that would precede it, so
     * nobody's `robots.txt` is read on behalf of a tenant who does not own it.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. Announcing its URL here would file the '
            .'submission under the wrong business and fetch a stranger\'s robots.txt to do it.',
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Contracts\SearchConsoleClient;
use App\Enums\AutopilotActionType;
use App\Enums\ContentAuditFinding;
use App\Exceptions\SearchConsoleRequestFailed;
use App\Models\Business;
use App\Models\Location;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Visibility\ConnectionState;
use App\Services\Visibility\SearchConsoleProperties;
use App\Support\Shingles;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;

/**
 * Doc `16` §15.3's daily self-audit.
 *
 * ```
 * SELF-AUDIT (daily):
 *   • Flag near-duplicate clusters
 *   • Flag pages with zero impressions after 90 days → prune or merge
 *   • Flag any template where <40% of body varies
 *   • Alert owner + auto-pause generation if thresholds breach
 *   • Monitor GSC manual actions continuously
 * ```
 *
 * ⛔ **TWO OF THOSE FIVE ARE BUILT AND THE OTHER THREE ARE NAMED HERE RATHER
 * THAN LEFT TO BE DISCOVERED** (5672):
 *
 *   - **the template-variance line has no subject.** There is no template in
 *     this schema — `growth_pages` holds finished copy, and the thing `16` is
 *     describing is a Stage 5 generator's own artefact. A check over a
 *     population of nothing passes vacuously (256);
 *   - **manual actions are not readable.** They live behind
 *     `webmasters` write scope in the Search Console UI, not in
 *     `searchAnalytics.query`, and 1083 refused that scope because it also
 *     permits deleting a tenant's property. This is a real gap and the honest
 *     answer is that we cannot see them, not that nobody looked.
 *
 * ## What "auto-pause generation" means here
 *
 * ⛔ **IT IS A COLUMN AND THE VOLUME CAPS ARE NOT** — `locations.content_generation_paused_at`
 * against a count over `growth_pages`. The difference is where the input comes
 * from: a cap is arithmetic over rows this application owns, so it is derived on
 * every attempt and releases itself at the period boundary; **this** is ninety
 * days of somebody else's API, and deriving it on the publish path would put a
 * vendor round trip in front of every page and make the gate's answer depend on
 * Google being up.
 *
 * ⚠️ **THE NEXT CLEAN AUDIT LIFTS IT** — there is no Ops release command and no
 * tenant-facing toggle, because a switch labelled *"ignore the audit"* is a
 * support surface offering to turn off the thing standing between a tenant and a
 * manual action.
 */
final class ContentSelfAudit
{
    /**
     * `16` §15.3: *"Flag pages with zero impressions after 90 days."*
     *
     * ⛔ **A PAGE YOUNGER THAN THE WINDOW IS NOT AUDITED AT ALL.** Google has
     * not finished with a page published last week, and reporting its silence as
     * a finding is 229's false statement — the same rule that keeps
     * `InsufficientData` out of `Neutral` one slice down the chain.
     */
    public const int IMPRESSION_WINDOW_DAYS = 90;

    /**
     * How alike two published pages have to be before they are a cluster.
     *
     * ⚠️ **THE GATE'S OWN THRESHOLD, READ FROM THE SAME REGISTRY ROW, AND NOT A
     * SECOND NUMBER.** `content.quality.min_uniqueness_pct` is what one page
     * must clear against the corpus; a pair that sits below it is by definition
     * a pair the gate would refuse today. A separate figure here would be a
     * second answer to one question, and the two would drift the first time
     * either moved.
     */
    public function __construct(
        private readonly DefaultsRegistry $defaults,
        private readonly GrowthPages $pages,
        private readonly SearchConsoleClient $searchConsole,
        private readonly SearchConsoleProperties $properties,
        private readonly DefaultsRegistry $registry,
        private readonly ActivityService $activity,
        private readonly AuditService $audit,
    ) {}

    /**
     * Audit one location and either pause it or lift a pause it no longer earns.
     *
     * @return list<ContentAuditFinding>
     */
    public function run(Location $location, CarbonImmutable $now): array
    {
        $published = $this->pages->publishedAt((int) $location->id);

        $findings = [];

        if ($this->hasNearDuplicateCluster($published)) {
            $findings[] = ContentAuditFinding::NearDuplicateCluster;
        }

        if ($this->hasSilentPages($location, $published, $now)) {
            $findings[] = ContentAuditFinding::ZeroImpressions;
        }

        $findings === [] ? $this->lift($location) : $this->pause($location, $findings);

        return $findings;
    }

    /**
     * ⚠️ **EVERY PAIR, NOT EVERY PAGE AGAINST THE REST.** `Shingles::uniquenessPercent()`
     * takes the best match across a corpus, so asking it *"is this page unique
     * against all the others"* answers about the corpus rather than about a
     * pair — and a location with twenty distinct pages and two identical ones
     * would read as fine. `16` §15.1's failure is a **cluster**, which is a
     * statement about two pages at a time.
     *
     * ⚠️ **BOUNDED BY THE VOLUME CAPS ABOVE IT.** Four pages and four posts a
     * month per location puts a mature location in the low hundreds, so the
     * pairwise pass is affordable on a nightly job and is not on any request
     * path.
     *
     * @param  list<PublishCandidate>  $published
     */
    private function hasNearDuplicateCluster(array $published): bool
    {
        $threshold = $this->registry->int(ContentQuality::MIN_UNIQUENESS_KEY);

        $texts = array_map(
            fn (PublishCandidate $page): string => $page->copy->fullText(),
            $published,
        );

        $count = count($texts);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (Shingles::uniquenessPercent($texts[$i], [$texts[$j]]) < $threshold) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * ⛔ **NO SEARCH CONSOLE MEANS NO FINDING, NEVER A FINDING OF ZERO.** A
     * tenant who has not connected Google, or whose grant was revoked, has no
     * impression data at all — and *"we cannot see"* reported as *"nobody saw
     * it"* would pause a location's publishing on the strength of a connection
     * problem. 229's rule, and `ActuationTiers`' third answer (5543) is the same
     * shape: **we do not know yet** is not the negative answer.
     *
     * ⚠️ **A FAILED REQUEST IS THE SAME AS AN ABSENT ONE.** Google being down at
     * 03:00 must not pause every tenant on the platform at 03:01.
     *
     * @param  list<PublishCandidate>  $published
     */
    private function hasSilentPages(Location $location, array $published, CarbonImmutable $now): bool
    {
        $website = $location->website_url;

        if ($website === null) {
            return false;
        }

        $old = array_values(array_filter(
            $published,
            fn (PublishCandidate $page): bool => $page->publishedAt !== null
                && $page->publishedAt->lessThanOrEqualTo($now->subDays($this->impressionWindowDays())),
        ));

        if ($old === []) {
            return false;
        }

        if ($this->properties->connectionState($location) !== ConnectionState::Usable) {
            return false;
        }

        $property = $this->properties->forLocation($location);

        if ($property === null) {
            return false;
        }

        $business = Business::query()->find(Tenancy::idOrFail());

        if (! $business instanceof Business) {
            return false;
        }

        try {
            $impressions = $this->searchConsole->pageImpressions(
                $business,
                (string) $property->site_url,
                $now->subDays($this->impressionWindowDays()),
                $now,
            );
        } catch (SearchConsoleRequestFailed) {
            return false;
        }

        foreach ($old as $page) {
            if (($impressions[$page->urlOn($website)] ?? 0) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<ContentAuditFinding>  $findings
     */
    private function pause(Location $location, array $findings): void
    {
        $reason = $findings[0]->value;

        if ($location->content_generation_paused_at !== null
            && $location->content_generation_pause_reason === $reason) {
            return;
        }

        // forceFill, because both columns are guarded on the model: this service
        // is the only writer and a request body must never be one.
        //
        // ⚠️ THE TIMESTAMP SURVIVES A CHANGE OF REASON. A location that was
        // paused for duplicates and is now also silent has been paused since the
        // first finding, and restamping it would tell an owner the problem
        // started today.
        $location->forceFill([
            'content_generation_paused_at' => $location->content_generation_paused_at ?? now(),
            'content_generation_pause_reason' => $reason,
        ])->save();

        $this->audit->record('content.generation_paused', 'autopilot', $location, [
            'findings' => array_map(
                fn (ContentAuditFinding $finding): string => $finding->value,
                $findings,
            ),
        ]);

        $this->activity->record(
            AutopilotActionType::OwnerActionNeeded,
            (int) $location->id,
            [
                'automation' => 'content.self_audit',
                'findings' => array_map(
                    fn (ContentAuditFinding $finding): string => $finding->value,
                    $findings,
                ),
            ],
        );
    }

    /**
     * ⚠️ **THE AUDIT IS THE ONLY THING THAT LIFTS ITS OWN PAUSE**, and it lifts
     * it silently: an owner who fixed what they were told about does not need a
     * second message telling them the alarm stopped.
     */
    private function lift(Location $location): void
    {
        if ($location->content_generation_paused_at === null) {
            return;
        }

        $location->forceFill([
            'content_generation_paused_at' => null,
            'content_generation_pause_reason' => null,
        ])->save();

        $this->audit->record('content.generation_resumed', 'autopilot', $location);
    }

    public function impressionWindowDays(): int
    {
        return $this->defaults->int('content.self_audit.impression_window_days');
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\SiteChangeVerdict;
use App\Enums\SpeedFix;
use Carbon\CarbonImmutable;

/**
 * One applied change set, in the shape the measurer needs and nothing more.
 *
 * ⛔ **THE MODEL DOES NOT LEAVE {@see SiteMeasurements}, WHICH IS THE
 * CHOKEPOINT DOING ITS JOB RATHER THAN AN EXCEPTION TO IT** — 5661's move one
 * slice on. The lint permits two files in `app/` to name `SiteChange`, so a
 * measurer, a job and a command that all deal in change sets take ids and value
 * objects instead. `ContentQuality::assess()`'s `int $pageId` is the same
 * pattern for the same reason.
 *
 * ⛔ **NEITHER SNAPSHOT IS HERE, AND THAT IS DELIBERATE.** `before_snapshot`
 * holds a verbatim copy of part of a stranger's page. The measurer needs the
 * page's *address* and the *kind* of change, never its content, and an object
 * that carried the content would end up in a queue payload and an exception
 * message (5521's own warning on `SiteChanges::live()`).
 */
final readonly class MeasurableChange
{
    public function __construct(
        public int $id,
        public int $locationId,
        public string $url,
        public string $changeType,
        public CarbonImmutable $appliedAt,
        public SiteChangeVerdict $verdict,
        public bool $isMeasured,
        /**
         * Whether this change **created** the page rather than editing one.
         *
         * ⛔ **A CREATION HAS NO PRE-CHANGE WINDOW BY CONSTRUCTION, AND THAT IS
         * A FACT ABOUT THE CHANGE RATHER THAN A MEASUREMENT** (5804). The page
         * did not exist a fortnight before, so its traffic then was not zero —
         * the question did not apply. Letting that fall out of the arithmetic
         * as a zero would record *"nobody visited"* about a page nobody could
         * have visited, which is 229 at the one address the whole verdict enum
         * exists to protect.
         */
        public bool $createdThePage = false,
        /**
         * Whether the change has already come off the site.
         *
         * ⛔ **AN OWNER PRESSING UNDO IS A CUSTOMER TELLING US WE WERE WRONG**
         * (5524). `SiteMeasurements::dueForMeasurement()` has excluded these
         * rows since slice H, so the sweep never offers one — **but it
         * dispatches a job per id, and the press can land in between** (5963).
         * Without this the measurer decides a verdict on a change that is
         * already gone and hands it to a revert that raises, so the job fails
         * three times and the queue collects one failure a night.
         */
        public bool $isRolledBack = false,
    ) {}

    /**
     * The path the pixel mart is keyed on.
     *
     * ⚠️ **`l2_fact_page_daily` STORES `window.location.pathname`**, so a query
     * string and a fragment are not part of the key — and neither is the host,
     * which is why this is a path rather than the URL. A full URL handed to the
     * mart would match nothing at all, silently, for every change ever measured.
     */
    public function pagePath(): string
    {
        $path = parse_url($this->url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    /**
     * Whether `28` §4.3's decider owns this row rather than `29` §2 rule 32's
     * measurer.
     *
     * ⛔ **A FACT ABOUT THE ROW, WHICH IS THE WHOLE CORRECTION** (5962). 5860
     * put the separation in `SiteMeasurements::dueForMeasurement()`'s
     * `whereNotIn`, so it held only for callers that came through the sweep —
     * and `ChangeMeasurer::measure()` is public, takes an id, and judged a
     * speed fix on page visits and Google clicks when handed one directly. The
     * set is still `SpeedFix`'s to define; what changed is that the row can now
     * be asked.
     */
    public function isTheSpeedLayers(): bool
    {
        return SpeedFix::owns($this->changeType);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Enums\SiteChangeVerdict;
use App\Livewire\Account\SiteChanges as SiteChangesScreen;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;

/**
 * What measuring one change actually found — the reader `site_changes`'
 * two metric documents never had.
 *
 * ⛔ **272's SHAPE, WITH THE OWNER'S OWN EVIDENCE INSIDE IT.** 5819 recorded
 * that nothing in `app/` read `baseline_metrics` or `measured_metrics` back and
 * named it slice J's; 5849 recorded that slice J did not do it, on the argument
 * that *"a metrics panel is a report and this screen is a control"*. **That is
 * an argument about where the reader goes, not about whether there is one** —
 * so this is it, and what it produces is a **sentence** rather than a panel.
 *
 * ## `29` §2 rule 47 decides the shape before anything else does
 *
 * ⛔ **NO FIELD NAMES, NO BASIS POINTS, NO WINDOW BOUNDARIES.** A baseline and a
 * measurement in front of somebody who runs a plumbing business have to say what
 * changed and whether it was good. {@see self::sentence()} is the whole
 * owner-facing surface and it names visits, a percentage and a number of days.
 * The documents themselves are for support, through `actuation:evidence`.
 *
 * ## Three ways to lie with true numbers, all of them refused here
 *
 * ⛔ **A WITHHELD BASELINE IS NOT A ZERO** (5804). A created page did not exist
 * a fortnight before it went up, so its traffic then was **not zero** — the
 * question did not apply, and `ChangeMeasurer::window()` records `null`.
 * Rendering that as *"0 visits before, 40 after"* would manufacture an infinite
 * improvement out of a question nobody asked. {@see self::sentence()} has an arm
 * of its own for it and says *there was nothing to compare it with*.
 *
 * ⛔ **THE ABSENCE OF `Improved` DOES NOT MEAN NOTHING IMPROVED** (5808). The
 * search signal is the whole property's, so it may say `Regressed` and may never
 * say `Improved` — which means a reader that showed both signals side by side
 * would show a column that is structurally incapable of good news, and an owner
 * reading it would conclude their site never improves. **So the owner-facing
 * sentence reports the page's own signal only**, and the site-wide search
 * figures stay in the evidence document where a person can be told what they
 * are. See {@see self::sentence()}.
 *
 * ⛔ **`InsufficientData` IS NOT `Neutral`** ({@see SiteChangeVerdict}, 5523).
 * *"We measured this for a month and it did not move"* and *"not enough people
 * reached it for the question to have an answer"* are different things to be
 * told, and 4861 makes the second the expected answer for most tenants for some
 * time. Collapsing them would report a month of evidence this platform does not
 * have, on every tenant, from the first change.
 *
 * ## The document is versioned and this reader honours that
 *
 * ⚠️ **AN UNRECOGNISED `v` IS REFUSED RATHER THAN READ OPTIMISTICALLY.**
 * {@see ChangeMetrics::VERSION} exists because these rows are evidence an owner
 * can be shown months later and a later slice will add a metric. A reader that
 * shrugged at an unknown version would either crash on a renamed key or, far
 * worse, read a figure that now means something else — so an unreadable document
 * produces **no sentence at all**, which is the same outcome as never having
 * measured and is honest in both.
 *
 * @see SiteChangesScreen the one owner-facing surface
 * @see SiteChanges::history() where these are built
 */
final readonly class ChangeEvidence
{
    private function __construct(
        public int $baselineDays,
        public int $measuredDays,
        public ?int $pageviewsBefore,
        public ?int $pageviewsAfter,
        public ?int $conversionsBefore,
        public ?int $conversionsAfter,
        public ?int $searchClicksBefore,
        public ?int $searchClicksAfter,
        public ?int $searchImpressionsBefore,
        public ?int $searchImpressionsAfter,
        public SiteChangeVerdict $verdict,
        public SiteChangeVerdict $pageVerdict,
        public SiteChangeVerdict $searchVerdict,
        public ?int $pageBasisPoints,
        public ?int $searchBasisPoints,
        public bool $createdThePage,
    ) {}

    /**
     * Read the two stored documents, or say honestly that there is nothing to
     * read.
     *
     * ⚠️ **NULL IS "WE HAVE NOT MEASURED THIS" AND IT IS THE ORDINARY ANSWER.**
     * The columns are all-or-nothing by CHECK, the measured window does not open
     * for a fortnight, and an unsettled Search Console window defers the whole
     * measurement (5802) — so most cards on most days have no evidence, and that
     * is a state rather than a fault.
     *
     * @param  array<string, mixed>|null  $baseline
     * @param  array<string, mixed>|null  $measured
     */
    public static function from(?array $baseline, ?array $measured, bool $createdThePage): ?self
    {
        if ($baseline === null || $measured === null) {
            return null;
        }

        if (($baseline['v'] ?? null) !== ChangeMetrics::VERSION || ($measured['v'] ?? null) !== ChangeMetrics::VERSION) {
            return null;
        }

        $before = self::metrics($baseline);
        $after = self::metrics($measured);

        if ($before === null || $after === null) {
            return null;
        }

        // ⛔ **THE COMPARISON IS RE-DERIVED THROUGH THE ONE CLASS THAT DEFINES
        // IT, NEVER RE-IMPLEMENTED HERE.** `site_changes.verdict` stores the
        // **combined** answer only, so the per-signal reading a sentence needs
        // is not on the row — and a second copy of rule 32's arithmetic is
        // 5146's four-marts-disagreeing failure with a customer's website
        // attached. The stored verdict and this derivation come from the same
        // rows through the same code, so they cannot disagree.
        $comparison = ChangeComparison::between($before, $after);

        return new self(
            $before->days,
            $after->days,
            $before->pageviews,
            $after->pageviews,
            $before->conversions,
            $after->conversions,
            $before->searchClicks,
            $after->searchClicks,
            $before->searchImpressions,
            $after->searchImpressions,
            $comparison->verdict,
            $comparison->pageVerdict,
            $comparison->searchVerdict,
            $comparison->pageBasisPoints,
            $comparison->searchBasisPoints,
            $createdThePage,
        );
    }

    /**
     * What we found, for the person whose website it is.
     *
     * ⛔ **THE PAGE'S OWN SIGNAL AND NOTHING ELSE, AND THE OMISSION IS THE
     * DECISION** (5808). `GoogleSearchConsoleClient::dailyMetrics()` reads a
     * whole property, so the search figure is about the **site** and not about
     * this page. Printing it on a card about one page would be read as being
     * about that page whatever caveat sat beside it — and a site-wide rise
     * credited to one page we wrote is `28` §4.3's fabricated win, which 5610
     * and 5765 refused one metric along. When that signal **falls** it is
     * already what took the page down, and `rolled_back_reason` prints it in the
     * words it was measured in.
     *
     * ⚠️ **THE WINDOW IS NAMED IN EVERY ARM THAT CARRIES A NUMBER** — 3787's
     * rule that a rate with no denominator beside it is what made two earlier
     * defects hard to see. The two windows are different lengths on purpose
     * (`29` §2 rule 32), so *"a fifth more"* is meaningless without them.
     */
    public function sentence(): string
    {
        // ⛔ **A CREATED PAGE HAS NO BASELINE AND IS NEVER GIVEN A FAKE ONE**
        // (5804). This arm is why the reader takes `createdThePage` at all: a
        // null baseline on an edit means *we could not see*, and on a creation
        // it means *the question did not apply*. Two sentences, because they are
        // two different facts about somebody's website.
        if ($this->createdThePage) {
            return $this->pageviewsAfter === null
                ? 'This page was new, so there was nothing to compare it with — and we could not count visits to it either.'
                : 'This page was new, so there was nothing to compare it with. '
                    ."It was visited {$this->pageviewsAfter} times in the {$this->measuredDays} days after it went up.";
        }

        if ($this->pageviewsBefore === null || $this->pageviewsAfter === null) {
            return 'We could not count visits to this page, so we cannot tell you whether the change helped.';
        }

        if ($this->pageVerdict === SiteChangeVerdict::InsufficientData || $this->pageBasisPoints === null) {
            return "Only {$this->pageviewsBefore} people reached this page in the {$this->baselineDays} days before the change — "
                .'too few for us to tell you honestly whether it helped.';
        }

        $windows = "the {$this->measuredDays} days after the change than in the {$this->baselineDays} days before it";

        return match ($this->pageVerdict) {
            SiteChangeVerdict::Improved => 'Visits to this page were about '
                .$this->percent().'% higher in '.$windows.'.',
            SiteChangeVerdict::Regressed => 'Visits to this page were about '
                .$this->percent().'% lower in '.$windows.'.',
            default => 'Visits to this page held about steady: roughly the same each day in '
                .$windows.'.',
        };
    }

    /**
     * The move, as whole percent.
     *
     * ⚠️ **INTEGER DIVISION ON BASIS POINTS, WHICH IS `ChangeMeasurer`'s OWN
     * ARITHMETIC.** There is no float anywhere in this path on
     * {@see ChangeComparison}'s standing reason, and a percentage rounded two
     * different ways in two places would put two figures on one change
     * depending on which sentence a person happened to read.
     */
    private function percent(): int
    {
        return intdiv(abs((int) $this->pageBasisPoints), 100);
    }

    /**
     * One stored window, back as the object it was written from.
     *
     * ⚠️ **THE KEY NAMES LIVE HERE AND IN `ChangeMetrics::toArray()`, AND THAT
     * IS A SERIALISATION CONTRACT WITH TWO ENDS.** It is pinned by a round-trip
     * test rather than by hope: a `ChangeMetrics` is serialised, read back here,
     * and asserted identical — so a renamed key or a bumped version reddens the
     * build in the slice that changes it, where a silent `null` would degrade an
     * owner's sentence to *"we could not count visits"* for ever.
     *
     * @param  array<string, mixed>  $document
     */
    private static function metrics(array $document): ?ChangeMetrics
    {
        $from = $document['from'] ?? null;
        $to = $document['to'] ?? null;
        $days = $document['days'] ?? null;

        if (! is_string($from) || ! is_string($to) || ! is_int($days)) {
            return null;
        }

        try {
            $start = CarbonImmutable::parse($from);
            $end = CarbonImmutable::parse($to);
        } catch (InvalidFormatException) {
            return null;
        }

        return new ChangeMetrics(
            $start,
            $end,
            $days,
            self::count($document, 'pageviews'),
            self::count($document, 'conversions'),
            self::count($document, 'search_clicks'),
            self::count($document, 'search_impressions'),
        );
    }

    /**
     * ⛔ **A MISSING KEY AND A RECORDED `null` COME BACK THE SAME AND MUST**
     * (229, and {@see ChangeMetrics}' own rule). Both mean *we could not see*;
     * neither means zero. What must never happen is a cast turning either into
     * `0`, which is why this refuses anything that is not an integer rather than
     * coercing it.
     *
     * @param  array<string, mixed>  $document
     */
    private static function count(array $document, string $key): ?int
    {
        $value = $document[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}

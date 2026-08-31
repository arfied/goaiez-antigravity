<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Enums\GrowthPageType;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Carbon;

/**
 * Doc `16` §15.3's volume caps — *"deliberately conservative"*.
 *
 * ```
 * New pages:           ≤ 4 per month per location (NOT per day)
 * Blog posts:          ≤ 4 per month
 * Total site growth:   ≤ 20% pages per quarter
 * Refresh:reNew ratio  ≥ 2:1  (refresh beats publish)
 * ```
 *
 * `29` §2 rule 30 carries the first two and the ratio, with the reason attached:
 * *"Google's March 2026 update cut traffic 50–80% on sites that ignored this."*
 * `BUILD-PLAN` §2.11.6 row 7 settles that all four **seed as written** rather
 * than being withheld — they are somebody else's risk model, not our preference.
 *
 * ## Two of the four are seeded and enforce nothing, and that is said here
 *
 * ⛔ **`content.volume.max_quarterly_growth_pct` HAS NO DENOMINATOR IN THIS
 * APPLICATION** (5667). *"≤20% pages per quarter"* is a share of the pages on
 * the tenant's website, and nothing here knows how many that is: `SiteProbe`
 * fetches the origin and the REST root, there is no crawl inventory, and
 * `growth_pages` holds only the pages **we** wrote. Substituting our own count
 * for the site's would refuse the first page every tenant ever publishes — a
 * quarter that starts at zero permits twenty per cent of zero — and calling that
 * "the cap working" would be the worst kind of green. It becomes computable when
 * a page inventory exists (the T1 plugin can enumerate a site; a T3 site cannot
 * be enumerated at all).
 *
 * ⛔ **`content.volume.min_refresh_to_new_ratio` HAS NO NUMERATOR** (5668).
 * Nothing in this schema refreshes a published page: `growth_pages` has one
 * terminal state and no update path onto the site, and the refresh half arrives
 * with Stage 5's content engine. A ratio of zero refreshes to one new page is
 * below 2:1 for **every** tenant on their first page for ever, so enforcing it
 * today would refuse everything and enforcing it "only when refreshes exist" is
 * a condition that is constantly false — 256's vacuous pass wearing a threshold.
 *
 * ⚠️ **THE FIGURES ARE SEEDED ANYWAY, AND THE OPS DESCRIPTION SAYS SO** —
 * §2.11.6 row 7 is explicit, and an operator reading a registry row must not
 * mistake a parked figure for a live ceiling. `CLAUDE.md`'s own warning applies
 * to both of them by name: *"a seeded figure with no reader is a row in a table,
 * not a ceiling"*. They are named here rather than discovered later.
 */
final class PublishingVolume
{
    /** `16` §15.3's *"New pages: ≤ 4 per month per location"*. */
    public const string MAX_PAGES_KEY = 'content.volume.max_new_pages_per_month';

    /** `16` §15.3's *"Blog posts: ≤ 4 per month"*. */
    public const string MAX_POSTS_KEY = 'content.volume.max_new_posts_per_month';

    /**
     * The two figures that are seeded and enforce nothing, with the fact that
     * would make each of them computable.
     *
     * ⚠️ **A CONSTANT RATHER THAN A PARAGRAPH, BECAUSE A TEST READS IT.**
     * `Architecture\ContentTest` asserts that every key here is seeded, that its
     * Ops description says it is not enforced, and that {@see self::verdict()}
     * does not consult it — so the day somebody wires one up, the entry has to
     * come out with it.
     *
     * @var array<string, string>
     */
    public const array UNENFORCED = [
        'content.volume.max_quarterly_growth_pct' => 'nothing knows how many pages the tenant’s website has',
        'content.volume.min_refresh_to_new_ratio' => 'nothing refreshes a published page yet',
    ];

    public function __construct(private readonly DefaultsRegistry $registry) {}

    /**
     * May this location publish one more page of this type this month?
     *
     * ⚠️ **THE MONTH IS THE CALENDAR MONTH IN THE TENANT'S OWN TIME**, on
     * `ResetMonthlyCredits`' reasoning: a rolling thirty days makes *"how many
     * have I got left"* unanswerable by anybody who is not running the query,
     * and this figure is one an owner is told.
     *
     * ⛔ **POSTS AND PAGES ARE TWO CAPS, NOT ONE OF EIGHT** (`16` §15.3 lists
     * them on separate lines). Collapsing them would let four blog posts consume
     * a tenant's whole service-page allowance for the month, which is the
     * opposite of *"refresh beats publish"*.
     */
    public function verdict(GrowthPages $pages, int $locationId, GrowthPageType $type, Carbon $now): VolumeVerdict
    {
        $key = $type === GrowthPageType::Post ? self::MAX_POSTS_KEY : self::MAX_PAGES_KEY;

        $cap = $this->registry->int($key);

        $published = $pages->publishedSince(
            $locationId,
            $now->copy()->startOfMonth(),
            $type === GrowthPageType::Post,
        );

        return $published >= $cap
            ? VolumeVerdict::reached($key, $published, $cap)
            : VolumeVerdict::allowed();
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Actuation\ChangeSet;
use App\Services\Actuation\ScriptDeferral;
use App\Services\Actuation\SiteProbe;
use App\Services\Actuation\SpeedFixes;
use App\Services\Actuation\WordPress\WordPressAdapter;

/**
 * `28` §4.1's seven rows — every speed change this platform may make to a
 * website it does not own, and the tiers each one is honest at.
 *
 * ⛔ **THE MATRIX IS TRANSCRIBED, NOT INTERPRETED.** §4.1 is a table of ticks
 * and crosses per tier, and {@see self::tiers()} is that table. Where the
 * document says *"❌ post-render is too late"* the tier is absent; where it says
 * *"⚠️ JS-injected — marginal, allowed"* the tier is present. Nothing here
 * improves on it: a fix quietly granted to a tier the document withholds it from
 * is this platform writing something it was told not to onto a stranger's page.
 *
 * ⛔ **NONE OF THE SEVEN CAN BE APPLIED BY ANY ADAPTER THAT EXISTS, AND THAT IS
 * A FINDING RATHER THAN A GAP** (5850–5852). Every one of them is theme- or
 * asset-layer, and the only live adapter is
 * {@see WordPressAdapter}, which writes over
 * WordPress **core's** REST API and whose whole writable vocabulary is `title`,
 * `content` and `excerpt`. So `fieldSupport()` refuses every field below, by
 * name and with a stored reason, and {@see SpeedFixes} records the refusal
 * rather than dropping it. **The seam is already built and it is not a new
 * one**: the pipeline asks the adapter what it can write (5772), and the answer
 * changes on the day F2's plugin adapter lands. Nothing in this enum needs to
 * move for that to happen.
 *
 * ⚠️ **TWO OF THE SEVEN ARE ALREADY DONE BY WORDPRESS ITSELF, WHICH NARROWS
 * WHAT F2 OWES RATHER THAN WHAT WE CLAIM** — see {@see self::ImageDimensions}
 * and {@see self::LazyLoadImages}. 5683's shape a second time: the built
 * behaviour must be to look rather than to assume.
 *
 * ⚠️ **THE VALUES ARE NOT `site_changes.change_type` — {@see self::changeType()}
 * IS.** That column is a free string shared by five tiers, and a bare
 * `image_dimensions` would be one careless T3 payload away from colliding with
 * something else's vocabulary. The `speed_` prefix is what keeps
 * {@see T3InjectionKind::tryFrom()} answering null for every one of these, which
 * is how the injection endpoint already skips change sets that are not its own.
 */
enum SpeedFix: string
{
    /**
     * `width` and `height` on images, so the browser can lay the page out
     * before they load — §4.1's CLS row.
     *
     * ⚠️ **WORDPRESS CORE HAS DONE THIS SINCE 5.5 AND F2 OWES THE EXCEPTION,
     * NOT THE RULE.** *"With version 5.5 WordPress will start back-filling width
     * and height attributes on img tags when they are not already present"*
     * (`make.wordpress.org/core/2020/07/14/lazy-loading-images-in-5-5/`,
     * published 2020-07-14, fetched 2026-08-20). ⚠️ **Its own caveat is the
     * exception**: *"width and height can only be determined if an image is for
     * a WordPress attachment and if the img tag includes the relevant
     * `wp-image-$id` class"* — so a hand-pasted `<img>`, a theme template image
     * and an externally hosted one are still unsized, and those are what a
     * plugin-layer filter reaches.
     */
    case ImageDimensions = 'image_dimensions';

    /**
     * `loading="lazy"` on below-the-fold images — §4.1's second row.
     *
     * ⚠️ **ALSO CORE SINCE 5.5, AND CONDITIONALLY**: *"In WordPress 5.5, images
     * will be lazy-loaded by default… By default, WordPress will add
     * `loading="lazy"` to all img tags that have width and height attributes
     * present"* (same source, same fetch). So this fix and the one above are one
     * chain on WordPress: an image core could not size is an image core will not
     * lazy-load, which is why they stay two fixes rather than one — the second
     * is worthless until the first has run on that image.
     *
     * ⛔ **NEVER T3.** §4.1: *"❌ (post-render is too late)"*. By the time the
     * pixel's module could set the attribute the browser has already begun the
     * fetch, so a T3 implementation would be a change set that changed nothing
     * while being measured, reverted and quarantined as though it had.
     */
    case LazyLoadImages = 'lazy_load_images';

    /**
     * `font-display: swap`, so text is readable while a web font loads.
     */
    case FontDisplaySwap = 'font_display_swap';

    /**
     * `<link rel="preconnect">` for the top third-party origins — §4.1's only
     * row with a tick in the pixel column.
     *
     * ⛔ **IT HAS NO INPUT IN THIS SYSTEM AND THAT IS WHY T3 GETS MEASUREMENT
     * ALONE TODAY** (5853). §4.1 asks for *"top 3 third-party origins"*, and
     * nothing here knows a site's third-party origins: the pixel collects
     * pageviews, vitals, errors, scroll and form events and **no resource
     * timings**, and {@see SiteProbe} keeps two booleans
     * from a page fetch and none of its markup. A preconnect fix built today
     * would either invent origins or ship an operation whose list is always
     * empty — 256's vacuous pass with a `<link>` tag on it.
     */
    case PreconnectHints = 'preconnect_hints';

    /**
     * Deferring third-party scripts — §4.2's *"the only risky fix"*.
     *
     * ⛔ **BOUNDED BY {@see ScriptDeferral}, WHICH
     * REFUSES ANYTHING WEARING A PAYMENT, BOOKING OR CHECKOUT SIGNATURE
     * WHATEVER AN OPERATOR HAS ALLOWED.**
     */
    case ScriptDeferral = 'script_deferral';

    /**
     * Reserved space for maps, video and booking embeds, so they do not shove
     * the page down when they arrive.
     */
    case EmbedSpaceReservation = 'embed_space_reservation';

    /**
     * WebP/AVIF conversion and responsive `srcset` — §4.1's *"on-server
     * convert"*.
     */
    case ImageOptimization = 'image_optimization';

    /**
     * The tiers `28` §4.1 permits this fix at, transcribed.
     *
     * ⚠️ **T0, T2 AND T4 NEVER APPEAR AND EACH IS ABSENT FOR ITS OWN REASON.**
     * T2 is §4.1's *"Edge (CF-proxied)"* column and every tick in it is real —
     * but `41` Part 5 makes T2 **auto-detect-only in v1**, so there is no edge
     * actuator to be permitted, and listing the tier would make
     * {@see ActuationTier::assertImplemented()} raise from inside a capability
     * question rather than at a write. T0 is 16e's product. T4 transmits
     * nothing: its answer to every row of §4.1 is the advisory rung.
     *
     * @return list<ActuationTier>
     */
    public function tiers(): array
    {
        return match ($this) {
            // §4.1's pixel column: ❌ for every row but preconnect and
            // measurement, and measurement is not a fix.
            self::PreconnectHints => [ActuationTier::T1, ActuationTier::T3],
            default => [ActuationTier::T1],
        };
    }

    /**
     * Whether `28` §4.1 permits this fix at this tier.
     */
    public function permittedAt(ActuationTier $tier): bool
    {
        return in_array($tier, $this->tiers(), true);
    }

    /**
     * The one field this fix writes into a change set.
     *
     * ⚠️ **ONE FIELD EACH, AND THE NAMES ARE NOT CMS FIELD NAMES.** A speed fix
     * is a directive to whatever layer can carry it — a filter, an HTMLRewriter
     * rule, a stylesheet line — rather than a value on a post. What the field
     * carries is typed by the caller that computes it;
     * {@see ChangeSet}'s own rule that a change set never
     * carries free-form HTML applies here unchanged.
     */
    public function field(): string
    {
        return match ($this) {
            self::ImageDimensions => 'image_dimensions',
            self::LazyLoadImages => 'lazy_loading',
            self::FontDisplaySwap => 'font_display',
            self::PreconnectHints => 'preconnect_origins',
            self::ScriptDeferral => 'deferred_scripts',
            self::EmbedSpaceReservation => 'embed_reservations',
            self::ImageOptimization => 'image_formats',
        };
    }

    /**
     * The `site_changes.change_type` this fix is recorded and quarantined under.
     *
     * ⛔ **ONE METHOD, READ BY THE WRITER AND BY THE QUARANTINE GATE ALIKE.**
     * 5817 records the hazard from the growth-page path, where the publisher
     * stamps the string in one file and the gate asks for it in another: a
     * quarantine keyed on a name one of them stopped using refuses **nothing,
     * for ever, silently**. Here there is nowhere for the two to drift apart,
     * which is a better answer than the lint that finding needed.
     */
    public function changeType(): string
    {
        return 'speed_'.$this->value;
    }

    /**
     * Every change type the speed layer owns.
     *
     * ⛔ **`SiteMeasurements::dueForMeasurement()` READS THIS TO STAY OUT OF THE
     * WAY, AND WITHOUT IT SLICE H WOULD JUDGE THESE ROWS ON THE WRONG
     * EVIDENCE** (5860). One `site_changes` row is measured by exactly one
     * measurer. H's asks whether visits to a *page* and clicks from Google held
     * up over days 14–30; §4.3 asks whether the *site* got slower over 7 days.
     * Left alone, H would have picked up every speed fix a fortnight after this
     * decider had already judged it and reverted it on a search wobble — a fifth
     * trigger nobody specified, on somebody else's website.
     *
     * @return list<string>
     */
    public static function changeTypes(): array
    {
        return array_map(static fn (self $fix): string => $fix->changeType(), self::cases());
    }

    /**
     * Whether one `site_changes` row belongs to the speed layer.
     *
     * ⛔ **THE SAME QUESTION AS {@see self::changeTypes()}, ASKED OF A ROW
     * RATHER THAN OF A QUERY, AND IT EXISTS BECAUSE THE QUERY WAS THE ONLY
     * PLACE ASKING** (5962). 5860 put the separation in one `whereNotIn`, so the
     * only thing keeping slice H's measurer off a speed fix was a predicate in a
     * method the measurer does not call: hand
     * `App\Services\Actuation\ChangeMeasurer::measure()` the id directly and it
     * judged a `font-display: swap` on page visits and Google clicks. **Which
     * measurer owns a row is a fact about the row**, so the sweep's query and
     * the measurer's own refusal both derive it from the one enum that defines
     * these change types, and neither is the only thing holding it.
     */
    public static function owns(string $changeType): bool
    {
        return in_array($changeType, self::changeTypes(), true);
    }

    /**
     * What an owner is told this fix did, in the words `29` §2 rule 47 asks for
     * — what happened to their website, never how it was done.
     */
    public function label(): string
    {
        return match ($this) {
            self::ImageDimensions => 'stopped pictures shoving the page around while they load',
            self::LazyLoadImages => 'stopped pictures further down the page loading before anyone scrolls to them',
            self::FontDisplaySwap => 'made your words readable while the fonts are still arriving',
            self::PreconnectHints => 'gave the browser a head start on the other services your pages use',
            self::ScriptDeferral => 'moved some third-party code out of the way of your page appearing',
            self::EmbedSpaceReservation => 'held space for maps and videos so they stop pushing the page down',
            self::ImageOptimization => 'made your pictures smaller to download without making them look worse',
        };
    }
}

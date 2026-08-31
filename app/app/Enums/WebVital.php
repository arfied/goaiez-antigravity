<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The four real-user metrics the pixel collects, and the units the warehouse
 * stores them in.
 *
 * ⚠️ **THE SET IS READ OFF THE PIXEL BUNDLE'S SOURCE, NOT REMEMBERED.**
 * `vitals()` emits `record('vital', { metric: 'LCP' | 'INP' | 'TTFB' | 'CLS',
 * value: … })` and nothing else, and
 * [[\App\Services\Pixel\PixelCollector::VITAL_METRICS]] is the door check
 * standing on the same four strings. **A lint in
 * `tests/Feature/Architecture/WarehouseTest.php` fails the build if the two
 * lists ever disagree** — a metric this enum knows and the collector refuses is
 * a mart column that is permanently empty, and a metric the collector accepts
 * and this enum drops is a measurement silently discarded at the derivation.
 *
 * ---------------------------------------------------------------------------
 * WHY EVERY VALUE HERE IS AN INTEGER IN A SMALLEST UNIT
 * ---------------------------------------------------------------------------
 * [[\App\Services\Warehouse\CanonicalJson]] already states the rule this obeys:
 * *"Telemetry that is naturally fractional is carried as an integer in its
 * smallest unit — the same rule CLAUDE.md already applies to money."* Three of
 * these four arrive as integer milliseconds already; CLS is the fraction, and
 * `L1Derivation::canonicalise()` renders it as a `%.17G` **string** (decision
 * 4867) precisely so that `serialize_precision` cannot reach it. Storing it as
 * `0.001` in a mart would put a float back into the one place the replay gate
 * cannot see through, so it is stored as **1**, in thousandths.
 *
 * ---------------------------------------------------------------------------
 * WHY THERE IS A BUCKET WIDTH AT ALL
 * ---------------------------------------------------------------------------
 * The vitals mart is a **histogram**, not a stored percentile — see its
 * creating migration's docblock for the whole argument. A histogram whose bucket is the
 * raw value is the size of the raw data, so each metric declares a width and
 * the stored bucket is the smallest multiple of that width **at or above** the
 * measurement.
 *
 * ⚠️ **NEITHER A DERIVED TABLE NOR THE BUNDLE'S PATH IS NAMED AS A LITERAL
 * ANYWHERE IN THIS FILE**, and both omissions are lints rather than taste:
 * [[DeviceClass]]'s docblock has the first, and the second is
 * `Architecture/PixelTest`'s *"exactly one file may deliver the pixel bundle"*
 * chokepoint, which matches the bundle's path across the whole of `app/` and
 * whose own instruction is to route through `PixelDelivery` rather than widen
 * its allowlist — so a docblock does not earn an entry on it.
 *
 * ⛔ **ROUNDED UP RATHER THAN DOWN, AND THAT IS THE ONE ASYMMETRY WORTH
 * READING.** The bucket is what the reader reports as the p75, so a bucket that
 * rounded down would report every site as marginally faster than it is —
 * `28` §4.3's *"never a fabricated win"* broken by a rounding rule. Rounding up
 * makes the reported figure an upper bound on the measurement, which errs
 * towards saying a site is slower.
 *
 * ⚠️ **THE WIDTHS ARE OURS AND THE TRADE IS WRITTEN DOWN**, on
 * `L1Derivation::PHONE_MAX_VIEWPORT`'s terms: the specification names none, so
 * guessing one and citing `28` for it is what CLAUDE.md's "verify against the
 * raw artefact" rule exists to stop. Each is chosen to be small against the
 * change `28` §4.3 asks a decider to notice (>10%): 25 ms is 1% of a 2,500 ms
 * LCP, 5 ms is 2.5% of a 200 ms INP, 10 ms is a little over 1% of an 800 ms
 * TTFB — and CLS's width of one thousandth is the pixel's **own** precision
 * (`Math.round(cls * 1000) / 1000`), so CLS loses nothing at all.
 * ⛔ **Changing a width changes every past replay's output**, which is why they
 * are constants on an enum and not registry rows.
 */
enum WebVital: string
{
    case Lcp = 'LCP';
    case Inp = 'INP';
    case Ttfb = 'TTFB';
    case Cls = 'CLS';

    /**
     * What one stored unit is worth, as a multiplier on the value the pixel
     * sends.
     *
     * `1` for the three millisecond metrics — the pixel already sends
     * `Math.round(…)`. `1000` for CLS, whose unit here is a thousandth.
     */
    public function unitScale(): int
    {
        return match ($this) {
            self::Lcp, self::Inp, self::Ttfb => 1,
            self::Cls => 1000,
        };
    }

    /**
     * The histogram bucket width, in this metric's stored unit.
     */
    public function bucketWidth(): int
    {
        return match ($this) {
            self::Lcp => 25,
            self::Inp => 5,
            self::Ttfb => 10,
            self::Cls => 1,
        };
    }

    /**
     * The largest value that can be stored, in this metric's stored unit.
     *
     * ⛔ **A CLAMP RATHER THAN A REJECTION, AND IT CLAMPS UPWARD.** The pixel
     * key is public, so `value` is ultimately whatever a stranger typed;
     * without a ceiling one hostile receipt would either overflow the column or
     * stretch the histogram to millions of buckets. Anything above the ceiling
     * is stored **at** the ceiling — the conservative direction, because it
     * keeps an implausibly slow measurement slow rather than discarding it and
     * flattering the site.
     *
     * ⚠️ **A NEGATIVE IS DROPPED RATHER THAN CLAMPED TO ZERO, WHICH IS THE
     * OPPOSITE HANDLING AND IS DELIBERATE.** the session mart's `active_s` learned
     * this the hard way (W23): `unsignedInteger()` is a plain `integer` in this
     * schema, so a negative survives every cast and stores a plausible-looking
     * wrong number. Clamping a negative *up* to zero would file it as the
     * fastest measurement on the site, which is a fabricated win with extra
     * steps.
     */
    public function ceiling(): int
    {
        return match ($this) {
            self::Lcp => 60_000,
            self::Inp => 10_000,
            self::Ttfb => 30_000,
            self::Cls => 5_000,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}

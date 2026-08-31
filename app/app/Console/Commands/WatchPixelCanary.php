<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OperatorAlertKind;
use App\Enums\PlatformHealthSignal;
use App\Models\PixelBundleVersion;
use App\Services\Config\DefaultsRegistry;
use App\Services\Ops\OperatorAlerts;
use App\Services\Pixel\PixelDelivery;
use App\Services\Pixel\PixelDeliveryHealth;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * §10's *"Canary 1% for 60 min, auto-halt on JS-error regression >0.5%"* — the
 * sweep that reads {@see PixelDeliveryHealth} and acts on it, decision 4980.
 *
 * ## What "regression" means, said precisely
 *
 * The canary's own `js_error` rate is compared against the Active version's own
 * rate — **not** a fixed error rate — over the identical window, since the
 * canary started. A page that already throws an error from a third-party script
 * on every load would otherwise trip on its very first canary; comparing
 * against a live baseline is what §10's ">0.5%" can honestly be checked
 * against.
 *
 * ## The floor, and why a small sample never trips this
 *
 * `pixel.canary_min_pageviews` (seeded 20, `ops.vendor_error_floor`'s own
 * value and reasoning): one `js_error` out of one pageview is a 100% rate, and
 * an auto-halt that fires on it teaches whoever reads the alert to raise the
 * threshold until it stops firing at all — decision 511, on a kill switch this
 * time rather than a pager.
 *
 * ## ⛔ NEITHER OF THIS SWEEP'S TWO FIGURES HAS AN OFF SWITCH AT ZERO, AND ONE
 * OF THEM USED TO CRASH THE SWEEP (7740–7759)
 *
 * `ops.alert_quiet_minutes`' own refusal message and its manifest description
 * both tell an operator that *"to quieten one noisy check rather than all of
 * them, set that check's own threshold to zero"*. That is 2409's convention and
 * it holds for the `PlatformHealthChecks` counters, each of which has an
 * explicit `<= 0 → return`. **It held for neither figure here.**
 *
 *   `pixel.canary_min_pageviews` at 0 made `0 >= 0` true against a build token
 *   with no traffic — the ordinary state of a canary in its first minutes — and
 *   the very next line divided by that zero. `everyFiveMinutes()`, so the sweep
 *   then threw 288 times a day, promoted nothing, halted nothing, and
 *   {@see OperatorAlertKind::PixelCanaryHalted} became a bell that could not
 *   ring. **Reproduced before it was fixed**, not inferred.
 *
 *   ⛔ **AND IT WOULD HAVE BEEN RECORDED AS A HEALTHY RUN — TRUE WHEN WRITTEN,
 *   HALF TRUE SINCE 2026-08-25, AND BOTH READINGS ARE KEPT** (4368, 9686).
 *   `ScheduleRunCommand` dispatches `ScheduledTaskFinished` **before** it checks
 *   the exit code, so `ScheduledRunMeter::finished()` writes the run down —
 *   fast, no failure — and its own docblock said why: *"it measures duration and
 *   not success"*. **That half is unchanged**: the `scheduled_run` row for a
 *   crash here still reads `total + 1, failures + 0` at a couple of hundred
 *   milliseconds, because `failures` on that signal means *overran its own
 *   lock* and this run did not.
 *
 *   ✅ **WHAT CHANGED IS THAT A SECOND ROW NOW SAYS IT FAILED.**
 *   {@see PlatformHealthSignal::ScheduledRunFailed} carries the exit
 *   code — read off `Event::$exitCode`, which `Event::run()` has already
 *   assigned by the time this entry's `ScheduledTaskFinished` is dispatched,
 *   because this is one of the five foreground entries — and
 *   `ops:schedule-runtimes` prints it in a column and again in a line of its
 *   own. ⛔ **"NOTHING PAGES ANYBODY ABOUT IT (9690), SO THE SENTENCE *A
 *   COMMAND THAT DIES FAST EVERY FIVE MINUTES FOR EVER WAKES NOBODY* SURVIVES
 *   THIS CORRECTION INTACT AND THIS COMMAND IS THE ENTRY IT WAS WRITTEN
 *   ABOUT" WAS TRUE FOR ONE WAVE AND IS NOT — CORRECTED 2026-08-26
 *   (9945–9959).** {@see OperatorAlertKind::ScheduledRunFailed}
 *   is rung from `ScheduledRunMeter` on every non-zero exit, so this
 *   command returning `self::FAILURE` now reaches an operator — once, and then
 *   not again about this command for
 *   `ScheduledRunMeter::FAILED_RUN_REPEAT_HOURS` hours.
 *
 *   ⚠️ **WHAT SURVIVES IS THE HALF ABOUT THE `scheduled_run` ROW**, which is
 *   the paragraph above and is untouched: a crash here is still written down as
 *   a fast, healthy run on that signal, because `failures` there means
 *   *overran its own lock*.
 *
 *   `pixel.canary_halt_regression_bp` at 0 is the opposite of an off switch: it
 *   halts on the smallest expressible regression, and a halt is not a quiet bell
 *   — {@see PixelDelivery::haltCanary()} rolls the release back, so following
 *   that instruction here stops pixel rollouts. **Below** zero it was worse
 *   still: `regressionBp` is already `max(0, …)`, so any negative made the
 *   comparison unconditionally true and a canary **byte-identical to Active**
 *   was halted on the first sweep that found a sample.
 *
 * {@see self::sampleFloor()} and {@see self::haltThresholdBp()} are what the
 * sweep now reads, and they are public because the operator alert board prints
 * what this command will actually do — 7661's rule, learned from a clamp copied
 * into a screen that then went stale: **ask the owner, never repeat its
 * arithmetic.**
 *
 * ## Runs every five minutes, `messaging:watch-platform-complaint-rate`'s cadence
 *
 * A 60-minute canary window checked once an hour could miss a regression for
 * up to an hour; five minutes bounds the worst case to one sweep interval
 * without adding an O(tenants) cost this sweep does not have — it reads two
 * small counters, never walks a business.
 */
#[Signature('pixel:watch-canary')]
#[Description('Promote or halt a live pixel canary based on its JS-error rate against the Active version')]
final class WatchPixelCanary extends Command
{
    /** `PublishPixelBundle::ACTOR`'s reasoning; not passed to `promoteCanary()` — see its docblock. */
    public const string ACTOR = 'pixel:watch-canary';

    /**
     * The three `platform_settings` keys this sweep reads.
     *
     * ⛔ **CONSTANTS BECAUSE A SECOND READER ARRIVED** (7607's rule, applied to
     * the same shape one screen along). `Admin\OperatorAlertBoard` states it
     * outright — *"every key is read off a constant on the class that owns it …
     * a renamed key breaks this file rather than leaving it quietly printing
     * about a key nobody reads"* — and then had no constant to read for either
     * canary key, because there was none anywhere in `app/`. All three were
     * string literals in this method.
     */
    public const string WINDOW_KEY = 'pixel.canary_window_minutes';

    public const string FLOOR_KEY = 'pixel.canary_min_pageviews';

    public const string THRESHOLD_KEY = 'pixel.canary_halt_regression_bp';

    /**
     * The smallest sample floor that leaves a rate expressible.
     *
     * ⛔ **THIS IS THE DIVISION'S GUARD, AND IT IS THE ONLY ONE.** The sweep
     * divides `js_errors` by `pageviews` the moment both sides clear the floor,
     * so the floor being at least one is exactly what makes the denominator
     * non-zero — there is deliberately no second `> 0` beside the division,
     * because a guard nothing can drive is a guard nobody can prove (398).
     * **Moving this to 0 fails the test named for the crash**, which is how it
     * was checked.
     *
     * ⚠️ **CLAMPED RATHER THAN REFUSED, AND HERE THE CLAMP IS THE OPERATOR'S OWN
     * MEANING.** The manifest called a zero here *"disabling the floor rather
     * than the check"*, and a floor of one is that sentence come true: judge the
     * regression as soon as there is any traffic at all. A refusal would cost an
     * operator a value that does what they meant, so this key is **not** in
     * `DefaultsRegistry::assertPreconditionsMet()` — `ops.health_window_minutes`'
     * written reasoning verbatim, *"the read corrects a nonsense value and there
     * is no consequence to refuse"*.
     */
    public const int MIN_SAMPLE_FLOOR = 1;

    /**
     * The narrowest halt threshold that is a threshold rather than a trip.
     *
     * ⚠️ **ONE, NOT ZERO, AND THE DIFFERENCE IS ONLY VISIBLE AT THE WRITE.** A
     * threshold of zero halts on a single basis point of regression, which is a
     * defensible if noisy setting; what it is not is an off switch, and it is
     * the value an operator types believing it is one. So the **write** refuses
     * it with a sentence saying so, and the **read** honours a zero already
     * written rather than substituting a figure of its own — `benchmarks.min_cohort`'s
     * objection, which is that a screen showing one number while the platform
     * applies another is worse than either number alone. 7589's split, with the
     * halves doing different jobs than they do on `ops.alert_quiet_minutes`.
     */
    public const int MIN_REGRESSION_BP = 1;

    /**
     * The widest halt threshold that can still fire.
     *
     * `PlatformHealthChecks::MAX_ERROR_RATE_BP`'s finding on a second key: a
     * figure above the arithmetic maximum is not a looser threshold, it is an
     * **undocumented off switch** that leaves the row reading as configured.
     * Both rates are basis points of a share, so each is at most 10,000 and a
     * regression between them is at most 10,000 — ⚠️ **and the comparison here is
     * `>` rather than `>=`**, so the widest value that can still halt anything is
     * one below that. At 9,999 the release halts only when the canary throws an
     * error on every pageview and the Active version throws none.
     */
    public const int MAX_REGRESSION_BP = 9_999;

    /**
     * Refuse a halt threshold that is not one — the write half of 7589's split.
     *
     * Reached from {@see DefaultsRegistry::assertPreconditionsMet()}, so it
     * covers the Ops screen and `defaults:sync` alike rather than only the
     * screen (398: the guard everybody drives is not the guard on the path).
     *
     * @throws InvalidArgumentException when the value is not a whole number of
     *                                  basis points inside
     *                                  {@see self::MIN_REGRESSION_BP}–{@see self::MAX_REGRESSION_BP}
     */
    public static function refuseUnworkableHaltThreshold(mixed $value): void
    {
        if (! is_int($value)) {
            throw new InvalidArgumentException(
                'The canary halt threshold is a whole number of basis points — 50 is half a '
                .'percentage point. Clearing this box reads back as zero, which is not an off '
                .'switch here: it halts a release on a single basis point of regression.'
            );
        }

        if ($value < self::MIN_REGRESSION_BP) {
            throw new InvalidArgumentException(
                'Zero is not an off switch on this one, and neither is anything below it. This '
                .'threshold is how much worse a canary\'s JS-error rate may be than the version it '
                .'is replacing, and crossing it does not ring a bell and stop — it rolls the '
                .'release back, so a zero here halts every pixel update that is a single basis '
                .'point noisier than the last. There is no way to switch the automatic halt off, '
                .'deliberately. The narrowest this may be set to is '
                .self::MIN_REGRESSION_BP.' basis point.'
            );
        }

        if ($value > self::MAX_REGRESSION_BP) {
            throw new InvalidArgumentException(
                'A threshold of '.number_format($value).' basis points is '
                .number_format($value / 100, 1).' percentage points, and one error rate can differ '
                .'from another by at most 100 — so this would switch the automatic halt off while '
                .'leaving it looking configured. The widest this may be set to is '
                .number_format(self::MAX_REGRESSION_BP).' basis points, which halts a release only '
                .'when the canary throws an error on every pageview and the version it is '
                .'replacing throws none.'
            );
        }
    }

    /**
     * The sample floor this sweep will actually apply.
     *
     * The read half of 7589's split: a write guard reaches values typed after it
     * ships, and no document in this repository may state what a running install
     * has configured — so a row already holding `0` is unreachable from any
     * refusal and is corrected here instead.
     */
    public static function sampleFloor(DefaultsRegistry $registry): int
    {
        return max(self::MIN_SAMPLE_FLOOR, $registry->int(self::FLOOR_KEY));
    }

    /**
     * The halt threshold this sweep will actually apply.
     *
     * ⚠️ **THE FLOOR OF THIS CLAMP IS ZERO WHERE THE WRITE'S IS ONE**, which is
     * not an oversight — see {@see self::MIN_REGRESSION_BP}. What a read cannot
     * honour is a **negative**: `regressionBp` is `max(0, …)`, so a negative
     * threshold halts a canary that has no regression at all, which is not a
     * tighter setting but a different behaviour. What it can honour is a zero,
     * and `Admin\OperatorAlertBoard` says out loud what one does rather than
     * this method quietly deciding otherwise.
     */
    public static function haltThresholdBp(DefaultsRegistry $registry): int
    {
        return min(self::MAX_REGRESSION_BP, max(0, $registry->int(self::THRESHOLD_KEY)));
    }

    public function handle(
        DefaultsRegistry $registry,
        PixelDelivery $delivery,
        PixelDeliveryHealth $health,
        OperatorAlerts $alerts,
    ): int {
        $canary = $delivery->currentCanary();

        if (! $canary instanceof PixelBundleVersion) {
            $this->line('No canary is live.');

            return self::SUCCESS;
        }

        $active = $delivery->currentActive();

        if (! $active instanceof PixelBundleVersion) {
            // Structurally should not happen — PixelDelivery::publish() only
            // creates a Canary row when an Active one already exists — but a
            // sweep that crashes on a state it did not expect is worse than one
            // that reports it and does nothing this run.
            $this->error('A canary is live with no Active version to compare it against.');

            return self::FAILURE;
        }

        if (! $canary->canary_started_at instanceof Carbon) {
            // Structurally should not happen — PixelDelivery::publish() always
            // stamps this the moment a row becomes Canary — but the column is
            // nullable, so a row that reached this state some other way is
            // reported rather than crashing the sweep.
            $this->error("Canary {$canary->sha} has no canary_started_at. Nothing was changed.");

            return self::FAILURE;
        }

        $since = CarbonImmutable::instance($canary->canary_started_at);
        $windowMinutes = $registry->int(self::WINDOW_KEY);
        $floor = self::sampleFloor($registry);
        $thresholdBp = self::haltThresholdBp($registry);

        $canaryRates = $health->ratesSince($canary->build_token, $since);
        $activeRates = $health->ratesSince($active->build_token, $since);

        $this->line(sprintf(
            'Canary %s since %s: %d pageviews / %d js_errors. Active %s: %d pageviews / %d js_errors.',
            $canary->sha,
            $since->toIso8601String(),
            $canaryRates['pageviews'],
            $canaryRates['js_errors'],
            $active->sha,
            $activeRates['pageviews'],
            $activeRates['js_errors'],
        ));

        $windowElapsed = CarbonImmutable::now()->gte($since->addMinutes($windowMinutes));
        $haveSample = $canaryRates['pageviews'] >= $floor && $activeRates['pageviews'] >= $floor;

        if ($haveSample) {
            $canaryBp = (int) round(($canaryRates['js_errors'] / $canaryRates['pageviews']) * 10_000);
            $activeBp = (int) round(($activeRates['js_errors'] / $activeRates['pageviews']) * 10_000);
            $regressionBp = max(0, $canaryBp - $activeBp);

            if ($regressionBp > $thresholdBp) {
                $this->halt($delivery, $alerts, $canary, $canaryBp, $activeBp, $regressionBp, $thresholdBp);

                return self::SUCCESS;
            }
        }

        if (! $windowElapsed) {
            $this->line($haveSample
                ? 'No regression found yet. Window still open.'
                : 'Not enough traffic yet to judge a regression ('.self::FLOOR_KEY.'). Window still open.');

            return self::SUCCESS;
        }

        // ⚠️ **PROMOTED EVEN WITHOUT MEETING THE FLOOR, IF THE WINDOW HAS
        // ELAPSED.** §10 fixes the window at 60 minutes; a canary parked
        // forever because a low-traffic period never produced enough samples to
        // judge is not what "for 60 min" means, and an absence of evidence is
        // not evidence of a regression. This lane's own choice, named as one.
        $delivery->promoteCanary($canary);

        $this->info("Window elapsed with no regression found. {$canary->sha} promoted to Active.");

        return self::SUCCESS;
    }

    private function halt(
        PixelDelivery $delivery,
        OperatorAlerts $alerts,
        PixelBundleVersion $canary,
        int $canaryBp,
        int $activeBp,
        int $regressionBp,
        int $thresholdBp,
    ): void {
        $reason = sprintf(
            'js_error rate %d bp vs Active\'s %d bp — %d bp regression, over the %d bp threshold.',
            $canaryBp,
            $activeBp,
            $regressionBp,
            $thresholdBp,
        );

        $delivery->haltCanary($canary, $reason);

        $this->error("CANARY HALTED: {$canary->sha}. {$reason}");

        $alerts->raise(
            OperatorAlertKind::PixelCanaryHalted,
            // ⚠️ `build_token`, NOT `sha` — `operator_alerts.subject` is 60
            // characters and a sha256 hex digest is 64. `build_token` is the
            // shorter of the two identifiers this row carries and is equally
            // unique per version.
            $canary->build_token,
            "A pixel bundle update ({$canary->sha}) was halted automatically after a JS-error regression. {$reason}",
            [
                'canary_sha' => $canary->sha,
                'canary_error_rate_bp' => $canaryBp,
                'active_error_rate_bp' => $activeBp,
                'regression_bp' => $regressionBp,
                'threshold_bp' => $thresholdBp,
            ],
        );
    }
}

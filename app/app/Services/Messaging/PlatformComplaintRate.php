<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Console\Commands\WatchPlatformComplaintRate;
use App\Enums\OutreachChannel;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The platform's complaint rate, summed out of every tenant's own counters —
 * 2119(b), and the input to the automatic global halt that 2102 requires and
 * 2118 records was never built.
 *
 * ## Why this reads L2's counters rather than measuring anything itself
 *
 * ⚠️ **A SECOND MEASUREMENT OF THE SAME FACT IS THE DEFECT 2186 NAMES**: *"what
 * must not happen is a second complaint counter beside this one, because then
 * the trip reads one of them."* `sending_health_windows` is the counter; the
 * per-tenant trip reads it; so the platform trip reads it too, and the two
 * switches can never disagree about what happened. That is also why this class
 * holds no SQL of its own — it asks {@see SendingHealth} the same question the
 * guard asks, once per tenant.
 *
 * ## The aggregate, not the worst tenant
 *
 * 2101's damage is cumulative: tenant number allocation puts every tenant's traffic on the **GOAIEZ**
 * 10DLC brand and our own number pool, so what a carrier scores is the total. A
 * platform halt driven by whichever single tenant happened to be worst would
 * fire constantly on small accounts — one STOP out of four deliveries is
 * 2,500bp — and would miss the case it exists for, which is a hundred accounts
 * each slightly over. **So the numerators and denominators are summed and the
 * rate is computed once, at the end.**
 *
 * ⛔ **SUMMING THE RATES WOULD BE WRONG AND WOULD LOOK RIGHT.** A mean of
 * per-tenant percentages weights a tenant who sent four messages the same as one
 * who sent forty thousand. The arithmetic here is deliberately
 * `sum(complaints) / sum(delivered)`, which is the figure a carrier actually
 * sees.
 *
 * ⛔ **AND THE AGGREGATE HIDES EXACTLY ONE THING, WHICH IS WHY THIS RETURNS A
 * {@see PlatformSweep} RATHER THAN A BARE SAMPLE** (7720–7739). *"Nothing at all
 * came back"* does not survive summation: one tenant reporting normally puts a
 * non-zero `delivered` into the sum, and
 * {@see PlatformRateSample::reportingHasGoneSilent()} — the only thing that can
 * see 2102's halt reading a rate measured over nothing — is then **false for
 * everybody**, however many other tenants are blind. The rate stays an aggregate
 * for the reason above; the blindness is kept per tenant, because it is a fact
 * about one account's carrier route rather than about the platform's standing.
 *
 * ## Why the tenant list is a parameter and not something this class finds out
 *
 * `sending_health_windows` is `FORCE ROW LEVEL SECURITY` with a policy keyed on
 * the session tenant, so a `DB::table(...)->sum(...)` across every tenant
 * **returns nothing at all** rather than failing loudly — with no tenant set,
 * `current_setting('app.business_id', true)` is `''`, `nullif` makes it NULL, and
 * `business_id = NULL` is never true. ⚠️ **The obvious implementation is
 * therefore not merely wrong, it is silently and permanently zero**, which reads
 * as a healthy platform and would keep the halt from ever firing. Reaching every
 * tenant means walking them, and walking them means dropping a global scope.
 *
 * ⛔ **AND A SERVICE IS THE WRONG PLACE FOR THAT.** Every one of the nine
 * `withoutGlobalScopes()` sites this codebase permits is a **console command**,
 * behind shell access, each with a written reason in `TenancyTest`. A service
 * that could enumerate every business is reachable from a web request, and the
 * lint that stopped this being written the easy way is the reason the list
 * arrives as an argument instead. {@see WatchPlatformComplaintRate}
 * does the owner walk, on `RefreshOauthTokens`' pattern.
 *
 * What this class keeps is the half that must not move: each tenant's numbers
 * are read **from inside that tenant's own context**, which is
 * `RollUpNumberHealth`'s rule and what makes a per-tenant figure that tenant's
 * rather than a cross-tenant read wearing a tenant's name.
 */
final readonly class PlatformComplaintRate
{
    /**
     * Where the last sweep leaves its reading for a screen to find.
     *
     * ⚠️ **A DISPLAY RECORD AND NEVER A DECISION INPUT** (4374–4376).
     * {@see WatchPlatformComplaintRate} re-measures on every run and never reads
     * this back, so a stale, wrong or missing entry cannot change what halts —
     * it can only change what an operator is shown, and what they are shown
     * carries its own timestamp.
     *
     * ⛔ **THE CACHE, RATHER THAN A TABLE OR A REGISTRY ROW.** A table would
     * need a migration, an RLS exemption and a prune for a figure that is
     * replaced every fifteen minutes; a registry row would write a
     * `registry_changes` entry ninety-six times a day and bury every real
     * setting change under it. What the cache costs is durability, which this
     * does not need: a flush renders as *"not measured"* rather than as a
     * confident zero, and the next sweep refills it.
     */
    public const string LAST_SAMPLE_CACHE_KEY = 'messaging:platform-complaint-sample';

    /**
     * How long a reading stays worth showing.
     *
     * Far longer than the fifteen-minute schedule, so an ordinary run keeps it
     * fresh and a sweep that has stopped running expires it — the screen then
     * says nothing has been measured, which is exactly what has happened.
     */
    public const int LAST_SAMPLE_TTL_HOURS = 24;

    public function __construct(
        private SendingHealth $health,
        private readonly DefaultsRegistry $registry,
    ) {}

    public function lastSampleTtlHours(): int
    {
        return $this->registry->int('messaging.complaint_rate.last_sample_ttl_hours');
    }

    /**
     * Leave this reading where the sending-controls screen can find it.
     *
     * ⚠️ **NOTHING HERE MAY PREVENT A HALT.** The sweep's job is to stop the
     * platform; recording a figure for a screen is the least important thing it
     * does, and a cache store that threw — a full disk on the database cache
     * driver, a Redis that went away — would otherwise take the containment down
     * with it. So the failure is logged and swallowed, which is the one place in
     * this class that direction is correct.
     *
     * ⛔ **AND THE LOG LINE IS ITSELF INSIDE A `try`, WHICH IS NOT BELT AND
     * BRACES** (4499). The failure this method is written against is *a full
     * disk*, and a full disk is precisely the condition under which
     * `Log::warning()` throws too — so the swallow that exists to protect the
     * containment would have re-raised out of `report()` and past the halt
     * decision in {@see WatchPlatformComplaintRate::handle()}, which sits below
     * this call. A rescue whose own rescue can throw is not one.
     *
     * ⚠️ **NO TENANT, NO BUSINESS ID, NO NAME.** Six aggregate integers. The
     * same reason `platform_halt_incidents` carries none: naming the
     * worst-performing tenant in a record support reads routinely would leak one
     * tenant's standing to everybody who opens the screen.
     *
     * ⚠️ **IT WAS FOUR UNTIL 2026-08-22 AND AN ENTRY WRITTEN BEFORE THEN IS
     * TREATED AS ABSENT, NOT AS A PLATFORM THAT SENT NOTHING** (7487).
     * {@see self::lastReported()} requires every key it wrote, so a cache entry
     * from the previous shape answers null and the panel says the sweep has not
     * reported — until the next run, twenty minutes later, replaces it. **That
     * is the correct direction and it is the reason the keys are checked rather
     * than defaulted**: a missing `sent` defaulted to `0` would render a
     * platform that had sent nothing, which is exactly the false reassurance
     * these two counters were added to remove.
     */
    public function report(PlatformRateSample $sample, ?CarbonImmutable $at = null): void
    {
        try {
            Cache::put(self::LAST_SAMPLE_CACHE_KEY, [
                'delivered' => $sample->delivered,
                'complaints' => $sample->complaints,
                'tenant_count' => $sample->tenantCount,
                'window_hours' => $sample->windowHours,
                'sent' => $sample->sent,
                'failed' => $sample->failed,
                'measured_at' => ($at ?? CarbonImmutable::now())->toIso8601String(),
            ], now()->addHours($this->lastSampleTtlHours()));
        } catch (Throwable $e) {
            try {
                Log::warning('The platform complaint reading could not be stored for display.', [
                    'exception' => $e::class,
                ]);
            } catch (Throwable) {
                // Nowhere left to say it. Silence is the correct outcome: the
                // only thing above this in the stack is a platform halt, and a
                // display record must never be the reason one does not fire.
            }
        }
    }

    /**
     * The last reading the sweep left, or null if it has not run.
     *
     * ⛔ **NULL RATHER THAN AN EMPTY SAMPLE**, which is {@see RateReading}'s
     * rule one layer up: a `PlatformRateSample` of zeroes renders as a perfect
     * platform, and *"the sweep has not reported"* and *"nobody has complained"*
     * are not the same statement. The caller is made to handle the absence.
     *
     * ⚠️ **THE TIMESTAMP IS PARSED DEFENSIVELY, WHICH THE FIVE CHECKS ABOVE IT
     * WOULD OTHERWISE MAKE POINTLESS** (4499). `CarbonImmutable::parse()` throws
     * on a string it cannot read, and this ran unguarded after five careful
     * key-existence tests — so a truncated or colliding cache entry would 500
     * **the one screen an operator opens when sending is broken**. Treated as
     * absent on the same terms as a missing key: half a sample is not a sample.
     *
     * @return array{sample: PlatformRateSample, measuredAt: CarbonImmutable}|null
     */
    public function lastReported(): ?array
    {
        $stored = Cache::get(self::LAST_SAMPLE_CACHE_KEY);

        if (! is_array($stored)) {
            return null;
        }

        foreach (['delivered', 'complaints', 'tenant_count', 'window_hours', 'measured_at', 'sent', 'failed'] as $key) {
            if (! array_key_exists($key, $stored)) {
                // A shape this class did not write — an older format, or a key
                // collision. Treated as absent rather than partially trusted:
                // half a sample renders a rate with the wrong denominator.
                return null;
            }
        }

        try {
            $measuredAt = CarbonImmutable::parse((string) $stored['measured_at']);
        } catch (Throwable) {
            return null;
        }

        return [
            'sample' => new PlatformRateSample(
                delivered: (int) $stored['delivered'],
                complaints: (int) $stored['complaints'],
                tenantCount: (int) $stored['tenant_count'],
                windowHours: (int) $stored['window_hours'],
                sent: (int) $stored['sent'],
                failed: (int) $stored['failed'],
            ),
            'measuredAt' => $measuredAt,
        ];
    }

    /**
     * Add up the window across the tenants handed in — **and keep the ones whose
     * own receipts have gone silent, which the sums cannot show** (7720–7739).
     *
     * ⚠️ **LEAVES NO TENANT ESTABLISHED.** This moves through many tenants'
     * contexts in turn; a console process that exits with one still set leaks it
     * into whatever inherits the connection under a pooler, and
     * `RefreshOauthTokens` makes the same point at the same place. A caller that
     * had a tenant established does not get it back, which is why nothing on a
     * request path calls this.
     *
     * ## ⛔ The per-tenant answer used to be computed here and thrown away
     *
     * Every tenant's `SendingRates` passes through this loop and only five
     * integers survived it, so *"tenant 42 sent four thousand messages and not
     * one outcome came back"* was measured on every sweep and discarded — while
     * the sums it fed made the platform predicate **false for everybody** as soon
     * as any one other tenant's receipts were working. That is
     * {@see PlatformRateSample::reportingHasGoneSilent()}'s honest limit rather
     * than a defect in it: it asks whether the *platform* went silent, and a
     * platform with one healthy customer has not.
     *
     * ⚠️ **THE PREDICATE IS `SendingRates::trafficWithoutOutcomes()` AND NOT A
     * SECOND ARITHMETIC.** It is the identical test `SendingGuard::reportBlindSpot()`
     * applies one tenant at a time on the send path, so the sweep and the guard
     * can never come to disagree about whether a given tenant is blind — 2186's
     * rule about the counter, applied to the question asked of it.
     *
     * ⚠️ **NOTHING IS DECIDED HERE (R25).** This records who is blind; it refuses
     * nothing, pauses nothing and rings nothing. {@see WatchPlatformComplaintRate}
     * is the raiser.
     *
     * @param  iterable<int>  $businessIds  every tenant to include — see the class docblock for who produces it
     * @param  int  $tenantFloor  how many messages one tenant must have handed
     *                            over before its own silence means anything —
     *                            `messaging.complaint_trip_min_delivered`, the
     *                            floor of the very trip that goes blind (2409).
     *                            ⛔ **REQUIRED RATHER THAN DEFAULTED TO ZERO, ON
     *                            7487's ARGUMENT**: a forgotten default answers
     *                            *"nobody is blind"* at every call site that
     *                            omits it, which is a detector that is present,
     *                            plausible and off. A non-positive value is the
     *                            caller stating that the per-tenant trip is
     *                            switched off, and then there is nothing to be
     *                            blind about.
     */
    public function measure(OutreachChannel $channel, iterable $businessIds, int $tenantFloor): PlatformSweep
    {
        $delivered = 0;
        $complaints = 0;
        $tenants = 0;
        $sent = 0;
        $failed = 0;

        /** @var array<int, int> $blind */
        $blind = [];

        foreach ($businessIds as $businessId) {
            $rates = Tenancy::actingAs(
                $businessId,
                fn (): SendingRates => $this->health->rates($channel),
            );

            // ⚠️ Counted whether or not this tenant sent anything. A tenant with
            // an empty window contributes zero to both sums and one to the
            // count, which is correct: the count answers "how broad is this
            // sample", not "how many tenants are in trouble".
            $delivered += $rates->delivered;
            $complaints += $rates->complaints;
            $tenants++;

            // ⛔ **SUMMED FOR ONE QUESTION AND NEVER FOR THE RATE** (7487).
            // `PlatformRateSample::reportingHasGoneSilent()` is the only reader:
            // without them, a carrier reporting under an id this application
            // never stored switches 2102's halt off across every tenant at once
            // and this sweep reads a spotless platform. The rate below still
            // divides by `delivered`, which is 1645's lesson and unchanged.
            $sent += $rates->sent;
            $failed += $rates->failed;

            // ⛔ **ASKED PER TENANT, INSIDE THAT TENANT'S OWN CONTEXT, AND KEPT**
            // (7720). This is the half the sums destroy: one healthy tenant
            // makes the aggregate answer false while this one still answers
            // true. `$rates->sent` rather than a recount — it is the same
            // rolling 24-hour figure the aggregate uses, so the bell's total and
            // the sweep's total are the same measurement.
            if ($rates->trafficWithoutOutcomes($tenantFloor)) {
                $blind[$businessId] = $rates->sent;
            }
        }

        Tenancy::forgetAll();

        return new PlatformSweep(
            sample: new PlatformRateSample(
                delivered: $delivered,
                complaints: $complaints,
                tenantCount: $tenants,
                windowHours: app(SendingHealth::class)->windowHours(),
                sent: $sent,
                failed: $failed,
            ),
            blindTenants: $blind,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OperatorAlertKind;
use App\Jobs\ArchivePixelBatchJob;
use App\Models\Business;
use App\Models\EtlRun;
use App\Models\User;
use App\Services\Ops\OperatorAlerts;
use App\Services\Warehouse\ObjectStoreL0Archive;
use App\Services\Warehouse\Replayer;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.8's replay job.
 *
 * *"replay --tenant=<uuid|all> --from=YYYY-MM-DD --to=YYYY-MM-DD
 * --layers=L1,L2"* — with `--business` where the specification writes
 * `--tenant`, because this schema's tenant key is `businesses.id`.
 *
 * ⚠️ **`--layers` IS NOT IMPLEMENTED AND IS NOT ACCEPTED**, rather than accepted
 * and ignored. L2 here is derived from L1 over the same range, so rebuilding L1
 * without L2 leaves marts describing rows that no longer exist — a flag that
 * produced that state on request would be a supported way to corrupt the
 * warehouse. An option this command silently ignored would be worse still:
 * `29`'s own §12.1 warns about a control that reads as present.
 *
 * ⛔ **THIS SAID "L2 IS A **PURE FUNCTION** OF L1 OVER THE SAME RANGE" UNTIL
 * 2026-08-22 AND THAT WORD IS WRONG — BOTH READINGS KEPT** (7840–7859). Two
 * derivations reach outside the range: `l2_fact_session.day` is decided by how
 * the range was cut, and `is_new` by whether the retention sweep has run. The
 * paragraph's *conclusion* about `--layers` is untouched and is if anything
 * stronger; what was false is the premise it leaned on, which is the premise a
 * later reader would use to justify scheduling this. See {@see Replayer}'s class
 * docblock.
 *
 * ⚠️ **THE FIRST OF THOSE TWO IS NO LONGER `l2_fact_session.day`, AND THE
 * SENTENCE IS STILL TRUE — CORRECTED 2026-08-22** (8040, 8041; 8100–8119). The
 * owner took that column off the session grain, so a session is one row whatever
 * the cut. ⛔ **What a cut still decides is the row's *contents***: `pageviews`,
 * `duration_s`, `active_s`, `is_engaged` and `is_new` are aggregated over the
 * range, so a session replayed a day at a time ends up describing only the day
 * replayed last, and its rollup counts it on two days at once. **`--layers` is
 * refused for exactly the same reason and the premise is repaired rather than
 * the conclusion.**
 *
 * ⛔ **THIS COMMAND IS NOT SCHEDULED AND MUST NOT BE.** A replay deletes and
 * rebuilds a range; running it on a timer means the warehouse is continuously
 * rewritten by a job nobody watched, and the day a derivation regresses it
 * overwrites the good rows with the bad ones on its own. §5.8 describes an
 * operator action, and `etl_runs.snapshot_digest` is what makes the result
 * comparable with the last one.
 *
 * ⛔ **AND THAT PARAGRAPH WAS RIGHT FOR A REASON IT DID NOT KNOW — 2026-08-22
 * (7840–7859).** §18 asks for a **roll-up** — *"roll up to L2 before TTL expiry
 * or benchmarks silently degrade"* — nothing performs one (7707), and the
 * obvious remedy is to put this command on a timer. **It cannot be.** A nightly
 * replay of the last day or two is a second derivation that disagrees with a
 * full-range replay of the same L0, on `l2_fact_session` and through it on
 * `l2_fact_daily_tenant.sessions` — which is `28` §4.3's conversion trigger's
 * denominator and its `≥100 sessions` floor.
 * `tests/Feature/Warehouse/IncrementalRollUpTest.php` is the proof, driven on
 * the same fixture `ByteIdenticalReplayTest` is built from.
 * ⚠️ **8040 DID NOT CHANGE THIS AND IT IS THE PARAGRAPH MOST LIKELY TO BE READ
 * AS THOUGH IT HAD.** That test is still red-by-design and still on the same
 * fixture; what moved is the *form* of the disagreement, not its existence.
 *
 * ⚠️ **WHAT THAT COSTS, STATED PLAINLY, BECAUSE IT IS A GUARANTEE AND NOT A
 * FEATURE.** `CLAUDE.md`'s critical rules say every site change is *"measured
 * 14–30 days"* with *"auto-rollback on regression"*.
 * `App\Services\Actuation\SpeedDecider` reads three marts and nothing writes any
 * of them on any deployment, so all four of `28` §4.3's triggers answer
 * `InsufficientData` and **no speed fix can ever be rolled back**;
 * `App\Services\Actuation\ChangeMeasurer` keeps only its Search Console half,
 * which is site-wide and — by `ChangeComparison`'s own asymmetry — may reach
 * `Regressed` and never `Improved`. **The gap is real, this command is not the
 * way to close it, and what has to happen first is in the decision rows.**
 *
 * ⚠️ **IN PRODUCTION TODAY EVERY RANGE IS EMPTY** (decision 4861): the collector
 * is unbuilt, so nothing has ever written a byte to L0.
 * ⛔ **THAT SENTENCE'S REASON STOPPED BEING TRUE AT 4960–4979 AND ITS CONCLUSION
 * DID NOT — BOTH READINGS KEPT AND DATED.** The collector **is** built
 * (`POST /api/pixel/e` → `PixelCollector` → `ArchivePixelBatchJob` →
 * `ObjectStoreL0Archive`). What is still true is the part that matters here: no
 * tenant has pasted a snippet, so no production L0 object has ever come from
 * anywhere but a test, and every production range is still empty.
 *
 * ## ⛔ A QUIET RESULT USED TO BE UNCONDITIONALLY REPORTED AS SUCCESS (9845, 9846)
 *
 * ⛔ **THIS COMMAND DELETES A RANGE AND REBUILDS IT, AND FINDING NOTHING TO
 * REBUILD FROM WAS NOT AN ERROR.** `0 objects, 0 lines → 0 L1, 0 L2` and exit
 * `0` was printed for a range whose archive had been lost and for a Sunday with
 * no visitors alike, and **nothing anywhere compared objects found against
 * objects expected.** Two things changed and they answer different halves:
 *
 *  1. {@see Replayer::refuseAShortArchive()} **refuses before the delete** where
 *     it can prove the archive is short — fewer objects than a previous replay
 *     of the identical range recorded, or no objects at all over a range that
 *     still holds L1 rows. Those arrive here as an ordinary refusal:
 *     {@see self::replayOne()} names the business, prints the sentence, and the
 *     run exits non-zero with **the warehouse untouched.**
 *  2. {@see self::warnIfTheArchiveWasFailing()} covers the case a replay
 *     structurally cannot see — a **total** loss, where L0 and L1 are both empty
 *     and agree. It reads the archive's own bell back and says so, without
 *     refusing and without moving the exit code.
 *
 * ⛔ **NEITHER OF THEM COUNTS EVENTS, BECAUSE NOTHING IN THIS APPLICATION
 * COUNTS ACCEPTED BEACONS.** A partial loss with no prior run of the same bounds
 * is invisible from in here and the bell is the only record it happened.
 *
 * ## ⛔ THIS COMMAND HAD NEVER BEEN RUNNABLE, ANYWHERE, AND ITS OWN COMMENT IS
 * WHY NOBODY LOOKED (decisions 5955, 6131, 6180)
 *
 * Until 2026-08-20 the roster read below was `Business::query()` under a comment
 * reading *"`withoutGlobalScopes()` **IS NOT USED AND IS NOT NEEDED**. Business
 * is the tenant root and is scoped on its own key; a console run resolves no
 * tenant, so this reads the roster **the same way every other platform command
 * does.**"* **Both halves were false.** `Business` carries
 * `App\Concerns\IsTenantRoot`, whose scope calls `Tenancy::idOrFail()`, so
 * the read raised `App\Exceptions\TenantNotResolved` on every console run
 * there has ever been — with or without `--business`. And no other platform
 * command reads the roster that way: twenty-odd of them walk owners, and
 * {@see RefreshOauthTokens}, two files away, writes the opposite instruction out
 * in full.
 *
 * ⚠️ **THE PARAGRAPH IS WHAT MADE THE LINE BESIDE IT READ AS CONSIDERED**, which
 * is `CLAUDE.md` 314–316 at its most expensive: a reviewer who sees the hazard
 * named stops looking for the instance. **And nothing caught it because the
 * command had no test** — every replay test drives {@see Replayer} directly, so
 * the suite was green while the operator's only entry point could not start.
 * `tests/Feature/Warehouse/WarehouseReplayCommandTest.php` is that gap closed.
 *
 * ⛔ **AND `withoutGlobalScopes()` ALONE IS NOT THE FIX — IT IS THE SAME DEFECT
 * MADE SILENT.** Verified with a throwaway probe before this was written:
 * `businesses` is `ENABLE` + `FORCE` row-level security with policies on
 * `app.business_id` and `app.user_id`, so dropping the application scope with
 * neither established returns **zero rows rather than an error** (569) — the
 * command would have printed *"No business matched. Nothing was replayed."* on a
 * platform full of tenants and exited non-zero. The enumeration therefore goes
 * through owners, which is the pattern {@see CheckGrandfatheredPricing},
 * {@see MeasureSiteChanges} and `App\Services\Warehouse\NetworkBenchmarks`
 * already use and `tests/Feature/Architecture/TenancyTest.php` allowlists one by
 * one.
 *
 * ## ⛔ THERE IS NO MAXIMUM SPAN, AND ONE WAS REFUSED RATHER THAN FORGOTTEN
 * (8366)
 *
 * `--from` and `--to` are checked for order and for nothing else: no maximum
 * range, no chunking, no cursor. L0 is kept **for ever** (see
 * {@see ObjectStoreL0Archive}), so an operator may
 * legitimately ask for five years, and with no `--business` the roster walk meets
 * whatever that costs **once per tenant, in one long-lived process**.
 *
 * ⛔ **A `--max-span` WOULD BE THE WRONG BOUND AND WOULD PUSH THE OPERATOR
 * TOWARD A WRONG ANSWER.** Refusing a long range only helps if a short one is
 * equivalent, and it is not: `l2_fact_session` aggregates a session **over the
 * range being replayed**, so `pageviews`, `duration_s`, `active_s`, `is_engaged`
 * and `is_new` all move when the cut moves (8117, and the paragraph above), and
 * `l2_fact_conversion`'s attribution reaches thirty days back. A range replayed
 * in pieces is a **different warehouse** from the same range replayed whole, and
 * `tests/Feature/Warehouse/IncrementalRollUpTest.php` is the standing proof. So a
 * span limit would refuse the correct operation and leave the operator with only
 * the incorrect one — which is worse than no limit at all.
 *
 * ⚠️ **THE REASON THE LIMIT LOOKED NECESSARY IS GONE AND THE REASONS IT STILL
 * MIGHT ARE NAMED.** Until 8360 a long range died on `memory_limit`; it no longer
 * does, and a replay's peak PHP heap is now flat in the size of the range
 * (`tests/Feature/Warehouse/ReplayMemoryTest.php`). What remains linear, in
 * order of size: `ObjectStoreL0Archive::paths()` returns every object key of the
 * range as one array, at a measured 149 bytes an object — about **three bytes an
 * event**, against the 2,125 bytes an event it replaced, so the ceiling moved
 * from roughly 240,000 events to roughly 170 million and is still not a bound;
 * the rebuild transaction stays open for the whole of L2, which on a large range
 * blocks vacuum on every mart; and 8230's planner defect, which is a wall clock
 * problem rather than a memory one and is unfixed.
 *
 * ⛔ **THAT LIST IS IN THE WRONG ORDER AND IS MISSING THE TWO LARGEST ITEMS —
 * CORRECTED 2026-08-23 (8501, 8502). BOTH READINGS KEPT** (4368), because the
 * paragraph is what a 3am operator reads before deciding how wide a range to
 * ask for, and every quantity it names is still linear.
 *
 *  1. ⛔ **`WarehouseSnapshot::digest()`'s WIRE BUFFER, WHICH IS LINEAR IN THE
 *     TENANT AND NOT IN THE RANGE.** PostgreSQL has no unbuffered result set —
 *     `WarehouseSnapshot::section()` says so — so `DB::cursor()` streams out of
 *     a buffer libpq has already filled whole, in the C heap, where PHP's
 *     `memory_limit` does not apply and `memory_get_peak_usage()` cannot see it.
 *     `digest()` reads `l1_events` and all six L2 marts **for the tenant, with
 *     no range predicate**, and runs on every replay. **Measured: a replay of
 *     ONE L0 object of fifty events, on a tenant holding 131,250 L1 rows, peaked
 *     at +128.7 MB of resident set — of which `digest()` alone was +128.5 MB —
 *     against a PHP-heap peak of 0.60 MB.**
 *  2. ⛔ **`Replayer`'s staging drain, which is the same buffer one range
 *     narrower.** `SELECT DISTINCT ON … FROM l1_replay_staging` is one statement
 *     over every staged row of the range. It is bounded by 1 above and can never
 *     be the peak: the two run one after the other, and a tenant is never
 *     smaller than one of its own ranges. ⚠️ **Which is why bounding it alone
 *     would change nothing an operator could measure.**
 *  3. `paths()` and the open transaction, as the paragraph above has them.
 *  4. `L0Archive::read()`, which is one whole object — fifty events today and
 *     whatever a batching collector writes tomorrow (8367).
 *
 * ⛔ **SO THE HONEST SENTENCE IS THAT A REPLAY'S COST IS SET BY THE TENANT'S
 * WHOLE WAREHOUSE AND NOT BY THE RANGE ASKED FOR**, and asking for a narrower
 * range does not make a replay cheaper in the quantity that gets a process
 * killed. **A `--max-span` would have been useless as well as wrong.**
 */
#[Signature('warehouse:replay {--business= : The business id, or omit for every business} {--from= : First UTC day, YYYY-MM-DD} {--to= : Last UTC day, YYYY-MM-DD, inclusive}')]
#[Description('Rebuild L1 and L2 from the L0 archive for a business and a day range.')]
final class WarehouseReplay extends Command
{
    public function handle(Replayer $replayer): int
    {
        try {
            $from = CarbonImmutable::parse((string) ($this->option('from') ?: 'yesterday'))->utc()->startOfDay();
            $to = CarbonImmutable::parse((string) ($this->option('to') ?: $from->toDateString()))->utc()->startOfDay();
        } catch (Throwable $e) {
            $this->error('Could not read the range: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($to->lessThan($from)) {
            $this->error('The range ends before it starts. --to is inclusive and must not precede --from.');

            return self::FAILURE;
        }

        $business = $this->option('business');

        // ⛔ **`--business=` WITH NOTHING AFTER IT REFUSES RATHER THAN WIDENING.**
        // An empty option is a typo, and reading it as *"every business"* turns a
        // slip of the finger into a rebuild of the whole platform's warehouse.
        // Narrowing is the fail-closed direction on a command that deletes and
        // re-derives.
        if ($business === '') {
            $this->error('--business was given with no id. Name one, or leave the option off to replay every business.');

            return self::FAILURE;
        }

        $replayed = 0;
        $refused = 0;

        if (is_string($business)) {
            // ⚠️ NAMING ONE BUSINESS NEEDS NO UNSCOPED READ AT ALL, and that is
            // `CheckGrandfatheredPricing`'s precedent rather than a shortcut:
            // the tenant is established first, so the ordinary global scope
            // resolves and `tenant_isolation` admits exactly the one row.
            //
            // ⚠️ **`find()` IS NOT WHAT NARROWS THIS AND SAYING OTHERWISE WOULD
            // BE 314–316's MISTAKE.** `IsTenantRoot`'s scope has already added
            // `id = Tenancy::idOrFail()` and row-level security has already
            // added the same predicate beneath it, so replacing this with
            // `Business::query()->first()` returns the same single row — a
            // mutation that survives, exactly as 398 predicts of a guard whose
            // outer layers refuse first. It is written this way because it says
            // what the line is for, not because it is doing the work.
            $subject = Tenancy::actingAs(
                (int) $business,
                fn (): ?Business => Business::query()->find((int) $business),
            );

            if ($subject instanceof Business) {
                $this->replayOne($replayer, $subject, $from, $to, $replayed, $refused);
            }
        } else {
            User::query()
                ->select('id')
                ->orderBy('id')
                ->chunkById(200, function (Collection $users) use ($replayer, $from, $to, &$replayed, &$refused): void {
                    foreach ($users as $user) {
                        $this->replayOwner($replayer, (int) $user->getKey(), $from, $to, $replayed, $refused);
                    }
                });
        }

        // Never leave a security context established after a console command:
        // the PostgreSQL session variable outlives this process's connection
        // under any pooler, and a worker inheriting it would start as whichever
        // tenant the loop happened to touch last.
        Tenancy::forgetAll();

        if ($replayed === 0 && $refused === 0) {
            $this->error('No business matched. Nothing was replayed.');

            return self::FAILURE;
        }

        // ⛔ **THE ONE THING A QUIET RANGE CANNOT TELL YOU ABOUT ITSELF.** See
        // the method's own docblock: a total archive loss and a day with no
        // visitors print the same line, and this is the only record that tells
        // them apart.
        $this->warnIfTheArchiveWasFailing($from);

        // ⛔ **A REFUSED TENANT MAKES THE WHOLE RUN NON-ZERO EVEN WHEN EVERY
        // OTHER TENANT REPLAYED.** An operator rebuilding a range needs to know
        // that part of it was not rebuilt, and a command that always exits zero
        // is one nobody reads (`CheckGrandfatheredPricing`'s reasoning).
        //
        // ⚠️ **THE TOTAL IS PRINTED BECAUSE THE PER-TENANT LINES SCROLL** (8506).
        // A roster walk over a full platform prints one line per business, so on
        // a hundred tenants the three that were not rebuilt are three lines in a
        // hundred and the exit code says only *"something"*. This is the line
        // that says how much.
        if ($refused > 0) {
            $this->error($refused.' of '.($replayed + $refused).' businesses were not replayed. Their warehouses are unchanged.');
        }

        return $refused > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Say so when the archive is known to have dropped batches inside this range.
     *
     * ## ⛔ The question this answers, and why nothing else could
     *
     * ⛔ **A RANGE WHOSE ARCHIVE NEVER LANDED IS OTHERWISE INDISTINGUISHABLE
     * FROM A RANGE WITH NO TRAFFIC.** {@see Replayer} refuses a rebuild it can
     * prove would destroy data, and there is one case it cannot see: **a total
     * loss.** Every batch of the day failed, so L0 holds no object *and* L1
     * holds no row, the two agree, and the line printed above reads
     * *"0 objects, 0 lines → 0 L1, 0 L2"* — which is exactly what a Sunday with
     * no visitors looks like. Nothing in this application counts accepted
     * beacons, so there is no expected figure to compare that against.
     *
     * ⚠️ **THE ONLY RECORD THAT THE BATCHES EXISTED IS THE BELL**
     * ({@see OperatorAlertKind::PixelArchiveFailed}, raised by
     * `ArchivePixelBatchJob::failed()`), and this is what reads it back at the
     * one moment an operator is deciding whether to trust a quiet replay.
     *
     * ## ⚠️ It warns and does not refuse, and does not move the exit code
     *
     * ⛔ **REFUSING WOULD BE WRONG AND WOULD GET TURNED OFF.** One archive
     * failure, ever, would then block every tenant's replay of every overlapping
     * range for as long as the alert row survives — which is decision 511 with a
     * command attached. ⚠️ **And the exit code is deliberately unmoved for the
     * same reason**: the run did what it was asked, and a non-zero exit here
     * would make the honest outcome and the refused one report identically.
     *
     * ## ⛔ What it cannot claim, because the shape of the signal is coarse
     *
     * ⛔ **IT NAMES NEITHER THE TENANT NOR THE DAY.** The bell's subject is the
     * **disk** and not a business — deliberately, so that one broken store pages
     * once rather than once per tenant — so what is knowable here is *"this
     * archive was failing somewhere in or after this window"* and never *"your
     * account lost events on the 14th"*.
     *
     * ⛔ **AND ITS SILENCE IS NOT EVIDENCE.** Three ways it fails open, all
     * stated rather than implied: alert rows are pruned on a horizon
     * ({@see OperatorAlerts::prune()}), so an old enough range reads clean;
     * the bell is de-duplicated per disk for
     * {@see ArchivePixelBatchJob::ARCHIVE_REPEAT_HOURS} hours, so it is a floor
     * on the number of incidents and never a count; and it is looked up under
     * **today's** `warehouse.l0_disk`, so a disk renamed since the failure is
     * invisible. **Quiet here means nothing was recorded, not that nothing was
     * lost.**
     *
     * ⚠️ **CONTAINED.** A pager that cannot be read must not stop a warehouse
     * being rebuilt — R25's rule pointed the other way round. A throw from the
     * container or the query is swallowed and the replay's own output stands.
     */
    private function warnIfTheArchiveWasFailing(CarbonImmutable $from): void
    {
        try {
            $disk = (string) config('warehouse.l0_disk');

            $rang = app(OperatorAlerts::class)->rangSince(
                OperatorAlertKind::PixelArchiveFailed,
                $disk,
                $from,
            );

            if (! $rang) {
                return;
            }

            $this->warn(
                '⚠️  The '.$disk.' archive reported losing pixel batches during or after this range, so a quiet '
                .'result above is not evidence of a quiet range. It is not known which accounts or days lost '
                .'events, or how many: read the failed jobs and the operator alerts for '.$disk.'.'
            );
        } catch (Throwable) {
            // A bell that cannot be read is not a reason to withhold a rebuild
            // that already happened.
        }
    }

    /**
     * Replay every business one person owns.
     *
     * ⚠️ **`withoutGlobalScopes()` BECAUSE THE SCOPE CALLS `Tenancy::idOrFail()`
     * AND NO TENANT IS ESTABLISHED YET** — the circularity `ResolveTenant`
     * documents. The database still restricts this to businesses owned by the
     * user just set, through the `owner_lookup` policy, so it cannot widen
     * beyond one person's own.
     *
     * ⚠️ **THE BLAST RADIUS, STATED RATHER THAN ASSUMED.** A business enumerated
     * by mistake yields the wrong tenant's *scope*, never another tenant's rows:
     * {@see Replayer::replay()} establishes the tenant itself and every derived
     * table it deletes from and writes to is FORCE row-level secured on
     * `business_id`, so a wrong id rebuilds an empty range for a tenant with no
     * L0 objects. **The dangerous direction — one tenant's events derived into
     * another's marts — is unreachable rather than unlikely**, because the
     * archive is read by business id under the same established tenant.
     */
    private function replayOwner(Replayer $replayer, int $userId, CarbonImmutable $from, CarbonImmutable $to, int &$replayed, int &$refused): void
    {
        Tenancy::setUser($userId);

        $businesses = Business::withoutGlobalScopes()
            ->where('owner_user_id', $userId)
            ->orderBy('id')
            ->get();

        foreach ($businesses as $subject) {
            $this->replayOne($replayer, $subject, $from, $to, $replayed, $refused);
        }
    }

    /**
     * Replay one business, reporting a refusal rather than dying on it.
     *
     * ⛔ **THE `LogicException` ARM OUTLIVED THE GUARD IT WAS WRITTEN FOR, AND
     * IT IS KEPT ON PURPOSE — 2026-08-30 (12539).** It was caught because
     * `PhiExclusion::refuse()` threw one, and that refusal is gone with `29` §2
     * rule 24. **What has not gone is the reason the catch is per-business**:
     * this method walks a roster, and any one tenant raising must not stop
     * every other tenant's warehouse being rebuilt.
     *
     * ⚠️ **AND THERE IS STILL A LIVE THROWER.** {@see L1Loader} raises a
     * `LogicException` when a batch carries another tenant's `business_id` — the
     * tenancy precondition that used to ride on the PHI reader and now stands on
     * its own. **So this arm is not vestigial**, and removing it as dead code
     * because rule 24 went would put the roster walk back where it was before
     * anybody thought about it.
     *
     * ⚠️ **NOTHING IS SWALLOWED AND THAT IS THE WHOLE OF WHY THE BROAD CATCH IS
     * ACCEPTABLE.** The message is printed in full, the business is named, and
     * the run exits non-zero — so a `LogicException` from anywhere reads as
     * loudly here as an uncaught one would, minus the stack trace.
     *
     * ## ⛔ THE CATCH WAS `LogicException` ONLY, AND THE PARAGRAPH ABOVE IS WHAT
     * STOPPED ANYBODY CHECKING WHICH EXCEPTIONS PASSED IT — CORRECTED 2026-08-23
     * (8506)
     *
     * *"Nothing is swallowed"* is an argument about the exception this catch
     * holds and says nothing about the ones that go straight past it, and there
     * were several. {@see ObjectStoreL0Archive::read()}
     * throws a **`RuntimeException`** on an object that is missing and on one
     * that is not readable gzip; `App\Services\Warehouse\CanonicalJson` throws
     * the same class on a line whose shape does not match the version it
     * declares. **One unreadable object at tenant 47 of 100 therefore killed the
     * process, and tenants 48 to 100 went unreplayed with nothing saying so** —
     * the operator sees a stack trace naming one business and a run that stopped,
     * on a command whose whole design elsewhere is *"a covered entity is
     * something the walk is supposed to meet."*
     *
     * ⚠️ **`Throwable` RATHER THAN A SECOND NAMED CLASS, ON THIS ARCHIVE'S OWN
     * PRECEDENT.** `ObjectStoreL0Archive::purgeFor()` and
     * `App\Services\Export\ExportBuilder::purgeAllFor()` both catch `Throwable`
     * for the identical reason — *"this is called from a sweep that walks every
     * due deletion, and one unreachable prefix must not abandon the rest of the
     * queue."* Enumerating classes here would be a list that goes stale the next
     * time a layer below throws something new, which is exactly how this one
     * came to be wrong.
     *
     * ⛔ **AND THE EXCEPTION CLASS IS NOW PRINTED, BECAUSE THE TWO OUTCOMES ARE
     * NOT THE SAME EVENT.** A `LogicException` from the PHI gate is the platform
     * working — a covered entity refused, by design. A `RuntimeException` from
     * the archive is a broken object in a store that is kept for ever and cannot
     * be repaired, so **every future replay of that range will fail
     * identically**. A line that reads the same for both would leave an operator
     * treating the second as routine.
     */
    private function replayOne(Replayer $replayer, Business $subject, CarbonImmutable $from, CarbonImmutable $to, int &$replayed, int &$refused): void
    {
        try {
            $run = $replayer->replay($subject, $from, $to);
        } catch (Throwable $e) {
            $refused++;

            $this->error('business '.$subject->getKey().' was not replayed ('.$e::class.'): '.$e->getMessage());

            return;
        }

        $replayed++;

        $this->line($this->summarise($subject, $run));
    }

    private function summarise(Business $business, EtlRun $run): string
    {
        // ⚠️ THE DIGEST IS PRINTED BECAUSE IT IS THE ONLY OUTPUT AN OPERATOR CAN
        // COMPARE. Row counts moving is expected — L0 grows. The digest moving
        // for a range whose L0 objects have not changed is the finding.
        $rejected = $run->l0_rejected > 0
            ? '  ⚠️ '.$run->l0_rejected.' rejected'
            : '';

        // ⚠️ **PRINTED ONLY WHEN IT IS NON-ZERO, AND IT IS NOT A FAULT** (7929).
        // These are events this range derived and did not write because the same
        // `event_id` already lives **outside** it — a beacon retried across UTC
        // midnight, which is ordinary. It is printed because without it the line
        // reads *"1 lines → 0 L1"* with nothing rejected, which is the triple
        // `etl_runs.l1_suppressed` exists to explain. **The sentence names the
        // remedy**, because an operator who reads this and does nothing has an
        // event on whichever of the two days was replayed first.
        $suppressed = $run->l1_suppressed > 0
            ? '  ⚠️ '.$run->l1_suppressed.' already stored outside this range (replay both days together to settle which day owns them)'
            : '';

        return sprintf(
            'business %d  %s..%s  %d objects, %d lines → %d L1, %d L2  sha256:%s%s%s',
            $business->getKey(),
            $run->from_day->toDateString(),
            $run->to_day->toDateString(),
            $run->l0_objects,
            $run->l0_lines,
            $run->l1_rows,
            $run->l2_rows,
            substr($run->snapshot_digest, 0, 16),
            $rejected,
            $suppressed,
        );
    }
}

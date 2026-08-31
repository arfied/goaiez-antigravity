<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\AlertOrigin;
use App\Enums\OperatorAlertKind;
use App\Enums\OutreachChannel;
use App\Livewire\Admin\OperatorAlertBoard;
use App\Models\Business;
use App\Models\PlatformHaltIncident;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\PlatformComplaintRate;
use App\Services\Messaging\PlatformRateSample;
use App\Services\Messaging\PlatformSweep;
use App\Services\Messaging\SendingGuard;
use App\Services\Ops\OperatorAlerts;
use App\Support\Tenancy;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Halt all sending when the platform's own complaint rate crosses the line —
 * the automatic half of decision 2102, and 2119's brief.
 *
 * ## Why this is a scheduled command when the per-tenant trip is on the hot path
 *
 * They are different questions and only one of them can be asked per message.
 * `SendingGuard` asks *"may THIS tenant send"*, reads one tenant's 24 rows, and
 * is consulted before every message — which is what lets a campaign trip itself
 * partway through (2182). The platform question is *"what is every tenant's
 * traffic adding up to"*, and answering it means walking every tenant in turn;
 * doing that per message would put an O(tenants) sweep in front of each send.
 *
 * ⚠️ **THE COST OF THE SCHEDULE IS REAL AND IS STATED RATHER THAN GLOSSED.**
 * Everything sent between two runs of this command goes out against a reading
 * taken before it. That is precisely the objection 2117 raised against building
 * the *per-tenant* trip this way, and it is answered here rather than repeated:
 * the per-tenant trip is the one with the tight loop, it already contains the
 * tenant generating the complaints, and this is the wider net that catches the
 * case a single tenant's rate never shows — a hundred accounts each slightly
 * over (2101's cumulative damage).
 *
 * ## It throws its own switch, not the operator's
 *
 * ⛔ **`messaging.automatic_halt`, AND THE SPLIT IS A COMPLIANCE FIX RATHER
 * THAN TIDINESS** (3980–3983). See {@see self::HALT_KEY}: writing the
 * operator's key silenced every carrier-mandated STOP and HELP reply on the
 * platform, automatically, at the moment a carrier was already looking.
 * `SendingGuard` reads both keys and refuses on either, so nothing about the
 * containment is softened; `SendingControls` shows "stopped" when either is on
 * and clears both, so no halt this command throws is unreleasable.
 *
 * ## It never releases
 *
 * Tripping is automatic; releasing is a person's act, on the settings screen,
 * with an actor. A rate that has fallen back under the threshold is not evidence
 * that whatever generated it was dealt with — the window rolls forward on its
 * own — and an auto-release would flap: halting overnight, clearing by morning,
 * and leaving nothing for anybody to have noticed. **Nothing in this command
 * writes `false`.**
 *
 * ## Switchable off, and loudly
 *
 * ⛔ **THIS SECTION READ "OFF BY DEFAULT" AND SAID *"BOTH REGISTRY KEYS SEED TO
 * ZERO AND 2119 FORBIDS INVENTING THEM, SO OUT OF THE BOX THIS COMMAND MEASURES,
 * REPORTS, AND TRIPS NOTHING"* — TRUE WHEN WRITTEN, FALSE SINCE 2684, CORRECTED
 * 2026-08-22 (7601).** The owner chose the figures: `DefaultsManifest` seeds
 * {@see self::TRIP_KEY} at **100** basis points over a {@see self::FLOOR_KEY} of
 * **50** delivered, and that manifest entry has said so for months while this
 * paragraph went on describing an unarmed platform — in the one docblock
 * somebody reads before deciding whether the containment is live.
 *
 * ⚠️ **AND THE CORRECTION MAY NOT GO THE OTHER WAY EITHER**, on `CLAUDE.md`'s
 * *a seed is not a deployment*: a seed is what an **unset** row answers, an
 * operator's edit through `Admin\PlatformSettings` leaves it untouched, and no
 * document in this repository may state what a running install has on. **What is
 * true is that either key at zero disables everything below**, and the command
 * says so on every run rather than exiting silently, because a scheduled task
 * that prints "nothing to do" is how a switched-off safety control stays
 * switched off for a year.
 *
 * ⛔ **AND SINCE 7600 THAT REFUSAL TAKES THE BLIND-SPOT BELL WITH IT.**
 * {@see self::ring()} sits below the same branch, so zeroing either key stops
 * the platform halting itself **and** stops anybody being told the halt has gone
 * blind. That is the honest coupling rather than an oversight — with no floor
 * configured there is no trip to be blind about — and it is rendered on
 * `Admin\OperatorAlertBoard`, which is the screen built to answer *"would this
 * have rung"*.
 *
 * ⚠️ **AND IT NO LONGER LEAVES NOBODY LISTENING, WHICH IS A CLOSURE RATHER
 * THAN A CHANGE OF MIND** (7723). The paragraph above is exactly true of
 * {@see self::ring()} and was true of the whole failure while that was the only
 * bell: two platform figures an operator could blank switched off every warning
 * this application had about a containment reading a rate measured over nothing.
 * {@see self::ringForBlindTenants()} sits **above** that branch, armed by the
 * per-tenant keys instead, so a blanked platform figure now silences the
 * platform bell and leaves the per-tenant one ringing for every blind account.
 *
 * ## It rings, and it still does not act
 *
 * ⛔ **THIS SECTION OPENED *"THE BELLS THIS COMMAND OWNS ARE ABOUT ITS OWN
 * BLINDNESS, NOT ABOUT THE RATE"* AND THAT WAS TRUE UNTIL 2026-08-22 — BOTH
 * READINGS KEPT AND DATED** (7860, 4368's rule). It is now false in the one
 * direction that matters: {@see OperatorAlertKind::PlatformSendingHalted} rings
 * about **the rate**, from {@see self::halt()}, on the sweep that throws the
 * switch. ⚠️ **What survives of the old sentence is its second half and it
 * survives unchanged** — the bells change nothing this command *does*. The halt
 * still fires only on the rate, the blind-spot branches still fall through to
 * `trips()`, the new bell is the **last** thing `halt()` does, and R25 holds
 * across all three: an alert is a bell and never a brake.
 *
 * ⚠️ **AND THE THIRD BELL IS DISCRETE WHERE THE OTHER TWO ARE CONTINUOUS**,
 * which is the property to hold on to when reading them side by side. The two
 * blind-spot bells re-raise on every sweep for as long as the condition lasts
 * and are regulated only by `OperatorAlerts`' quiet window (7605), so every
 * figure they carry is a **rolling** one. The halt bell can fire only on the
 * sweep that writes the switch, because every later sweep returns at *"already
 * halted"* — so its figures are a **snapshot**, deliberately, and freezing them
 * is what makes the alert row answer *"what did the machine act on"* rather
 * than *"what was true an hour later"*.
 *
 * ⛔ **"THE ONE BELL" WAS TRUE UNTIL 2026-08-22 AND THERE ARE TWO** (7720–7739).
 * {@see OperatorAlertKind::TenantDeliveryReceiptsSilent} reports the half the
 * aggregate destroys: `reportingHasGoneSilent()` sums `delivered` and `failed`
 * across every tenant, so **one healthy tenant makes it false for everybody**,
 * and an account with four thousand blind sends was invisible the moment
 * anybody else's receipts were working. Every tenant's `SendingRates` was
 * already passing through {@see PlatformComplaintRate::measure()} and five
 * integers survived the loop; the per-tenant answer was measured on every sweep
 * and thrown away.
 *
 * ⚠️ **THE TWO CAN NEVER BOTH RING** — see {@see self::ringForBlindTenants()} —
 * and they are armed by **different pairs of registry keys**, because they
 * report two different containments going blind. {@see self::TRIP_KEY} and
 * {@see self::FLOOR_KEY} arm 2102's platform halt; {@see self::TENANT_TRIP_KEY}
 * and {@see self::TENANT_FLOOR_KEY} arm the per-tenant trip
 * `SendingGuard::shouldTrip()` throws. ⛔ **So the section above is narrower than
 * it reads**: zeroing a platform key still takes the platform bell with it, and
 * no longer takes the only bell on this failure with it.
 */
#[AsCommand(name: 'messaging:watch-platform-complaint-rate')]
final class WatchPlatformComplaintRate extends Command
{
    /**
     * The actor recorded on a halt nobody threw.
     *
     * The registry's change history stamps an actor on every write, and this is
     * the vocabulary `SendingGuard::SYSTEM_ACTOR` already established: an
     * operator reading the history needs to tell a person from the platform at a
     * glance, and a free-text string typed at the call site drifts the moment
     * there are two.
     */
    public const string ACTOR = 'system:platform-complaint-rate';

    /**
     * The switch this command throws — **not** `messaging.global_halt`.
     *
     * ⛔ **IT WROTE THE OPERATOR'S KEY UNTIL 3980, AND THAT SILENCED STOP AND
     * HELP.** `ComplianceReplies` defers to `sms.enabled` and
     * `messaging.global_halt`, arguing each is *"an operator's decision to make
     * knowingly"*. **An automatic quarantine is neither, and neither is this** —
     * this command runs every fifteen minutes with nobody awake, so the first
     * automatic trip stopped every carrier-mandated reply on the platform while
     * a carrier was already looking at us because of the complaint rate. 2099
     * makes STOP and HELP unconditional.
     *
     * ⚠️ **IT STOPS SENDING JUST AS HARD.** `SendingGuard` reads both keys and
     * refuses on either, so 2102's containment is unchanged; the split reaches
     * exactly one class, which is the class that must never be stopped by a
     * machine.
     *
     * ⚠️ **THE KEY IS {@see SendingGuard}'S TO NAME AND THIS IS AN ALIAS**, so
     * the writer and the reader cannot come to disagree about the string — the
     * shape 2402 calls a second source of truth.
     */
    public const string HALT_KEY = SendingGuard::AUTOMATIC_HALT_KEY;

    /**
     * The rate at which the platform halts itself, in basis points.
     *
     * ⚠️ **A CONSTANT BECAUSE A SECOND READER ARRIVED** (7607). This was a
     * string literal here while `Admin\SendingControls` held two more, which was
     * survivable for as long as every reader was a screen rendering the same
     * sweep's figures. {@see OperatorAlertBoard::bells()}
     * now reads both keys to answer *"can this bell ring at all"*, and that is
     * the reader a renamed key must break rather than leave quietly printing
     * about a key nobody sets — `PlatformHealthChecks`' four constants exist for
     * exactly that reason and the board's own docblock says so.
     *
     * ⚠️ **`SendingControls` still holds two literals of each and this lane did
     * not own that file**, so the migration is deliberately partial and recorded
     * rather than half-hidden. Nothing breaks while they agree; what is owed is
     * pointing them here.
     */
    public const string TRIP_KEY = 'messaging.platform_complaint_trip_bp';

    /**
     * How many messages must have been **delivered** before the rate above is
     * allowed to mean anything.
     *
     * ⛔ **AND IT IS THE FLOOR THE BLIND-SPOT BELL BORROWS** (2409, 7484). The
     * claim {@see OperatorAlertKind::DeliveryReceiptsSilent} makes is exact
     * rather than approximate — *as many messages have been handed to a carrier
     * as the trip needs to have been delivered, and not one of them has been
     * reported on* — which is only true while both readers use one number.
     * ⚠️ **So zeroing this switches off the detector as well as the trip**, and
     * that is the honest coupling rather than an oversight: with no floor
     * configured there is no trip to be blind about.
     *
     * ⚠️ **"THE DETECTOR" IS NOW ONE OF TWO AND THIS KEY REACHES ONLY ITS OWN**
     * (7723). {@see OperatorAlertKind::TenantDeliveryReceiptsSilent} borrows
     * {@see self::TENANT_FLOOR_KEY} instead, because the containment it reports
     * as blind is the **per-tenant** trip; zeroing this figure no longer
     * silences every warning on this failure.
     */
    public const string FLOOR_KEY = 'messaging.platform_complaint_min_delivered';

    /**
     * The **per-tenant** trip's rate — `SendingGuard::shouldTrip()`'s first read.
     *
     * ⛔ **THIS COMMAND HAS NO BUSINESS HALTING ON IT AND DOES NOT** (7721). It
     * is read for exactly one thing: whether there is a per-tenant containment
     * for {@see OperatorAlertKind::TenantDeliveryReceiptsSilent} to report as
     * blind. With this at zero no tenant's sending pauses automatically, so no
     * tenant's trip can be reading a rate measured over nothing — the same
     * coupling {@see self::FLOOR_KEY} has with its own bell, one layer down.
     *
     * ⚠️ **A FIFTH SPELLING OF A KEY WHOSE OWNER EXPOSES NO CONSTANT, AND THAT
     * IS RECORDED RATHER THAN HIDDEN** (7726). `SendingGuard` reads it as a
     * literal and so does `Admin\SendingControls`, three times each; **both files
     * were read-only to this lane**, so promoting it to `SendingGuard::TRIP_KEY`
     * and pointing everything here is owed to whoever next owns that file. A
     * literal here would have been the sixth, and {@see self::TRIP_KEY}'s own
     * docblock is the precedent: a renamed key must break a reader loudly rather
     * than leave it quietly printing about a key nobody sets.
     */
    public const string TENANT_TRIP_KEY = 'messaging.complaint_trip_bp';

    /**
     * The **per-tenant** trip's delivery floor, and therefore the per-tenant
     * blind-spot bell's floor.
     *
     * ⛔ **IT IS THE TRIP'S OWN FIGURE AND NOT THE PLATFORM'S, WHICH IS THE
     * WHOLE OF 2409 APPLIED ONE LAYER DOWN** (7722). The claim
     * {@see OperatorAlertKind::TenantDeliveryReceiptsSilent} makes is about one
     * account's containment — *as many messages have gone to a carrier for this
     * tenant as its own trip needs to have been delivered, and not one has been
     * reported on* — and `SendingRates::trafficWithoutOutcomes()` is handed this
     * number for exactly that reason. Borrowing {@see self::FLOOR_KEY} instead
     * would compare one tenant's traffic against a platform-wide significance
     * floor and make the sentence false.
     *
     * ⚠️ **SO ZEROING THIS SWITCHES OFF THE PER-TENANT DETECTOR**, on the
     * identical argument: with no floor configured `SendingGuard::shouldTrip()`
     * returns before it acts, and there is no trip to be blind about.
     */
    public const string TENANT_FLOOR_KEY = 'messaging.complaint_trip_min_delivered';

    /**
     * How many blind tenants one bell names.
     *
     * ⛔ **A RENDERING BOUND, NEVER A THRESHOLD** (7724). Nothing is decided by
     * it: the bell rings on the first blind tenant and rings once however many
     * there are, and `blind_tenants` and `blind_sent` are always whole. This
     * governs only how much evidence fits in a `jsonb` column and a
     * `Log::critical` line an operator has to read — `OperatorAlerts::clamp()`'s
     * own 300-character bound, pointed at evidence instead of prose.
     * **A policy figure would be the owner's** (2409); this is not one.
     *
     * ⚠️ **TWENTY BECAUSE IT IS THE POINT AT WHICH A LIST STOPS BEING READ**,
     * and because past it the useful figure is the count. They are ordered by
     * traffic, so the twenty named are the twenty with most already on the wire.
     */
    public const int NAMED_BLIND_TENANTS = 20;

    protected $description = 'Halt all platform sending if the aggregate complaint rate crosses its threshold';

    public function handle(DefaultsRegistry $registry, PlatformComplaintRate $rates, OperatorAlerts $alerts): int
    {
        $threshold = $registry->int(self::TRIP_KEY);
        $floor = $registry->int(self::FLOOR_KEY);

        // ⚠️ Measured before the "is it configured" branch on purpose. An
        // operator deciding what the threshold should be needs to see what the
        // rate actually is, and a command that refuses to look until somebody
        // has already guessed the number is a command that guarantees the guess.
        //
        // ⛔ **AND THE FLOOR HANDED DOWN IS THE PER-TENANT TRIP'S, NOT THIS
        // COMMAND'S** (7722). The two halves of this sweep are armed by two
        // different pairs of registry keys because they report two different
        // containments going blind, and conflating them is how zeroing one
        // platform key came to switch off the only bell on this whole failure.
        $tenantFloor = $this->tenantBlindSpotFloor($registry);

        $sweep = $rates->measure(OutreachChannel::Sms, $this->everyTenant(), $tenantFloor);

        $sample = $sweep->sample;

        // ⚠️ **LEFT WHERE A SCREEN CAN FIND IT, BEFORE ANY BRANCH** (4374–4376).
        // T176 P9 asks for the platform's current standing on the admin screen,
        // and this is the only place in the application that can compute one:
        // `sending_health_windows` is FORCE ROW LEVEL SECURITY, so a request
        // path summing it across tenants reads **zero** rather than failing, and
        // `PlatformComplaintRate` refuses the owner walk outside a console
        // command. Recorded on every run, including the runs that then halt and
        // the runs where nothing is configured — a reading an operator can only
        // see once something has gone wrong is not a standing.
        //
        // ⚠️ It cannot throw. See `report()`: a display record must never be
        // able to stop a containment.
        $rates->report($sample);

        $this->line(sprintf(
            'Platform complaint rate: %d bp over %d delivered across %d tenant(s) in %dh.',
            $sample->basisPoints(),
            $sample->delivered,
            $sample->tenantCount,
            $sample->windowHours,
        ));

        // ⚠️ **READ ONCE AND ASKED TWICE, BECAUSE TWO BRANCHES NEED IT AND THEY
        // SIT ON OPPOSITE SIDES OF THE PLATFORM KEYS** (7723). The early return
        // below is unchanged and so is its ordering; what could not stay inline
        // is the answer, because the per-tenant bell has to be asked *above* the
        // platform-key gate and *below* this one.
        $halted = $registry->value(SendingGuard::OPERATOR_HALT_KEY) === true
            || $registry->value(self::HALT_KEY) === true;

        // ⛔ **ABOVE THE PLATFORM-KEY GATE, DELIBERATELY, AND THIS IS THE ONE
        // ORDERING DECISION IN THE SLICE** (7723). Everything below this line is
        // armed by `TRIP_KEY` and `FLOOR_KEY`, which arm **2102's platform
        // halt**. The per-tenant trip is a different containment with its own two
        // keys, and an operator who zeroes a platform figure has not switched it
        // off — so putting this below that gate would have reproduced, in the fix,
        // the exact hole it exists to close.
        //
        // ⚠️ **BELOW THE HALT CHECK, THOUGH, AND THAT IS RE-DERIVED RATHER THAN
        // COPIED.** `SendingGuard::refusalFor()` reads both halt keys and refuses
        // every send on either, so on a halted platform no tenant's trip has
        // anything left to contain and a bell about it wakes somebody for
        // nothing. Same conclusion as the platform bell's, reached from the
        // per-tenant containment rather than inherited from it.
        if (! $halted) {
            $this->ringForBlindTenants($alerts, $sweep, $threshold, $floor, $tenantFloor);
        }

        if ($threshold <= 0 || $floor <= 0) {
            $this->warn(
                'The automatic platform halt is SWITCHED OFF: '.self::TRIP_KEY
                .' and '.self::FLOOR_KEY.' are both required and one is unset. '
                .'Decision 2119 leaves those figures to the owner; until they are set, only a person '
                .'can halt the platform.'
            );

            return self::SUCCESS;
        }

        // ⚠️ **BOTH KEYS, AND THE OPERATOR'S ONE IS NOT MERELY COURTESY.**
        // Idempotent, and quietly. This runs on a schedule, so a rate that
        // stays over the threshold would otherwise open a fresh incident every
        // cycle — and the incident list is what somebody reads to find out how
        // many times this has actually happened. A platform an operator has
        // already halted is halted; adding a second switch on top of it records
        // an incident for a stop that changed nothing, and leaves an automatic
        // halt standing behind the release of the manual one.
        if ($halted) {
            $this->info('Sending is already halted platform-wide; nothing to do.');

            return self::SUCCESS;
        }

        if ($this->platformHasGoneSilent($sample, $threshold, $floor)) {
            // ⛔ **THE HALT IS OFF AND THIS SWEEP CANNOT SEE IT ANY OTHER WAY**
            // (7480, 7487). `trips()` refuses whenever `delivered` is under the
            // floor, and an empty `delivered` reads identically whether nothing
            // was sent or nothing was reported back. Here the platform handed
            // out at least `$floor` messages and not one outcome came home, so
            // 2102's automatic halt is switched off for every tenant on the
            // shared GOAIEZ registration — and the standing printed two lines
            // above says `0 bp over 0 delivered`, which reads as a spotless
            // platform.
            //
            // ⛔ **SAID, NOT DONE (R25).** This does not halt and must not:
            // stopping every tenant because our own reporting is broken would
            // silence the carrier-mandated STOP confirmation and HELP answer
            // 2099 makes unconditional — 3980–3983's collision through a
            // different door. The action is an operator's, and it is to check
            // the Infobip delivery notification profile and whether
            // `destinations[].messageId` is still being supplied.
            $this->warn(sprintf(
                'The automatic platform halt CANNOT FIRE: %d message(s) went out in %dh and not one '
                .'delivery outcome was reported back, so the rate it reads is measured over nothing. '
                .'Check the Infobip delivery webhook and whether the carrier is reporting under the '
                .'message id this application supplied.',
                $sample->sent,
                $sample->windowHours,
            ));

            // ⛔ **AND THE LINE ABOVE REACHES NOBODY, WHICH IS THE WHOLE OF
            // 7493(a)** (7600). It is `stdout` from a scheduled command, and the
            // documented cron entry is `schedule:run >> /dev/null 2>&1` — so a
            // warning this command shouts every fifteen minutes is audible only
            // to somebody who ran it by hand, which is the one case in which they
            // already knew. ⚠️ **The reason is the cron redirect and NOT
            // `runInBackground()`**: this entry explicitly refuses that, and
            // `routes/console.php` carries the argument. The conclusion is the
            // same and the mechanism is not, which matters to whoever next tries
            // to fix it by detaching or attaching a schedule entry.
            $this->ring($alerts, $sample, $floor);
        }

        if (! $sample->trips($threshold, $floor)) {
            return self::SUCCESS;
        }

        $this->halt($registry, $alerts, $sample, $threshold);

        return self::SUCCESS;
    }

    /**
     * Wake somebody: this containment is switched off and traffic is on the
     * wire — 7493(a), closed at 7600–7619.
     *
     * ## Why the bell is HERE and could not be where the condition is felt
     *
     * ⛔ **THE PER-TENANT DETECTOR HAS NOWHERE LEGAL TO RING FROM** (7485,
     * 7605). `SendingGuard::reportBlindSpot()` sees the same failure one tenant
     * at a time, but it runs inside `refusalFor()`, which is asked **before
     * every single send** — and 7485 already refused a *cache* round trip on
     * that path, because a cache outage inside it is a brake on all outbound
     * messaging arriving from the one method whose contract is that it returns a
     * reason instead of throwing. {@see OperatorAlerts::raise()} is a database
     * read, an insert, a `Log::critical`, a queued mail dispatch and an
     * **inline** text: strictly worse, same path, same argument.
     *
     * ⛔ **THIS SAID "A SYNCHRONOUS MAIL AND A TEXT" UNTIL 2026-08-22 AND THE
     * MAIL HALF WAS NEVER TRUE — CORRECTED IN PLACE** (7873a). `PlatformMailer::send()`
     * is `DeliverPlatformMail::dispatch()` and nothing else, so the request pays
     * a queue write rather than an SMTP round trip. ⚠️ **The conclusion is
     * untouched and the wrong word made it sound stronger than it is** — an
     * insert, a `critical` log, a queue dispatch and a **synchronous carrier
     * call** is still strictly worse than the cache round trip 7485 refused, and
     * the text is the part that was always inline. **So the scheduled sweep is the only viable raiser**, and
     * it already asks the question two lines above.
     *
     * ⚠️ **WHICH COSTS UP TO ONE SWEEP INTERVAL OF LATENCY AND THAT IS STATED
     * RATHER THAN GLOSSED**, on this class's own rule about the schedule: the
     * first unplaceable receipt is noticed at the next quarter-hour boundary,
     * not at the send. Fifteen minutes against a failure that has already been
     * running for as long as it took to hand `$floor` messages to a carrier is
     * not the expensive part of this.
     *
     * ## Every figure it carries is a rolling one
     *
     * ⛔ **BECAUSE ONLY THE FIRST BELL IN A QUIET WINDOW LANDS.** The condition
     * is continuous and nothing ever clears an alert — there is no acknowledge,
     * no resolve, no `cleared_at` — so this re-raises on every sweep and
     * `OperatorAlerts::alreadyRang()` suppresses all but the first in each
     * window, which is the existing convention for a continuous kind. **Any
     * figure that reset per bucket would therefore be pinned at its first-bell
     * value for ever.** `sent`, `tenantCount` and `complaints` are all read off
     * the rolling 24-hour window, so a bell rung six hours into an incident
     * carries a bigger number than the one rung at the start, and *"how much
     * traffic went out blind"* is answerable from the alert row alone.
     *
     * ⚠️ **`complaints` IS THE SHARPEST FIGURE HERE AND IT IS NOT ALWAYS
     * ZERO.** Complaints reach `sending_health_windows` from the STOP handler
     * (`Sms/InboundMessages`) and deliveries from the receipt webhook
     * (`Sms/DeliveryReceipts`) — two independent writers — so this state can
     * hold hundreds of STOPs beside zero deliveries, and the rate still reads
     * `0 bp` because it divides by `delivered`. A non-zero count in that
     * context is the difference between *"we cannot see"* and *"we cannot see
     * and it is already bad"*.
     *
     * ⚠️ **NO PERSONAL DATA AND NO TENANT NAMED**, which is not a courtesy here
     * — the summary is delivered by SMS, and `PlatformComplaintRate::report()`
     * and `platform_halt_incidents` both refuse to name a tenant for the same
     * reason: the worst-performing account's standing must not leak into a
     * record support reads routinely. The sample carries no tenant identity to
     * leak in any case, which is why the subject is the channel — see
     * {@see OperatorAlertKind::DeliveryReceiptsSilent}.
     *
     * ⛔ **R25: A BELL, NEVER A BRAKE.** `raise()` returns rather than throws and
     * contains its own failures, so a broken alert path cannot stop this sweep
     * measuring, reporting or halting; the command exits `SUCCESS` either way,
     * and nothing about this branch refuses a send.
     */
    private function ring(OperatorAlerts $alerts, PlatformRateSample $sample, int $floor): void
    {
        $alerts->raise(
            OperatorAlertKind::DeliveryReceiptsSilent,
            // The channel, not a tenant. One vendor misconfiguration causes
            // this for everybody at once; the enum case carries the fork.
            OutreachChannel::Sms->value,
            sprintf(
                '%d message(s) went out in %dh across %d tenant(s) and not one delivery outcome came '
                .'back, so the automatic halt cannot fire. Check the Infobip delivery notification '
                .'profile and whether receipts carry the message id we supply.',
                $sample->sent,
                $sample->windowHours,
                $sample->tenantCount,
            ),
            [
                'channel' => OutreachChannel::Sms->value,
                'sent' => $sample->sent,
                // Zero by construction of the predicate, and carried anyway:
                // they are the evidence for "not one outcome", and an incident
                // review reading this row after the window rolled cannot
                // re-derive them.
                'delivered' => $sample->delivered,
                'failed' => $sample->failed,
                'complaints' => $sample->complaints,
                'tenant_count' => $sample->tenantCount,
                'window_hours' => $sample->windowHours,
                'floor' => $floor,
            ],
        );
    }

    /**
     * Whether the **platform's own** bell is the one this run should ring.
     *
     * ⛔ **ONE SPELLING FOR TWO READERS, AND THE SECOND READER IS WHAT MADE IT A
     * METHOD** (7723). {@see self::handle()} asks it to decide whether to warn
     * and ring; {@see self::ringForBlindTenants()} asks it to decide whether to
     * **stay quiet**, because the two bells are mutually exclusive by
     * construction. Two copies of this condition drifting apart would produce
     * either two bells for one incident or none for it, and neither is
     * recoverable from the alert rows afterwards.
     *
     * ⚠️ **`$threshold > 0` IS PART OF THE PREDICATE AND NOT A REDUNDANCY.**
     * {@see PlatformRateSample::reportingHasGoneSilent()} already refuses a
     * non-positive floor, so the floor half is covered — but a platform trip with
     * a live floor and a zeroed rate returns from `handle()` before it can ring,
     * so *"the predicate is true"* and *"the platform bell rang"* are different
     * statements. It is the second that has to be answered here.
     */
    private function platformHasGoneSilent(PlatformRateSample $sample, int $threshold, int $floor): bool
    {
        return $threshold > 0 && $sample->reportingHasGoneSilent($floor);
    }

    /**
     * The floor a single tenant's silence is judged against, or `0` when there is
     * no per-tenant trip for it to be blind about.
     *
     * ⛔ **BOTH PER-TENANT KEYS, BECAUSE THEY ARE ONE SETTING IN TWO BOXES**
     * (4492–4495, restated at {@see PlatformRateSample::trips()}). A floor with a
     * zeroed rate is a trip `SendingGuard::shouldTrip()` returns out of before it
     * acts, so a bell announcing that trip has gone blind would be announcing
     * that a switched-off containment is switched off. Answering `0` makes
     * `SendingRates::trafficWithoutOutcomes()` false for every tenant, which is
     * the same closed door reached one layer earlier — and it means the
     * measurement is never taken rather than taken and discarded.
     */
    private function tenantBlindSpotFloor(DefaultsRegistry $registry): int
    {
        if ($registry->int(self::TENANT_TRIP_KEY) <= 0) {
            return 0;
        }

        return $registry->int(self::TENANT_FLOOR_KEY);
    }

    /**
     * Wake somebody: **some** tenants' receipts have stopped while the rest of
     * the platform is being reported on normally — 7615(a), closed at 7720–7739.
     *
     * ## Why this is a second bell and not the one above with a different subject
     *
     * ⛔ **THE HEADLINE IS THE PRODUCT HERE, AND THE TWO HEADLINES SEND SOMEBODY
     * TO TWO DIFFERENT PLACES.** *"Delivery reports have stopped"* is the wrong
     * sentence at 3am when most of them have not — and the first move it implies,
     * the vendor's delivery notification profile, is demonstrably **working**,
     * because other tenants' receipts are arriving through it. What differs about
     * these accounts is their route: the number they send on, the campaign they
     * are attached to, or a send that never reached the carrier at all.
     * {@see OperatorAlertKind::TenantDeliveryReceiptsSilent} carries the full
     * argument, including which of its sibling's three refusals survive.
     *
     * ## They can never both ring, and that is by construction
     *
     * ⛔ **{@see self::platformHasGoneSilent()} IS ASKED HERE FOR THAT REASON.**
     * When *nothing at all* came back, every tenant is blind and this would ring
     * about all of them for one vendor cause — which is 511's *"the first outage
     * sends two hundred texts"* with the platform bell already ringing beside it.
     * When something came back for somebody, the platform predicate is false by
     * arithmetic and only this bell can see the accounts it is false *for*.
     * **The two partition the space; neither is a special case of the other.**
     *
     * ⚠️ **AND THE PARTITION IS WHY ZEROING A PLATFORM KEY NO LONGER BLINDS
     * ANYBODY.** With `TRIP_KEY` at zero the platform bell cannot ring at all
     * (7601's honest coupling), so `platformHasGoneSilent()` is false and this
     * bell answers for every blind tenant instead — including, then, all of them.
     * That is a closure rather than an accident of ordering.
     *
     * ## One bell however many accounts, and the count is the figure
     *
     * ⛔ **A BELL PER TENANT WAS REFUSED ON ITS SIBLING'S GROUND (a)**, which
     * survives here unchanged: `OperatorAlerts::raise()` is a read, an insert, a
     * `Log::critical`, a queued mail dispatch **and** an inline text, and a
     * carrier-side partial outage across forty accounts would be forty of each.
     * ⚠️ **"A synchronous mail" was the wording here until 2026-08-22 and it was
     * wrong about which half is inline** (7873a): the mail is queued, the text is
     * not, and forty inline carrier calls is the half that bites. That is
     * {@see OperatorAlertKind::GbpGrantOutstanding}'s own argument for collapsing
     * to one subject and carrying a count. ⚠️ **The cost is stated rather than
     * glossed**: a second account going blind inside the first's quiet window is
     * suppressed with it, and is named by the next bell after the window rolls —
     * the same trade 7605 already accepted for a continuous kind, and the reason
     * every figure below is a **rolling** one.
     *
     * ⚠️ **THE ACCOUNTS ARE EVIDENCE AND RIDE IN `context`, NEVER IN THE
     * SUMMARY** — the summary is delivered by SMS. ⛔ **Naming them at all
     * overturns half of `DeliveryReceiptsSilent`'s ground (b), deliberately**:
     * what that refusal protects is `PlatformComplaintRate::report()`'s cache
     * entry and `platform_halt_incidents`, which are about a tenant's
     * **standing**, and neither is touched here. A blind tenant's complaint rate
     * is *unknown*; saying which accounts a carrier is not reporting on is a fact
     * about our own integration. Without the ids this bell would tell an operator
     * that something is wrong somewhere and give them no way to find it.
     *
     * ⛔ **R25: A BELL, NEVER A BRAKE.** Nothing here pauses a tenant, halts the
     * platform or files an incident, and `raise()` contains its own failures — so
     * the sweep measures, reports and halts identically whether this rings or
     * throws. The temptation is sharper than for the platform bell because a
     * named account is something a machine could pause, and pausing it punishes a
     * tenant for a carrier's reporting while still not stopping whoever is
     * generating the complaints.
     */
    private function ringForBlindTenants(
        OperatorAlerts $alerts,
        PlatformSweep $sweep,
        int $threshold,
        int $floor,
        int $tenantFloor,
    ): void {
        if (! $sweep->hasBlindTenants()) {
            return;
        }

        if ($this->platformHasGoneSilent($sweep->sample, $threshold, $floor)) {
            return;
        }

        // ⛔ **SAID ON `stdout` AS WELL, AND THAT IS NOT THE DELIVERY** (7611).
        // The documented cron line is `schedule:run >> /dev/null 2>&1`, so this
        // reaches somebody who ran the command by hand and nobody else. It is
        // here because that person is usually the one already investigating.
        $this->warn(sprintf(
            'A per-tenant complaint trip CANNOT FIRE for %d of %d tenant(s): %d message(s) went out '
            .'for them in %dh with no delivery outcome reported back, while the rest of the platform '
            .'is being reported on normally. Check the numbers and campaigns those accounts send on.',
            $sweep->blindTenantCount(),
            $sweep->sample->tenantCount,
            $sweep->blindSent(),
            $sweep->sample->windowHours,
        ));

        $named = $sweep->worstBlindTenants(self::NAMED_BLIND_TENANTS);

        $alerts->raise(
            OperatorAlertKind::TenantDeliveryReceiptsSilent,
            // ⚠️ The channel, on its sibling's ground (a). A per-business subject
            // would ring once per affected account for what is usually one cause;
            // the accounts are carried as evidence instead.
            OutreachChannel::Sms->value,
            sprintf(
                '%d of %d account(s) handed %d message(s) to a carrier in %dh and got no delivery '
                .'outcome back, while the rest of the platform is reported on normally, so their '
                .'automatic complaint trip cannot fire. Check the numbers those accounts send on.',
                $sweep->blindTenantCount(),
                $sweep->sample->tenantCount,
                $sweep->blindSent(),
                $sweep->sample->windowHours,
            ),
            [
                'channel' => OutreachChannel::Sms->value,
                // Rolling, on 7605's rule: only the first bell in a quiet window
                // lands, so a figure counted per sweep would be pinned at its
                // first-bell value for the length of the incident.
                'blind_tenants' => $sweep->blindTenantCount(),
                'blind_sent' => $sweep->blindSent(),
                'tenant_count' => $sweep->sample->tenantCount,
                // ⚠️ The aggregate beside it, because the whole claim of this
                // bell is that the platform looked fine. An incident review
                // reading this row after the window rolled cannot re-derive it.
                'platform_delivered' => $sweep->sample->delivered,
                'platform_failed' => $sweep->sample->failed,
                'window_hours' => $sweep->sample->windowHours,
                // ⛔ The PER-TENANT trip's floor, never `FLOOR_KEY`. Putting the
                // platform's significance floor on a per-tenant claim is how the
                // sentence this bell makes stops being exact (2409).
                'tenant_floor' => $tenantFloor,
                // ⚠️ **A STRING RATHER THAN A LIST**, because `raise()`'s context
                // is `array<string, scalar|null>` and it is spread into a log
                // line. Capped and ordered by traffic — the count above is always
                // whole, so the cap can never hide the size of the incident.
                'business_ids' => implode(',', $named),
                'business_ids_named' => count($named),
            ],
        );
    }

    /**
     * Every tenant on the platform, reached through its owner.
     *
     * ⚠️ **THE TENTH COPY OF `RefreshOauthTokens`' OWNER WALK, AND IT IS HERE
     * RATHER THAN IN THE SERVICE FOR A REASON THE LINT MADE VISIBLE.** All nine
     * `withoutGlobalScopes()` sites `TenancyTest` permits are console commands,
     * behind shell access. `PlatformComplaintRate` began with this loop inside
     * it and the lint refused it — correctly: a *service* that can enumerate
     * every business is reachable from a web request, while this is reachable
     * from a shell.
     *
     * `businesses` is `FORCE ROW LEVEL SECURITY` with a policy keyed on the
     * session tenant, so `Business::all()` returns nothing and dropping the
     * scope alone does not help — the policy is in the database. The scope is
     * dropped only because it calls `Tenancy::idOrFail()` and no tenant is
     * established yet (the circularity `ResolveTenant` documents); the read is
     * still restricted by `owner_lookup` to the businesses owned by the user
     * just set, so this gains no privilege a logged-in owner does not have.
     *
     * @return iterable<int>
     */
    private function everyTenant(): iterable
    {
        $ids = [];

        User::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function (Collection $users) use (&$ids): void {
                foreach ($users as $user) {
                    Tenancy::setUser((int) $user->getKey());

                    foreach (
                        Business::withoutGlobalScopes()
                            ->where('owner_user_id', $user->getKey())
                            ->pluck('id') as $businessId
                    ) {
                        $ids[] = (int) $businessId;
                    }
                }
            });

        // Collected rather than yielded. A generator would interleave the owner
        // walk with the per-tenant reads, so `Tenancy::actingAs()` inside the
        // sweep would be swapping the session tenant underneath a paginated
        // query that is still walking `users` — and `chunkById` would resume
        // from a connection whose tenant had been changed by the consumer.
        return $ids;
    }

    /**
     * Throw the switch, record why, then wake somebody.
     *
     * ⚠️ **THE SWITCH IS WRITTEN FIRST AND THE ORDER IS DELIBERATE.** If the
     * incident write fails, the platform is halted and the evidence is missing —
     * recoverable, and somebody is looking at a stopped platform with a registry
     * history entry naming this command. The other order fails the other way: a
     * perfect incident record of a halt that never happened, while every tenant
     * keeps sending. **Between an unexplained stop and an unenforced record, the
     * stop is the survivable one.**
     *
     * ⛔ **AND THE BELL IS LAST, WHICH IS THE SAME ARGUMENT ONE STEP FURTHER
     * OUT** (7861). {@see OperatorAlerts::raise()} is a database read, an insert,
     * a `Log::critical`, a queued mail dispatch **and** an inline call to a
     * carrier — by a wide margin the most failure-prone line in this method — and
     * R25 says an alert may never be a brake. Placed first it could page about a halt that then did not happen;
     * placed last, every failure it can have leaves the switch thrown and the
     * incident filed. **`raise()` contains its own failures and returns
     * rather than throws**, so this is belt and braces rather than the only
     * guard, and it is the ordering that is pinned by test rather than the
     * containment.
     *
     * ⛔ **AND UNTIL 2026-08-22 THERE WAS NO FOURTH LINE AT ALL** (7732, 7860).
     * The three effects above are a registry key, a table row and
     * `$this->error()` — and that last one is `stdout` from a scheduled command
     * whose documented cron entry is `schedule:run >> /dev/null 2>&1`, which is
     * 7611's own mechanism. Nothing else in `app/` read either the key or the
     * table except `Admin\SendingControls`, and a screen is something somebody
     * opens. **So the loudest thing this platform can do — stopping every
     * tenant's sending, unattended, at three in the morning — woke nobody**, and
     * the first signal was a customer asking why nothing sent.
     *
     * ⚠️ **THE INCIDENT ID RIDES ON THE BELL AND IS THE REASON THE ROW IS
     * CAPTURED.** `platform_halt_incidents` is what `Admin\SendingControls`
     * renders under *"When sending stopped by itself"*, so carrying the id is
     * what turns a text message at 3am into a row an operator can open — and it
     * is the one figure here that cannot be re-derived from the window
     * afterwards, because the window rolls.
     *
     * ⚠️ **`sent` IS CARRIED FOR THE SAME REASON AND THE INCIDENT ROW DOES NOT
     * HOLD IT.** That table stores `delivered`, `complaints`, the rate, the
     * threshold, the window and the tenant count; how much traffic was handed to
     * a carrier in the window is nowhere in it, and it is the figure that says
     * how large the incident was rather than how bad the ratio was.
     *
     * ⛔ **AND IT IS {@see self::HALT_KEY}, NEVER `messaging.global_halt`**
     * (3980). The two stop the same sends; only one of them stops a STOP
     * confirmation, and it is not this command's to throw. ⚠️ **The summary says
     * so out loud**, because *"did this just silence every carrier-mandated
     * reply on the platform"* is the question an operator asks first and the
     * wrong answer to it sends them somewhere expensive.
     */
    private function halt(
        DefaultsRegistry $registry,
        OperatorAlerts $alerts,
        PlatformRateSample $sample,
        int $threshold,
    ): void {
        $registry->set(self::HALT_KEY, true, self::ACTOR);

        $incident = PlatformHaltIncident::create([
            'delivered_in_window' => $sample->delivered,
            'complaints_in_window' => $sample->complaints,
            'rate_basis_points' => $sample->basisPoints(),
            'threshold_basis_points' => $threshold,
            'window_hours' => $sample->windowHours,
            'tenant_count' => $sample->tenantCount,
            'tripped_at' => now(),
        ]);

        $this->error(sprintf(
            'PLATFORM SENDING HALTED: %d bp is at or over the %d bp threshold. '
            .'Releasing it is a person\'s decision, on the settings screen.',
            $sample->basisPoints(),
            $threshold,
        ));

        $alerts->raise(
            OperatorAlertKind::PlatformSendingHalted,
            // ⚠️ **EMPTY, BECAUSE THE KIND HAS ONE SUBJECT** — the platform, on
            // `PlatformHealthChecks`' `FailedJobSpike` precedent. ⛔ **The
            // channel was refused**: the rate is measured over SMS and the
            // switch refuses *every* channel, so `sms` here would read on the
            // board as though a tenant's email still went out. The enum case
            // carries the rest of the argument, including why a subject per
            // incident would remove the only rate limit
            // `PlatformTexter::alertOperator()` has.
            '',
            sprintf(
                'The complaint rate reached %d bp against a %d bp threshold: %d complaint(s) over '
                .'%d delivered message(s) in %dh across %d business(es). Carrier STOP and HELP '
                .'replies still go out. Nothing starts again by itself — a person releases it on '
                .'the sending controls screen.',
                $sample->basisPoints(),
                $threshold,
                $sample->complaints,
                $sample->delivered,
                $sample->windowHours,
                $sample->tenantCount,
            ),
            [
                // ⚠️ A SNAPSHOT AND NOT A ROLLING FIGURE, WHICH IS THE OPPOSITE
                // OF THIS COMMAND'S OTHER TWO BELLS (7605, 7864). This can ring
                // only on the sweep that throws the switch — every later sweep
                // returns at "already halted" — so these are the numbers the
                // machine acted on, and an incident review needs exactly those.
                'rate_bp' => $sample->basisPoints(),
                'threshold_bp' => $threshold,
                'complaints' => $sample->complaints,
                'delivered' => $sample->delivered,
                'sent' => $sample->sent,
                'tenant_count' => $sample->tenantCount,
                'window_hours' => $sample->windowHours,
                'incident_id' => (int) $incident->id,
                // ⛔ WHICH OF THE TWO SWITCHES, AND IT IS THE FIGURE THAT
                // ANSWERS "ARE STOP AND HELP STILL WORKING" (3980–3983).
                'halt_key' => self::HALT_KEY,
            ],
            // ⛔ **STATED RATHER THAN INFERRED, AND IT IS THE ONE KEYWORD LANE D
            // COULD NOT WRITE** (7873b, 7902). `AlertOrigin` did not exist on
            // D's branch, so writing it there would have reddened all three
            // gates on a branch whose independent green is what the merge
            // depends on. The inference would land on `Platform` today —
            // `Route::current()` is null in a console command — but that is a
            // property of **this call site**, not of this bell: an operator
            // triggering the same sweep from an admin screen would infer
            // `Request` and inherit a ten-a-day push budget on the loudest
            // thing this platform does.
            origin: AlertOrigin::Platform,
        );
    }
}

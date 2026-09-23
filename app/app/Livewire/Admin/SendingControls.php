<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OutreachChannel;
use App\Enums\SendingPauseReason;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\PlatformHaltIncident;
use App\Models\RegistryChange;
use App\Models\SendingPause;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\IdentifierHashEpochs;
use App\Services\Messaging\Outbound\SendSettlement;
use App\Services\Messaging\PlatformComplaintRate;
use App\Services\Messaging\PlatformRateSample;
use App\Services\Messaging\RateReading;
use App\Services\Messaging\ReviewInviteSender;
use App\Services\Messaging\SendingGuard;
use App\Services\Messaging\SendingHealth;
use App\Services\Messaging\SendingRates;
use App\Services\Messaging\TripMath;
use App\Services\Sms\InboundMessages;
use App\Services\Tenant\TenantPause;
use App\Support\Admin\AdminAccess;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LogicException;
use Masmerise\Toaster\Toaster;

/**
 * The kill switches, given a door — T137 `SL-8`, and the surface 2478 recorded
 * as owed.
 *
 * ## Why this screen exists
 *
 * ⛔ **BOTH KILL SWITCHES WORKED AND NEITHER COULD BE OPERATED.**
 * `SendingGuard::resume()` had **no caller anywhere in `app/`**, and
 * `pause()`'s only caller was the guard's own automatic trip — with
 * `SendingPauseReason::isSelfClearing()` false for every case. So a tenant the
 * platform stopped by itself could be released **only by direct SQL**, and no
 * operator could stop one deliberately at all. `history()` had no caller either
 * (2478). `SendingRates` — the type T137 §3.2's dashboard figures come from, by
 * its own docblock — had no view or component consumer of any kind.
 *
 * That is `CLAUDE.md`'s most repeated defect from the other end: not a table
 * nobody writes, but **a control nobody can reach**. 828 recorded the same shape
 * for `TenantPause` and shipped the screen with the service for exactly this
 * reason; here the service shipped three slices ahead of the screen.
 *
 * ## Three stop-states, and an operator has to be able to tell them apart
 *
 * ⚠️ **THIS IS THE MOST IMPORTANT THING ON THE SCREEN AND IT IS WHY THE ACCOUNT
 * PANEL IS HERE AT ALL.** 2450 records the cost of confusing two of them: a
 * docblock claimed the campaign runner read *"the tenant pause — L7's automatic
 * complaint-rate trip writes this"*, and it does not — the trip writes a
 * `SendingPause` row and `TenantPause` is a different table with a different
 * reader and no automatic writer. The suite was green throughout.
 *
 *   1. **`messaging.global_halt` and `messaging.automatic_halt`** — two
 *      registry keys and one stop-state. Either stops every tenant and every
 *      channel at once; the first is thrown by an operator here, the second by
 *      `messaging:watch-platform-complaint-rate` (2400–2410). ⚠️ **They are two
 *      keys because `ComplianceReplies` honours the operator's and must not
 *      honour the machine's** (3980–3983) — 2099 makes STOP and HELP
 *      unconditional, and a scheduled sweep silencing them is not a decision
 *      anybody made. **This screen shows one answer and releases both**, so the
 *      split is invisible here and total in the one place it matters.
 *   2. **`sending_pauses`** — one tenant's *messaging* containment, 2101's.
 *      Thrown automatically by `SendingGuard` when that tenant's complaint rate
 *      crosses `messaging.complaint_trip_bp`, or by an operator here. **This is
 *      the switch this screen operates.**
 *   3. **`businesses.paused_at`** — `TenantPause`, the account-wide *"Pause
 *      Everything"*. The **owner's** switch, or support's on their behalf, and
 *      it stops every automation rather than messaging alone. It has **no
 *      automatic writer**, it is not ours to lift, and it is shown here
 *      **read-only**.
 *
 * ⚠️ **SHOWING (3) IS NOT DECORATION.** An operator who releases (2) while (3)
 * is set sees nothing sent afterwards and has every reason to conclude the
 * release did not work — and the next thing they do is release it again, or
 * report the containment as broken. Rendering all three side by side is the
 * only way "sending is stopped" has one answer.
 *
 * ## ⛔ And a fourth that is not a switch — added 2026-08-25 (9641)
 *
 * ⛔ **THE HEADING ABOVE SAID "THREE" AND THE PAGE ANSWERED *"Running for
 * everyone"* IN A STATE WHERE NOTHING WAS GOING OUT AT ALL.**
 * `ConsentService::decide()` returns `SendRefusalReason::SuppressionUnreadable`
 * — named in backticks and never in a `{@see}`, on `OperatorAlerts::probeChannels()`'
 * rule — **above** the suppression lookup and above the purpose branch whenever
 * {@see IdentifierHashEpochs::isReadable()} is false — so every send on every
 * channel for every tenant is refused, review invites and missed-call
 * text-backs included, and **neither switch on this page is set.** It is the
 * only one of the four that nobody threw: an `APP_KEY` that moved, or an
 * install that upgraded with suppressions already stored.
 *
 * ⚠️ **IT IS RENDERED AS ITS OWN PANEL AND THE "Everyone" PILL IS UNTOUCHED**,
 * on (3)'s precedent rather than by preference. That pill is the state of the
 * switch the button beside it operates, and a pill that read *"stopped"* for a
 * cause that button cannot clear is 2450's confusion rebuilt one level up. What
 * the account-pause panel does for a tenant — its own pill, its own words, and
 * a sentence pre-empting the wrong conclusion the neighbouring control invites
 * — this does for the platform, and it sits **above** the switches rather than
 * below them because a person mid-incident does not scroll before concluding
 * (9448).
 *
 * ⛔ **NOTHING ON THIS PAGE CLEARS IT AND THE PANEL SAYS SO.** The way out is
 * `php artisan consent:hash-epoch`, and the two remedies are not equivalent:
 * the previous `APP_KEY` first, `--accept-loss` second and permanent (9444).
 * The panel renders that text **whole**, from the method the command prints,
 * because 8277 records what a silent 300-character clamp did to it.
 *
 * ## What this screen refuses to offer
 *
 * ⛔ **AN OPERATOR MAY NOT PAUSE A TENANT AS `ComplaintRate`.** That case is
 * the enum's own *"the only one that happens without a human"*, and an operator
 * selecting it would forge a machine measurement: the row would carry a reason
 * asserting a threshold was crossed with `observed_rate_bp` null, and the
 * incident series 2476 exists to answer would stop distinguishing the automatic
 * trip from a person. The select offers the other three and the validator
 * refuses the fourth, because a Livewire action is callable whatever rendered
 * it (391).
 *
 * ⛔ **AND IT CANNOT RESUME THE PLATFORM'S AUTOMATIC TRIP ON A TIMER.** Nothing
 * here runs on a schedule; every release is a click with an actor (2408).
 *
 * ## The tenant is a typed number, for `PhiTenants`' reason
 *
 * `businesses` carries two RLS policies and neither admits platform staff, so
 * the runtime role cannot enumerate businesses at all. An operator acts on one
 * business at a time, named by its number, and every read and write runs inside
 * `Tenancy::actingAs()` so the audit entry lands in that tenant's own log (419).
 *
 * ⚠️ **NO BUSINESS NAME, NOTE OR ACTOR EVER REACHES A TOAST** (104). Toast text
 * carries no personal data, is never the audit record, and uses outcome
 * language.
 */
final class SendingControls extends Component
{
    /**
     * The business number an operator typed. A string, because it comes from a
     * text input and an unparseable one has to be answerable rather than fatal.
     */
    public string $lookup = '';

    /**
     * The business in view, once one has resolved.
     *
     * ⚠️ `#[Locked]` FOR `PhiTenants::$businessId`'s REASON, AND ONE MORE. Without
     * it this is an ordinary public property that arrives in the update payload,
     * so anybody who can reach this component could set it to any integer and
     * let `render()` read that tenant's incident history and rates with
     * `lookUp()` never called and its audit entry never written. Here it is also
     * the id every **write** acts on: `pauseTenant()` resolves it through
     * `viewing()`, so an unlocked property would let a client name the tenant to
     * be stopped.
     */
    #[Locked]
    public ?int $businessId = null;

    /**
     * Why this tenant is being stopped. Defaults to the operator's own case
     * rather than to the automatic one.
     */
    public string $pauseReason = 'operator';

    public string $pauseNote = '';

    public string $releaseNote = '';

    public function windowHours(): int
    {
        return app(SendingHealth::class)->windowHours();
    }

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    /**
     * Stop every tenant, immediately.
     *
     * ⚠️ **NO CONFIRMATION STEP, AND THAT IS 826'S RULING APPLIED RATHER THAN
     * FORGOTTEN.** A confirm dialog is in the way at the moment somebody needs
     * the control most; 1228 states the rule the two screens share — *confirm
     * the direction that is hard to notice you took* — and stopping is the
     * direction that announces itself. The release below is the one that asks.
     *
     * ⚠️ **THE REGISTRY IS THE RECORD, AND `audit_log` CANNOT BE.**
     * `AuditService::record()` opens with `Tenancy::idOrFail()` (419) and a
     * platform halt belongs to no tenant, so filing it under whichever business
     * happened to be established would be worse than not filing it. What records
     * this is `registry_changes` — actor, before, after, in one transaction with
     * the write (518) — which is the same append-only account `PlatformSettings`
     * relies on and 2400 points at.
     *
     * ⚠️ **GATED ON `AdminAccess::GATE` AND NOT ON `SendingPausePolicy`, WHICH
     * IS A DISTINCTION RATHER THAN AN OVERSIGHT.** 2402 is explicit that the
     * registry key **is** the switch and that no second row answers *"is the
     * platform halted"*; a policy on `SendingPause` would be authorising an act
     * against a model this method never touches. `AdminAccess::GATE` asks
     * `canAdministerPlatform()` — `super_admin` alone, which is the right width
     * for a control whose blast radius is every tenant at once.
     */
    public function haltPlatform(DefaultsRegistry $registry, SendingGuard $guard): void
    {
        $this->authorize(AdminAccess::GATE);

        // ⚠️ **ASKED OF BOTH SWITCHES, WRITTEN TO ONE** (3980–3983). An
        // operator whose platform is already stopped — by them or by the
        // complaint-rate sweep — is told so rather than stacking a second halt
        // on top of the first, which would leave one standing after they
        // released the other.
        if ($guard->haltedPlatformWide()) {
            Toaster::error('Sending is already stopped for everyone.');

            return;
        }

        $registry->set(SendingGuard::OPERATOR_HALT_KEY, true, $this->actor());

        // Outcome language, and the verb survives the flow (`22`, `29` §5.7).
        Toaster::success('Sending stopped for everyone');
    }

    /**
     * Let the platform send again.
     *
     * ⚠️ **A PERSON'S ACT, AND THE ONLY WAY THE SWITCH EVER GOES BACK** (2408).
     * `WatchPlatformComplaintRate` writes `true` and nothing in it writes
     * `false`; a rate that has fallen back under the threshold is not evidence
     * that whatever produced it was dealt with, because the window rolls forward
     * on its own.
     *
     * ⚠️ **NO NOTE IS ASKED FOR HERE, AND THAT IS DELIBERATE RATHER THAN AN
     * OMISSION.** `registry_changes` stores the key, both values and the actor;
     * it has no note column, so a box demanding a sentence would discard it —
     * which is worse than not asking, because it reads as a record. The tenant
     * release below **does** take a note, because `sending_pauses.release_note`
     * is a column that keeps it (2474). What stands in for it here is the
     * consequence stated above the button.
     *
     * ⛔ **IT CLEARS BOTH SWITCHES, AND THAT IS WHAT MAKES THE SPLIT SAFE**
     * (3980–3983). `WatchPlatformComplaintRate` throws
     * {@see SendingGuard::AUTOMATIC_HALT_KEY} rather than the operator's key,
     * so a release that touched only the operator's would leave an automatic
     * halt standing with **no door in the application at all** — the writerless
     * control from the other end (2478's shape), releasable only by direct SQL.
     * Each key is written only if it is actually set, so the change log records
     * the release of the switch that was really on and invents nothing.
     */
    public function releasePlatform(DefaultsRegistry $registry, SendingGuard $guard): void
    {
        // The registry keys, not the pause model — see haltPlatform().
        $this->authorize(AdminAccess::GATE);

        if (! $guard->haltedPlatformWide()) {
            Toaster::error('Sending is already running for everyone.');

            return;
        }

        foreach ([SendingGuard::OPERATOR_HALT_KEY, SendingGuard::AUTOMATIC_HALT_KEY] as $key) {
            if ($registry->value($key) === true) {
                $registry->set($key, false, $this->actor());
            }
        }

        Toaster::success('Sending started again for everyone');
    }

    /**
     * Put a business in view.
     *
     * `PhiTenants::lookUp()`'s shape and its reasoning, including the audit
     * entry: this screen shows a tenant's incident series, the free-text notes
     * operators wrote on it and their sending rates, so a read that leaves no
     * trace lets staff walk the business id space with nothing anywhere saying
     * they did. A miss says so plainly rather than 404ing the screen, and
     * records nothing — there is no tenant to file it under.
     *
     * ⚠️ The audit write is not wrapped and `$this->businessId` is set after it,
     * so an unwritable `audit_log` fails the whole action rather than showing
     * the tenant unaudited.
     */
    public function lookUp(AuditService $audit): void
    {
        $this->authorize(AdminAccess::GATE);
        $this->authorize('viewAny', SendingPause::class);

        $id = (int) trim($this->lookup);

        if ($id <= 0) {
            $this->businessId = null;
            Toaster::error('Enter a business number.');

            return;
        }

        $business = $this->resolve($id);

        if (! $business instanceof Business) {
            $this->businessId = null;
            Toaster::error("There is no business {$id}.");

            return;
        }

        Tenancy::actingAs(
            $id,
            fn (): AuditLogEntry => $audit->record('business.viewed_by_staff', $this->actor(), $business),
        );

        $this->businessId = $id;
    }

    /**
     * Stop this tenant sending.
     *
     * ⚠️ **AUTHORIZE BEFORE VALIDATE**, `PhiTenants::raiseToPhi()`'s rule: an
     * ungated caller answered with a validation error is told the action exists
     * and which fields it wants.
     *
     * ⚠️ **THE REASON IS VALIDATED AGAINST THE ENUM MINUS ONE CASE.** See the
     * class docblock on why `ComplaintRate` is not an operator's to write. The
     * select does not offer it; this is what makes that true of the action
     * rather than only of the markup.
     *
     * ⚠️ **AND THE NOTE IS REQUIRED, WHICH THE SERVICE DOES NOT REQUIRE.** An
     * automatic trip carries `observed_rate_bp` and needs no prose; an operator
     * pause carries nothing else at all, so a row with no note is an account
     * stopped for a reason nobody recorded. Bounded at 500 here as well as in
     * the service and at the database (2475), because the row is never deleted.
     */
    public function pauseTenant(SendingGuard $guard): void
    {
        $this->authorize(AdminAccess::GATE);
        $this->authorize('create', SendingPause::class);

        $this->validate([
            'pauseReason' => [
                'required',
                Rule::enum(SendingPauseReason::class)->except(SendingPauseReason::ComplaintRate),
            ],
            'pauseNote' => ['required', 'string', 'max:500'],
        ], attributes: [
            'pauseReason' => 'reason',
            'pauseNote' => 'note',
        ]);

        $this->act(function () use ($guard): string {
            if ($guard->isPaused()) {
                throw new InvalidArgumentException(
                    'This business is already stopped. Release the incident that is open before opening another.'
                );
            }

            $guard->pause(
                reason: SendingPauseReason::from($this->pauseReason),
                trippedBy: $this->actor(),
                note: $this->pauseNote,
            );

            $this->pauseNote = '';

            return 'Sending stopped for this business';
        });
    }

    /**
     * Let this tenant send again.
     *
     * ⚠️ **THE ACTOR IS THE AUTHENTICATED USER AND IS NEVER A FIELD** (2408).
     * `resume()` writes `released_by` from it, and `sending_pauses` has a CHECK
     * that refuses a release which does not name somebody — so a release with no
     * recorded actor is refused by the database as well as unreachable from
     * here. The two are layers rather than substitutes (314–316).
     *
     * ⚠️ **NOTHING SELF-CLEARS, AND THE VERY NEXT SEND MAY TRIP AGAIN.** The
     * window has not rolled just because somebody clicked, so a tenant released
     * into the same complaint rate opens a **second** incident (2472). The
     * screen says so rather than letting an operator read that as a failure.
     */
    public function releaseTenant(SendingGuard $guard): void
    {
        $this->authorize(AdminAccess::GATE);

        $this->validate([
            'releaseNote' => ['required', 'string', 'max:500'],
        ], attributes: [
            'releaseNote' => 'note',
        ]);

        $this->act(function () use ($guard): string {
            $pause = $this->livePause();

            if (! $pause instanceof SendingPause) {
                throw new InvalidArgumentException(
                    'This business is not stopped, so there is nothing to release.'
                );
            }

            // ⚠️ THE POLICY IS ASKED ABOUT THE ROW, NOT ONLY ABOUT THE SCREEN.
            // 2478's question is *"a tenant must not be able to clear their own
            // containment"*, and it is answered on the ability rather than on
            // the route — a second door added later inherits the answer.
            $this->authorize('release', $pause);

            if (! $guard->resume($this->actor(), $this->releaseNote)) {
                throw new LogicException(
                    'Nothing was released — somebody else released this business first.'
                );
            }

            $this->releaseNote = '';

            return 'Sending started again for this business';
        });
    }

    /**
     * ⛔ **`IdentifierHashEpochs` IS READ AND NEVER OBSERVED** — 9640.
     * `Architecture/ConsentTest`'s *only the consent write paths observe an
     * identifier hash epoch* is what keeps it that way, and the hazard it names
     * is precisely this file: an epoch is recorded when a durable hash is
     * **written** and never when one is read, and a screen filing what it found
     * would record the new key on the first render after a rotation and
     * conclude everything was current. Three read-only calls, one memoised
     * query between them.
     */
    public function render(
        DefaultsRegistry $registry,
        SendingGuard $guard,
        SendingHealth $health,
        TenantPause $accountPause,
        PlatformComplaintRate $platformRate,
        IdentifierHashEpochs $epochs,
    ): View {
        $this->authorize(AdminAccess::GATE);

        $business = $this->businessId === null ? null : $this->viewing(
            fn (): ?Business => Business::query()->find($this->businessId),
        );

        // ⛔ **ONE AGGREGATE, ONCE, AND IT WAS TWO UNTIL 2026-08-16** (4499).
        // `readings()` and `tenantTripMath()` each called `rates()` — a
        // five-`SUM` aggregate — inside its own `Tenancy::actingAs()`, so every
        // render paid two round trips and two `set_config`. ⚠️ **And they were
        // not in one transaction**, so a delivery receipt or a STOP landing
        // between them made the Complaints card and the trip-math panel report
        // different denominators for the same tenant on the same page: 3418's
        // both-halves-right-on-their-own-screen shape, arriving by a race rather
        // than by a second source of truth.
        // ⚠️ **BOTH CHANNELS, ONE TENANT CONTEXT, FOR THE HALF OF 4499 THAT
        // STILL APPLIES.** The two aggregates are per channel and cannot
        // disagree with each other about anything — they describe different
        // traffic — so the race 4499 records is not reproducible across them.
        // What is reproducible is the *cost*: `Tenancy::actingAs()` issues a
        // `set_config` per entry, and a second one buys nothing here. Inside
        // each channel the old rule is unchanged and is what matters: one
        // `rates()` call feeds that channel's cards **and** its trip math, so
        // a delivery receipt landing mid-render cannot make them report
        // different denominators for the same tenant on the same page.
        $rates = $business instanceof Business ? $this->viewing(
            /** @return array{sms: SendingRates, email: SendingRates} */
            fn (): array => [
                'sms' => $health->rates(OutreachChannel::Sms),
                'email' => $health->rates(OutreachChannel::Email),
            ],
        ) : null;

        $reported = $platformRate->lastReported();

        return view('livewire.admin.sending-controls', [
            // ⚠️ **THE GUARD'S OWN PREDICATE, NOT A SECOND SPELLING OF IT**
            // (3980–3983). There are two halt keys now; a screen that asked
            // about one of them would show "running for everyone" while every
            // send refused, which is the both-halves-right-on-their-own-screen
            // failure 3418 records.
            'halted' => $guard->haltedPlatformWide(),

            // ⛔ **THE FOURTH STOP-STATE, AND THE ONLY ONE NOBODY THREW**
            // (9449(c), 9641). `ConsentService::decide()` returns
            // `SuppressionUnreadable` above the suppression lookup and above
            // the purpose branch whenever the stored identifier hashes cannot
            // be read, so every send on every channel for every tenant is
            // refused — and **neither switch on this page is set**. Until this
            // panel the page answered *"Running for everyone"* in that state,
            // which is the both-halves-right-on-their-own-screen failure 3418
            // records and 3980–3983 already caught once on this very pill.
            //
            // ⚠️ **DERIVED, NEVER RE-TYPED.** The predicate is the same
            // `isReadable()` the refusal itself asks, so the page cannot say
            // sending is running while `decide()` refuses, or the reverse.
            //
            // ⛔ **ONE VALUE AND NOT THREE** (9645) — see
            // `SuppressionReadability`, which exists because a mutation proved
            // the three could disagree on this very screen.
            'registers' => $epochs->readability(),
            'haltedByMachine' => $registry->value(SendingGuard::AUTOMATIC_HALT_KEY) === true,
            'haltHistory' => $this->haltHistory($registry),
            'haltIncidents' => $this->haltIncidents(),
            'automaticHalt' => $this->automaticHaltSetting($registry),
            'platformTripMath' => $this->platformTripMath($registry, $reported),
            // ⚠️ **THE BREADTH OF THE READING, WHICH WAS WRITTEN, RECONSTRUCTED
            // AND NEVER READ** (4499). "0.83% across 9 businesses" and "0.83%"
            // are different claims about the same figure, and the second one
            // leaves an operator unable to tell a platform-wide reading from a
            // sweep that found one tenant.
            //
            // ⚠️ **THE NULL TRAVELS WITH THE PANEL RATHER THAN BESIDE IT.** It
            // is read from the same `$reported` the `TripMath` above is built
            // from, so *"nothing has been measured"* and *"there is no breadth
            // to state"* are one condition and not two that could disagree.
            // ⛔ **So the template's guard on this variable cannot be driven**,
            // and it is kept anyway — 4394's rule against undrivable guards is
            // about ones somebody reasons from, and a Blade variable carries no
            // type that would make this one unnecessary. It is not a fallback
            // for *"0 businesses"*: the sentence it guards renders only on the
            // measured branch, where this is always an integer.
            'platformTenantCount' => $reported === null ? null : $reported['sample']->tenantCount,
            'reasons' => $this->offerableReasons(),

            'business' => $business,
            'livePause' => $business instanceof Business ? $this->viewing(
                fn (): ?SendingPause => $this->livePause(),
            ) : null,
            'history' => $business instanceof Business ? $this->viewing(
                fn (): array => $guard->history(20),
            ) : [],
            'readings' => $rates === null ? [] : $this->readings($registry, $rates['sms']),
            'tenantTripMath' => $rates === null
                ? null
                : $this->tenantTripMath($registry, $rates['sms']),

            // ⛔ **THE EMAIL ARM, AND IT IS NOT A COPY OF THE ONE ABOVE** —
            // see {@see self::emailReadings()} for the card this panel
            // deliberately does not have.
            'emailReadings' => $rates === null ? [] : $this->emailReadings($registry, $rates['email']),
            'emailTripMath' => $rates === null
                ? null
                : $this->emailTripMath($registry, $rates['email']),

            // The third stop-state, read-only. See the class docblock.
            'accountPaused' => $business instanceof Business
                && $accountPause->isPaused($business),
            'accountPausedBy' => $business instanceof Business
                ? $accountPause->pausedBy($business)
                : null,
        ]);
    }

    /**
     * The three rates T137 §3.2 names, each with §3 rail 2's threshold.
     *
     * ✅ **ALL THREE ARE LIVE NOW, AND THIS DOCBLOCK SAID THEY READ ZERO FOR
     * EVERY TENANT UNTIL 2026-08-13** (3038). It was true when written:
     * 2496–2499 left `SendingHealth` with no writer anywhere in `app/`. The DLR
     * webhook and the STOP handler wired four counters on 2026-08-12
     * (2520–2539), and `sent` — this delivery rate's denominator — followed on
     * 2026-08-13 from {@see SendSettlement}
     * (3030–3040). ⚠️ **The stale sentence outlived the defect on the one screen
     * an operator reads to find out whether anything is being measured at all**,
     * which is `CLAUDE.md`'s 2505 shape in its most expensive position.
     *
     * {@see RateReading} is still what keeps this honest, now in the ordinary
     * way rather than the emergency one: it carries the denominator beside the
     * rate, and an empty denominator renders as an absence, never a confident
     * `0%`.
     *
     * ⚠️ **THE DELIVERY RATE'S POPULATION IS NARROWER THAN "SENT" SOUNDS**
     * (3034). It counts only messages that were given a carrier handle, because
     * that is the only population a delivery receipt can ever land on — so
     * compliance auto-replies and all email sit outside it by construction, and
     * this figure is a **carrier-SMS** delivery rate rather than the platform's
     * overall one.
     *
     * ⛔ **"IT ALSO LAGS … A BUSY TENANT READS SLIGHTLY LOW" AND "NO THRESHOLD
     * IS SEEDED FOR IT … NOTHING AUTOMATIC ACTS ON EITHER" WERE BOTH TRUE AND
     * ARE BOTH FALSE — CORRECTED 2026-08-22 (7560, 7568).** The delivery
     * threshold became a **set** figure in the production registry on
     * 2026-08-22, bought as the detector for the receipt blind spot (7543), so
     * this card is watched and it goes red. And *"slightly low"* understated the
     * lag by the width of the defect: the figure divided by `sent`, which counts
     * every message no verdict has arrived for, and Infobip's own `EXPIRED`
     * status hangs on a validity period that runs to **days** where this window
     * is 24 hours — so a tenant's very first message read `0.0%` and a healthy
     * 500-message campaign read `60.0%`. ⚠️ **A document in this repository may
     * not state what a running install has on** (`CLAUDE.md`, 5913/6121): what
     * is recorded here is that the key is settable and is now read as a live
     * alert, never what it holds today. ✅ {@see RateReading::settled()} divides
     * by the population a verdict has arrived for and carries the silence arm;
     * its docblock is the argument, and 7561 is why a volume floor on `sent` was
     * refused.
     *
     * ⚠️ **NOTHING HERE SEEDS, BACKFILLS OR SUBSTITUTES A FIGURE.** A dashboard
     * that filled a gap would be the writerless counter surviving another slice
     * — 2498 records that it already survived two, because every test seeds the
     * counters by hand.
     *
     * ⛔ **"EMAIL DELIVERY IS THE MAIL ENGINE'S OWN REPORTING (2420–2447)"
     * WAS TRUE AND IS NOT — CORRECTED 2026-08-21 (6660).** This paragraph read:
     * *"SMS ONLY, AND THE CHANNEL IS NAMED ON SCREEN. `messaging.
     * complaint_trip_bp` is the figure `SendingGuard` compares for the trip that
     * 2101 exists to contain, and that trip is about the 10DLC brand and our own
     * number pool. Email delivery is the mail engine's own reporting (2420–2447)
     * and putting it under the same heading would put two different vendors'
     * bounce vocabularies behind one number."*
     *
     * **There is no separate mail-engine report and there never was.** 2420–2447
     * built the SES feedback endpoint and a *suppression*, which answers *is
     * this address dead* — a different question from *is this tenant's sending
     * healthy*, over a different population, with no rate and no screen. 6360
     * then gave all four email counters writers in **this** table, and 6375
     * recorded the consequence in terms: an operator has a per-tenant email
     * complaint rate that can pause a business and no screen shows it. So the
     * sentence sent the one reader who would have gone looking somewhere that
     * does not exist — `CLAUDE.md`'s 2505 shape on the screen a containment is
     * read from, which is where 3038 already put it once.
     *
     * ⚠️ **WHAT SURVIVES OF IT IS THE SEPARATION, AND THE SEPARATION IS WHY
     * THERE ARE TWO PANELS RATHER THAN ONE SET OF CARDS** (6661). Two vendors'
     * bounce vocabularies behind one number would still be wrong, and
     * {@see SendingHealth} never adds the channels together — so this method
     * stays **SMS only** and {@see self::emailReadings()} is its own grid, its
     * own trip math and its own heading. What changed is that the second grid
     * now exists on this screen instead of being promised elsewhere.
     *
     * ⚠️ **THE RATES ARRIVE AS AN ARGUMENT AND ARE NOT FETCHED HERE** (4499).
     * `render()` resolves them once, inside one tenant context, and hands the
     * same object to this method and to {@see self::tenantTripMath()} — so the
     * Complaints card and the trip-math panel cannot report different
     * denominators for the same tenant on the same page because a receipt landed
     * between two queries.
     *
     * @return list<RateReading>
     */
    private function readings(DefaultsRegistry $registry, SendingRates $rates): array
    {
        $window = app(SendingHealth::class)->windowHours();

        return [
            RateReading::settled(
                name: 'Delivered',
                landed: $rates->delivered,
                // ⛔ **`outcomesReported()` AND NOT `sent` — 7560–7563.** The
                // rate this card is compared against a threshold must be over
                // the messages a carrier has actually adjudicated, or the
                // comparison is measuring lag: an attempt with no verdict has
                // not failed to land, it has not been judged. `SendingRates`'
                // own docblock rules this for the complaint rate (1645) and
                // this is the one rate that never obeyed it.
                reportedOn: $rates->outcomesReported(),
                handedOver: $rates->sent,
                denominatorNoun: 'reported on by the carrier',
                handedOverNoun: 'handed to a carrier',
                windowHours: $window,
                // ⚠️ THE FIGURE IS THE OWNER'S AND IS STILL NOT INVENTED HERE
                // (2409). `intOr(…, 0)` because the manifest seeds `0` meaning
                // *nobody is watching*, which the reading renders as "No alert
                // set" rather than as a passing grade every rate satisfies.
                thresholdBp: $registry->intOr('messaging.delivery_rate_alert_bp', 0),
                // ⚠️ **THE TRIP'S OWN FLOOR, REUSED RATHER THAN INVENTED**
                // (2409, 7564). It does two jobs here and they are one idea:
                // how much has to be reported on before this rate may be
                // judged, and how much may be handed over with **nothing**
                // reported before that silence is itself the alert. The second
                // is `SendingRates::trafficWithoutOutcomes()`'s own argument —
                // *"the floor is the caller's and deliberately the trip's own
                // figure. A number invented here would be a policy nobody
                // set."* ⛔ A per-rate floor key is a manifest change and is
                // nobody's this wave (7569).
                minimumSample: $registry->int('messaging.complaint_trip_min_delivered'),
            ),
            RateReading::ceiling(
                name: 'Asked to stop',
                basisPoints: $rates->optOutRateBp(),
                denominator: $rates->delivered,
                denominatorNoun: 'delivered',
                windowHours: $window,
                thresholdBp: $registry->intOr('messaging.opt_out_rate_alert_bp', 0),
                // ⛔ **THIS ARGUMENT WAS SIMPLY OMITTED AND NOBODY HAD RECORDED
                // IT — 7564.** The omission was harmless only for as long as
                // `messaging.opt_out_rate_alert_bp` stayed unset: the moment
                // anybody sets it, one STOP against a new tenant's first
                // delivered message is a 10,000bp rate and a red pill on their
                // first day — the identical defect the delivery card had, in a
                // card nobody was looking at, waiting on one registry edit.
                // ⚠️ **The same floor as the Complaints card beside it, over
                // the identical denominator**: both divide by `delivered`, both
                // ask *how big a sample means something about this tenant*, and
                // there is exactly one figure in this application answering it.
                significanceFloor: $registry->int('messaging.complaint_trip_min_delivered'),
            ),
            RateReading::ceiling(
                name: 'Complaints',
                basisPoints: $rates->complaintRateBp(),
                denominator: $rates->delivered,
                denominatorNoun: 'delivered',
                windowHours: $window,
                // ⚠️ THE TRIP'S OWN THRESHOLD, NOT A SECOND ONE FOR THE SCREEN.
                // A dashboard alerting at a different number from the one that
                // stops the tenant is two answers to one question, which is
                // 2402's rule about second sources of truth.
                // `int()` rather than `intOr(…, 0)`, for 2861's reason: the
                // fallback belongs in the manifest, and `SendingGuard` compares
                // the same two keys the same way. A screen falling back to zero
                // where the guard falls back to 300 is 2402's second source of
                // truth, one accessor down.
                thresholdBp: $registry->int('messaging.complaint_trip_bp'),
                significanceFloor: $registry->int('messaging.complaint_trip_min_delivered'),
            ),
        ];
    }

    /**
     * How a stop is worked out for the business in view — T176 P9's trip math.
     *
     * ⚠️ **THE SAME TWO KEYS `SendingGuard::shouldTrip()` READS, THROUGH THE
     * SAME ACCESSOR** (2861). `int()` and not `intOr(…, 0)`: the fallback
     * belongs in the manifest, and a screen falling back to zero where the guard
     * falls back to the seed would tell an operator this tenant cannot be
     * stopped while the guard was ready to stop them. That direction of
     * disagreement is the dangerous one and it has already happened once here.
     *
     * ⚠️ **THE RATE IS TAKEN FROM `SendingRates`, NOT RECOMPUTED.**
     * {@see TripMath} divides nothing for exactly this reason — a second rate
     * formula beside the one the trip reads is 2402's second source of truth,
     * and the screen and the containment would disagree about the same tenant.
     *
     * ⚠️ **THE SAME `SendingRates` OBJECT THE "Complaints" CARD IS BUILT FROM**
     * (4499) — see {@see self::readings()}. Two separate `rates()` calls were
     * two aggregates a write could land between.
     */
    private function tenantTripMath(DefaultsRegistry $registry, SendingRates $rates): TripMath
    {
        return TripMath::measured(
            // Outcome language (`22`), and the words an operator would use. The
            // subject is rendered mid-sentence, so it is lower case and names
            // what stops rather than which class stops it.
            subject: 'this business',
            thresholdBp: $registry->int('messaging.complaint_trip_bp'),
            floorDelivered: $registry->int('messaging.complaint_trip_min_delivered'),
            delivered: $rates->delivered,
            complaints: $rates->complaints,
            observedBp: $rates->complaintRateBp(),
            windowHours: app(SendingHealth::class)->windowHours(),
            // ⛔ **WITHOUT THESE TWO THIS PANEL CANNOT TELL "NOTHING WAS SENT"
            // FROM "NOTHING CAME BACK", AND IT SAID THE FIRST TO BOTH** (7480).
            // See `TripMath::reportingHasGoneSilent()` for what it rendered.
            sent: $rates->sent,
            failed: $rates->failed,
        );
    }

    /**
     * The same tenant, over email — 6378(a), and the reader half of 2496.
     *
     * ⛔ **THE EMAIL COMPLAINT RATE CAN PAUSE A BUSINESS AND NOTHING RENDERED
     * IT** (6375, 6378(a)). 6360 gave all four email counters writers, so
     * {@see SendingGuard::refusalFor()} called with
     * {@see OutreachChannel::Email} — which {@see ReviewInviteSender} does on
     * every email invite — now reads a real rate and can write a real pause. An
     * operator could not see that rate, could not see it approaching, and could
     * not tell afterwards which channel had produced the stop. **A rate with no
     * reader is how 2496 survived two slices**, and this is that gap from the
     * other end.
     *
     * ⛔ **THERE IS NO "Asked to stop" CARD HERE, AND ITS ABSENCE IS THE MOST
     * LOAD-BEARING THING IN THIS METHOD** (6662). `SendingHealth::recordOptOut()`
     * is called from exactly one place in `app/` —
     * {@see InboundMessages} — with `OutreachChannel::Sms` written as a
     * literal, so `sending_health_windows.opted_out` **has no email writer at
     * all**. Rendering the third card anyway would divide a permanently-zero
     * numerator by a denominator that is genuinely growing, and
     * {@see RateReading} would correctly call that *measured*: a confident
     * **0.0%** against a real volume, which is the best-looking number on the
     * screen and means nothing. That is 2496 rebuilt as a rendering rather than
     * as a counter, on the screen written to contain it. **The card comes back
     * when a writer does**, and not before.
     *
     * ⚠️ **THE THRESHOLDS ARE THE SAME TWO KEYS, BECAUSE THE TRIP READS THE
     * SAME TWO KEYS** (6663). `SendingGuard::shouldTrip()` takes a channel and
     * reads `messaging.complaint_trip_bp` and
     * `messaging.complaint_trip_min_delivered` whichever channel it is given —
     * there is no per-channel threshold in this application. So this panel
     * renders the configured figure and **invents nothing**: 6379(a) asks
     * whether an SMS-derived 300bp over 50 delivered is right for email, where
     * the industry line is an order of magnitude lower, and that is the owner's
     * to answer. A screen quoting a number nobody set would be 2409's failure
     * with a percentage sign on it.
     *
     * ⚠️ **`int()` AND NEVER `intOr(…, 0)`**, for {@see self::readings()}'s
     * reason (2861): a screen falling back to zero where the guard falls back
     * to the manifest seed tells an operator this tenant cannot be stopped
     * while the guard stands ready to stop them.
     *
     * ⚠️ **BOTH OF THE DELIVERY CARD'S NOUNS ARE NAMED DIFFERENTLY FROM THE SMS
     * ONE AND THAT IS NOT COSMETIC.** `sent` is written by
     * {@see SendSettlement} the moment the transport
     * names the message, which for SMS is a carrier accepting it and for email
     * is SES answering `250 Ok` over SMTP (6376, 6377). *"Handed to a carrier"*
     * would be a false description of an email population, and the noun is what
     * {@see RateReading::sentence()} puts in front of an operator.
     *
     * ⛔ **"`failed` IS NOT RENDERED AS A RATE, BECAUSE IT IS NOT ONE" IS STILL
     * TRUE AND ITS SECOND CLAUSE IS NOT — CORRECTED 2026-08-22 (7563).** It read
     * *"`SendingRates` derives delivery, opt-out and complaint and never reads
     * `failed`"*, which stopped being true at 7480 and is now load-bearing here:
     * `failed` is half of {@see SendingRates::outcomesReported()}, which is this
     * card's **denominator**. There is still no fourth tile — a bounce count
     * with no rate and no threshold carries no signal — and the bounces are
     * still inside the delivery figure as the part that did not land. What
     * changed is that they are now inside it as *the part a verdict arrived
     * for*, which is what stops an outstanding message being counted as one.
     *
     * @return list<RateReading>
     */
    private function emailReadings(DefaultsRegistry $registry, SendingRates $rates): array
    {
        $window = app(SendingHealth::class)->windowHours();

        return [
            RateReading::settled(
                name: 'Delivered',
                landed: $rates->delivered,
                reportedOn: $rates->outcomesReported(),
                handedOver: $rates->sent,
                denominatorNoun: 'reported on by the mail transport',
                handedOverNoun: 'accepted by the mail transport',
                windowHours: $window,
                thresholdBp: $registry->intOr('messaging.delivery_rate_alert_bp', 0),
                minimumSample: $registry->int('messaging.complaint_trip_min_delivered'),
            ),
            RateReading::ceiling(
                name: 'Complaints',
                basisPoints: $rates->complaintRateBp(),
                denominator: $rates->delivered,
                denominatorNoun: 'delivered',
                windowHours: $window,
                thresholdBp: $registry->int('messaging.complaint_trip_bp'),
                significanceFloor: $registry->int('messaging.complaint_trip_min_delivered'),
            ),
        ];
    }

    /**
     * How an email complaint rate stops this business.
     *
     * ⛔ **THE SUBJECT IS `'this business'` AND NOT `'their email'`, WHICH IS
     * THE ONE THING ON THIS PANEL AN OPERATOR MOST NEEDS AND WOULD OTHERWISE
     * GUESS WRONG** (6664). `sending_pauses` has **no channel column** — its
     * `business_id` is `unique()`, one live row per tenant — and
     * {@see SendingGuard::refusalFor()} asks `isPaused()` before it asks
     * `shouldTrip()`, so a pause opened by an email complaint rate refuses the
     * **next text message** with `SendRefusalReason::TenantPaused` exactly as it
     * refuses the next email. A per-channel subject here would read as *"only
     * their email stops"*, which is false and is the reading an operator would
     * act on. The channel is the thing being **measured**; the stop is
     * business-wide, and {@see TripMath}'s sentence says so in both panels.
     *
     * ⚠️ **AND THE ROW CANNOT SAY WHICH CHANNEL TRIPPED IT**, which is why the
     * screen renders both standings side by side rather than annotating the
     * incident: `observed_rate_bp` is a snapshot with no channel beside it, so
     * the only honest answer to *"which one was it"* is the two rates. Adding a
     * channel column is a schema change and a migration, and it is not this
     * slice's — it is recorded at 6668.
     *
     * ⚠️ **THE RATE IS TAKEN FROM `SendingRates`, NOT RECOMPUTED**, and the same
     * `SendingRates` object the email cards are built from — {@see TripMath}
     * divides nothing, for 2402's reason, and 4499's is why it is one object.
     */
    private function emailTripMath(DefaultsRegistry $registry, SendingRates $rates): TripMath
    {
        return TripMath::measured(
            subject: 'this business',
            thresholdBp: $registry->int('messaging.complaint_trip_bp'),
            floorDelivered: $registry->int('messaging.complaint_trip_min_delivered'),
            delivered: $rates->delivered,
            complaints: $rates->complaints,
            observedBp: $rates->complaintRateBp(),
            windowHours: app(SendingHealth::class)->windowHours(),
            // ⛔ **WITHOUT THESE TWO THIS PANEL CANNOT TELL "NOTHING WAS SENT"
            // FROM "NOTHING CAME BACK", AND IT SAID THE FIRST TO BOTH** (7480).
            // See `TripMath::reportingHasGoneSilent()` for what it rendered.
            sent: $rates->sent,
            failed: $rates->failed,
        );
    }

    /**
     * How a stop is worked out for the whole platform.
     *
     * ⛔ **THE STANDING IS THE SWEEP'S TO REPORT AND THIS SCREEN CANNOT MEASURE
     * IT** (4374–4376). `sending_health_windows` is `FORCE ROW LEVEL SECURITY`
     * with a policy keyed on the session tenant, so summing it across tenants
     * from a request returns **zero** rather than failing — a healthy-looking
     * platform that is simply unreadable — and reaching every tenant means the
     * owner walk that `PlatformComplaintRate`'s docblock keeps inside a console
     * command, behind shell access. So `messaging:watch-platform-complaint-rate`
     * leaves its reading where this can find it, and when it has not run the
     * panel says *"not measured"* rather than rendering the zero.
     *
     * ⚠️ **THE CONFIGURED FIGURES AND BOTH DERIVATIONS ARE ANSWERABLE EITHER
     * WAY**, which is why the unmeasured branch is still worth rendering: the
     * question *"do these two numbers hold together"* needs no measurement at
     * all, and it is the question P9 was raised to make askable.
     *
     * ⚠️ **THE READING IS RESOLVED ONCE IN `render()` AND PASSED IN**, for the
     * same reason the tenant rates are (4499): the panel and the *"across N
     * businesses"* line beside it must describe one reading, not two cache reads
     * a sweep could land between.
     *
     * @param  array{sample: PlatformRateSample, measuredAt: CarbonImmutable}|null  $reported
     */
    private function platformTripMath(DefaultsRegistry $registry, ?array $reported): TripMath
    {
        $threshold = $registry->int('messaging.platform_complaint_trip_bp');
        $floor = $registry->int('messaging.platform_complaint_min_delivered');

        if ($reported === null) {
            return TripMath::unmeasured(
                subject: 'everyone',
                thresholdBp: $threshold,
                floorDelivered: $floor,
                windowHours: app(SendingHealth::class)->windowHours(),
            );
        }

        return TripMath::measured(
            subject: 'everyone',
            thresholdBp: $threshold,
            floorDelivered: $floor,
            delivered: $reported['sample']->delivered,
            complaints: $reported['sample']->complaints,
            // The sweep's own arithmetic, which is what the halt compares.
            observedBp: $reported['sample']->basisPoints(),
            windowHours: $reported['sample']->windowHours,
            sent: $reported['sample']->sent,
            failed: $reported['sample']->failed,
            measuredAt: $reported['measuredAt'],
        );
    }

    /**
     * Who threw the platform switch — across **both** of them, newest first.
     *
     * ⚠️ **TWO KEYS, ONE LIST, BECAUSE AN OPERATOR IS ASKING ONE QUESTION**
     * (3980–3983). "Who stopped sending, and when" has one answer, and two
     * separate lists side by side would make a reader work out which switch
     * they were looking at before they could read the times. Merged on `id`,
     * which is the same descending sort `historyFor()` uses, so two writes in
     * one second still order correctly — a timestamp cannot express that.
     *
     * @return list<RegistryChange>
     */
    private function haltHistory(DefaultsRegistry $registry): array
    {
        $changes = array_merge(
            $registry->historyFor(SendingGuard::OPERATOR_HALT_KEY, 5),
            $registry->historyFor(SendingGuard::AUTOMATIC_HALT_KEY, 5),
        );

        usort($changes, fn ($a, $b): int => (int) $b->id <=> (int) $a->id);

        return array_slice($changes, 0, 5);
    }

    /**
     * Whether the automatic platform halt is actually armed, and the two figures.
     *
     * ⚠️ **ASKED AND SHOWN BECAUSE IT COULD BE SWITCHED OFF, NOT BECAUSE IT IS**
     * (2409, 2410, armed at 2684). Both keys seeded to `0` from 2119 until
     * 2026-08-12, when the owner set them to 100bp over a floor of 50; either
     * one edited back to zero disables the trip again, and 2113 makes that trip
     * a precondition of sending at all. **So this is computed from the registry
     * on every render rather than stated** — a screen that hardcoded either
     * answer would be telling operators about the seed rather than about the
     * platform. `WatchPlatformComplaintRate` says the same thing on every run,
     * precisely so a switched-off safety control does not stay switched off for
     * a year; an operator reading this screen has the same right to know,
     * because this is where they would otherwise conclude the platform is
     * protected.
     *
     * @return array{armed: bool, thresholdBp: int, floor: int}
     */
    private function automaticHaltSetting(DefaultsRegistry $registry): array
    {
        // ⛔ `int()`, NOT `intOr(…, 0)` (2861). `WatchPlatformComplaintRate`
        // — the thing that actually throws this switch — reads these with
        // `int()`, which falls back to the manifest seed; this screen read them
        // with `intOr(…, 0)`, which falls back to *zero* when no row exists. So
        // the two disagreed by construction, and the direction of the
        // disagreement is the dangerous one: the screen told an operator the
        // platform would not stop itself while the scheduled command was armed
        // and ready to stop it.
        $threshold = $registry->int('messaging.platform_complaint_trip_bp');
        $floor = $registry->int('messaging.platform_complaint_min_delivered');

        return [
            'armed' => $threshold > 0 && $floor > 0,
            'thresholdBp' => $threshold,
            'floor' => $floor,
        ];
    }

    /**
     * The evidence behind automatic halts — 2402's table, which is never read to
     * decide.
     *
     * ⚠️ **READ HERE AND NEVER WRITTEN HERE.** `PlatformComplaintTripTest` pins
     * `WatchPlatformComplaintRate` as the only writer, deliberately: *"an
     * incident row written from a request path would mean a halt nobody can
     * trace to a measurement."* This screen is a request path.
     *
     * Not tenant-scoped and carrying no business id at all, by the same test's
     * allowlist entry — naming the worst-performing tenant in a record support
     * reads routinely would leak one tenant's standing to everyone who opens it.
     *
     * @return list<PlatformHaltIncident>
     */
    private function haltIncidents(): array
    {
        return array_values(
            PlatformHaltIncident::query()->latest('id')->limit(5)->get()->all()
        );
    }

    /**
     * The reasons an operator may choose.
     *
     * ⚠️ **BUILT BY EXCLUSION FROM THE ENUM RATHER THAN LISTED**, so a fifth case
     * appears here automatically and a reviewer decides once whether it belongs.
     * A hand-written list is the thing that silently stops covering the enum.
     *
     * @return list<SendingPauseReason>
     */
    private function offerableReasons(): array
    {
        return array_values(array_filter(
            SendingPauseReason::cases(),
            fn (SendingPauseReason $reason): bool => $reason !== SendingPauseReason::ComplaintRate,
        ));
    }

    /**
     * The incident currently stopping the business in view, or null.
     *
     * ⚠️ **THE GUARD'S OWN PREDICATE, THROUGH ITS OWN HISTORY.** A
     * `whereNull('released_at')` written here would be a second spelling of
     * "live" beside `SendingGuard::livePause()` and the partial unique index —
     * 2470's stated hazard, which is exactly why that predicate is written in
     * one place. `SendingPause::isLive()` exists for this and says so: *"a
     * reader's convenience, never the send-path answer"*.
     *
     * ⚠️ **ONE ROW IS ENOUGH, AND THE INVARIANT IS THE INDEX'S RATHER THAN THIS
     * METHOD'S.** The partial unique index admits at most one row with
     * `released_at IS NULL`, and `pause()` opens a new row only when none is
     * live (2471, 2472) — so a live row is always the **newest** row, and
     * `history(1)` cannot miss one that an unbounded scan would find. If that
     * ever stopped holding, the index would have stopped holding first and this
     * screen would be the least of it.
     */
    private function livePause(): ?SendingPause
    {
        foreach (app(SendingGuard::class)->history(1) as $pause) {
            if ($pause->isLive()) {
                return $pause;
            }
        }

        return null;
    }

    /**
     * Run one action as the business in view, and show a refusal rather than
     * swallowing it.
     *
     * `PhiTenants::act()`'s shape, including its warning: `InvalidArgumentException`
     * **extends** `LogicException`, so this one clause catches both deliberate
     * cases — a service refusing an input, and a rule nobody may act on from
     * here. Nothing in these actions parses a date, which is the one live source
     * of an accidental `LogicException` that file had to move out of the try.
     *
     * @param  callable(): string  $action
     */
    private function act(callable $action): void
    {
        $this->authorize(AdminAccess::GATE);

        try {
            $outcome = $this->viewing($action);
        } catch (LogicException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        Toaster::success($outcome);
    }

    /**
     * Run a callback as the business in view.
     *
     * ⚠️ **THIS IS THE CROSS-TENANT STEP, IN ONE PLACE.** `Tenancy::actingAs()`
     * restores the previous tenant in a finally block, so a throwing callback
     * cannot leave the operator's session pointed at somebody else's business.
     * Every read in `render()` and every write goes through here, so there is no
     * second spelling of it to keep in step — and `SendingGuard::pause()` files
     * its `sending.paused` audit entry in the acted-on tenant's own log rather
     * than in whichever business the operator happens to own (419).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    private function viewing(callable $callback): mixed
    {
        $id = $this->businessId ?? throw new InvalidArgumentException(
            'Look up a business first.'
        );

        return Tenancy::actingAs($id, $callback);
    }

    /**
     * The business a typed number resolves to, or null.
     *
     * Its own tenant context, because `businesses` is FORCE ROW LEVEL SECURITY
     * and a lookup with the operator's own tenant established would answer "no"
     * for every business but their own.
     */
    private function resolve(int $id): ?Business
    {
        return Tenancy::actingAs(
            $id,
            fn (): ?Business => Business::query()->whereKey($id)->first(),
        );
    }

    /**
     * Who is doing this, for `tripped_by`, `released_by` and the change log.
     *
     * An actor label rather than a user foreign key, matching `audit_log`'s
     * vocabulary and `SendingGuard::SYSTEM_ACTOR`'s — automation is a
     * first-class actor in these tables, so a foreign key would have nothing to
     * point at for the trip that writes most of these rows.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}

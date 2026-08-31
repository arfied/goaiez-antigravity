<?php

declare(strict_types=1);

namespace App\Services\Consent;

use App\Enums\ConsentType;
use App\Enums\SendRefusalReason;
use App\Models\Customer;
use App\Models\StateMessagingRule;
use App\Services\Config\DefaultsRegistry;
use Carbon\CarbonImmutable;

/**
 * The only reader of `state_messaging_rules` — `29` §2 rule 11's fourth item —
 * and, since 1609, the quiet-hours authority of which that table is the *per
 * state* half.
 *
 * ⛔ **THE FLOOR IS HERE BECAUSE WITHOUT IT THIS CLASS PERMITTED EVERY HOUR IN
 * EVERY STATE** (1609). `for()` returns null when a state has filed no rule, and
 * `ConsentService::stateRefusal()` correctly read that as *"nothing stricter
 * than federal here"* — and then permitted, because nothing anywhere in `app/`
 * expressed what federal actually says. That was invisible while `StateUnknown`
 * refused every marketing send outright; **slice 5 removed exactly that guard**,
 * and this table ships with no rows at all, so on the day the three scrubbing
 * registers load, *every* state is a no-row state. `platformFloorRefusal()` is
 * what closes it.
 *
 * ⛔ **QUIET HOURS BIND MARKETING ONLY, AND THAT IS A DELIBERATE OVERRIDE OF
 * `24` §3.4 AND `29` §754 RATHER THAN AN IMPLEMENTATION OF THEM** (1618). Both
 * word the quiet-hours row as applying to *all* message types, and three
 * sentences in this codebase claimed to be that row. They were wrong about their
 * own code: `ConsentService::stateRefusal()` returns null before it reaches this
 * class for any purpose `OutreachPurpose::isSubjectToDoNotCall()` answers false
 * for, so a `Transactional` send — which is every message this product sends
 * today — has never been subject to a window here. **The owner's ruling is that
 * it should not be**: the review invite fires immediately after somebody submits
 * our own feedback form, so it is a response to a just-completed interaction
 * rather than a solicitation, and federal quiet hours target solicitations.
 * ⚠️ **The alternative was refused for a concrete reason, not for convenience.**
 * `ReviewInviteSender` returns null on a refusal and never retries, and the
 * deferral scheduler is doc `43`, which is blocked on the undelivered `42` — so
 * applying quiet hours to transactional sends would silently **lose** every
 * invite from an evening submission rather than delay it. The claims are deleted
 * rather than softened, and a test pins the override so it is stated rather than
 * implied.
 *
 * ✅ THE HONEST STATE OF THIS, RESTATED ONCE THE ANSWER CHANGED (1594). The
 * table holds real statutes, the checks below are real, and there **is** now a
 * way to find out which state a recipient is in: `customers.region_code`, filled
 * by `CustomerEditor::setRegion()` from the contact's profile or by a `state`
 * column on an imported list. This paragraph said the opposite until row 4 slice
 * 5, and the reason it is corrected here rather than only in `DECISIONS.md` is
 * 384's: the sentence a reader acts on is the one in front of them.
 *
 * ⚠️ WHAT DID NOT CHANGE IS THE REFUSAL FOR A CONTACT NOBODY ANSWERED FOR.
 * `ConsentService::stateRefusal()` still refuses that *marketing* send with
 * `StateUnknown` — a blank field and a state with no statute are different
 * facts, and only one of them is safe to send under.
 *
 * ⚠️ THERE IS STILL DELIBERATELY NO `stateFor(Customer)` SEAM HERE, and there
 * was one for an hour. It could only ever `return null`, which Larastan
 * correctly reported as an unreachable return type — a resolver that cannot
 * resolve is not a seam, it is a method whose name claims a capability the
 * codebase does not have. **It stays deleted now that the column is populated,
 * for a second reason**: `ConsentService` reads the column directly, and a
 * resolver here would be the natural place for somebody to add the address
 * fallback this class spends two paragraphs refusing (1595). `businesses.
 * address` is a JSONB column and would satisfy the type, but the *business's*
 * state is not the *recipient's*.
 *
 * ⚠️ WHY REFUSING BEATS FALLING BACK TO FEDERAL. Rule 11's own words are that
 * "Florida and Washington are stricter than federal and the list moves". A
 * federal-only fallback is therefore not a conservative default — it is the
 * permissive one, applied precisely where the stricter rule was meant to bind,
 * and it would report as compliant. Refusing costs nothing today, because `24`
 * §3.3 makes every message this product sends transactional and transactional is
 * untouched by this class.
 *
 * ⚠️ WHY NOT PARSE THE STATE OUT OF `locations.address`. Because a parsed state
 * is a guess, and a guess here silently becomes policy — the reasoning CLAUDE.md
 * gives for refusing to infer the unset add-on location cap. "Springfield" is in
 * thirty-four states, and an address line that yields the wrong one produces
 * confident compliance with the wrong statute, which is worse than an honest
 * refusal. ✅ **This was closed by asking rather than by parsing** (1594–1595):
 * the owner picks the state on the contact, or a `state` column arrives on an
 * imported list. Three derivations were refused with it — the phone number's
 * NPA (numbers port), the location's or business's address, and a LERG-derived
 * range table whose own documentation calls itself a heuristic.
 *
 * ✅ THE "STATE KNOWN" BRANCH IS REACHABLE FROM PRODUCTION NOW, AND IT IS STILL
 * ALSO DRIVEN DIRECTLY. It was unreachable when this file shipped, which is why
 * every check below has a test that calls this class rather than the send path —
 * decision 398's method for a branch no controller could produce. Those tests
 * stay: they are what keeps each rule falsifiable on its own, and the end-to-end
 * ones through `ConsentService::decide()` are what prove the branch is now
 * reached at all.
 */
final class StateMessagingRules
{
    public function __construct(private readonly DefaultsRegistry $registry) {}

    /**
     * Write or replace one state's rule — the only writer of this table.
     *
     * ⚠️ THE TABLE SHIPS EMPTY AND THE ROWS ARE COUNSEL'S, NOT OURS. `29` §12.1
     * puts counsel review before the platform's legal documents go live, and a
     * state statute is the same class of content as those: Florida's FTSA was
     * narrowed within two years of passing, Washington's CEMA predates texting
     * and reached it through case law, and Oklahoma copied Florida *after*
     * Florida had already been amended. Seeding this file with a
     * remembered-from-training window would produce a table that reads
     * authoritative and cites a statute nobody checked — decision 255's lesson
     * ("verify by full name, never by tier") applied to something with
     * statutory damages rather than a price attached.
     *
     * ⚠️ WHICH IS EXACTLY WHY A WRITER EXISTS ANYWAY. A table with no writer is
     * not a feature (272, 377, 399, and CLAUDE.md's rule at five instances), and
     * "counsel has not supplied the rows" is not the same problem as "there is
     * no way to put a row in". This method and `compliance:set-state-rule` are
     * the way; the rows are a prelaunch gate item.
     *
     * ⚠️ AN AMENDMENT IS A NEW ROW, KEYED ON ITS OWN DATE, NOT AN EDIT. The
     * table is a history because nothing else can be: `audit_log` is
     * tenant-owned with RLS on `business_id`, and a statute belongs to no
     * tenant, so an overwrite would leave no record anywhere of what Florida's
     * window used to be or who changed it. Re-entering the same
     * `(state, effective_from)` corrects that version; a different date adds
     * one.
     */
    public function record(
        string $state,
        string $quietHoursStart,
        string $quietHoursEnd,
        string $citation,
        string $effectiveFrom,
        bool $requiresWrittenConsent = false,
        ?string $notes = null,
    ): StateMessagingRule {
        return StateMessagingRule::query()->updateOrCreate(
            [
                'state' => strtoupper(trim($state)),
                'effective_from' => $effectiveFrom,
            ],
            [
                'quiet_hours_start' => $quietHoursStart,
                'quiet_hours_end' => $quietHoursEnd,
                'citation' => $citation,
                'requires_written_consent' => $requiresWrittenConsent,
                'notes' => $notes,
            ],
        );
    }

    /**
     * The rule in force for a state, or null when that state has none on file.
     *
     * A state with no row is not an error and not a refusal: most states have no
     * mini-TCPA and are governed by federal rules alone. The absence of a row
     * means "nothing stricter than federal here", which is a real answer, and
     * treating it as a refusal would block marketing in forty-odd states for no
     * reason.
     *
     * ⚠️ FUTURE-DATED ROWS ARE NOT IN FORCE. An amendment can be entered the day
     * it is signed and starts applying on its own date, so nobody has to
     * remember to come back — which is the failure mode a "current row" column
     * would have had.
     *
     * ⚠️ `orderByRaw`, NOT `orderByDesc('effective_from')`. Postgres sorts NULL
     * first on a DESC order by, and this codebase fails the build for exactly
     * that reason on any `latest`/`orderByDesc` that is not on `'id'`. The
     * column is NOT NULL here, so the hazard is theoretical — the lint is not,
     * and neither is the next person who makes it nullable.
     */
    public function for(string $state, ?CarbonImmutable $asOf = null): ?StateMessagingRule
    {
        return StateMessagingRule::query()
            ->where('state', strtoupper(trim($state)))
            ->whereDate('effective_from', '<=', ($asOf ?? CarbonImmutable::now())->toDateString())
            ->orderByRaw('effective_from DESC NULLS LAST')
            ->latest('id')
            ->first();
    }

    /**
     * Why this state refuses a marketing message right now, or null if it does
     * not.
     *
     * ⚠️ TAKES THE TIMEZONES RATHER THAN READING ONE. `24` §3.4 puts quiet hours
     * "in the location's timezone", and `locations.timezone` is nullable — so a
     * caller that let this class read it would get the *server's* timezone
     * whenever the column was empty, which is a quiet-hours check that passes at
     * the wrong hours and reports as working. A null timezone is the caller's
     * refusal to make, and `ConsentService` makes it.
     *
     * ⚠️ **A LIST, AND EVERY ENTRY IS A VETO** (1609). `29` line 294 says
     * "recipient timezone"; line 754 and `24` §3.4 say "location's timezone",
     * and the two disagree for exactly the customer this gate is about — one who
     * lives in a different zone from the business texting them. The owner's
     * ruling is the strictest of both, so the caller passes the business's own
     * zone **and** every zone the recipient's state spans, and one closed window
     * among them refuses. See `ConsentService::quietHoursZones()` for why that
     * is not the derivation 1598 forbids.
     *
     * @param  list<string>  $timezones
     */
    public function refusalFor(
        StateMessagingRule $rule,
        array $timezones,
        ?ConsentType $consentType,
        ?CarbonImmutable $now = null,
    ): ?SendRefusalReason {
        if ($rule->requires_written_consent && $consentType !== ConsentType::ExpressWritten) {
            // Includes a null consent_type. A record that does not state its
            // own strength cannot satisfy a statute that names one — the same
            // reading `SendPermit` documents for its nullable $consentType.
            return SendRefusalReason::ConsentTooWeakForState;
        }

        return $this->windowRefusal(
            $rule->quiet_hours_start,
            $rule->quiet_hours_end,
            $timezones,
            $now,
        );
    }

    /**
     * The platform's own quiet-hours band, which binds in every state.
     *
     * ⛔ **THE BRANCH THAT USED TO RETURN NOTHING AT ALL** (1609). A state with
     * no row is a real answer — most states have no mini-TCPA — but "no state
     * rule stricter than federal" is not the same sentence as "no rule", and
     * until this method existed the code treated them as one and permitted at
     * 3am.
     *
     * ⛔ **A STATE ROW UNIONS WITH THIS RATHER THAN REPLACING IT** (1617). 1609
     * shipped the opposite and this docblock argued for it — *"ANDing two bands
     * would produce a window no legislature wrote"* — which has it backwards.
     * 47 CFR 64.1200(c)(1) binds nationwide: a state may be stricter and cannot
     * authorise what federal forbids, so the union of two prohibited bands is
     * exactly what obeying two statutes at once means. `ConsentService::
     * stateRefusal()` asks this **before** `refusalFor()` and on every send, not
     * only when `for()` came back empty.
     *
     * ⚠️ **THIS BINDS MARKETING ONLY, BY THE OWNER'S RULING** (1618), and the
     * sentence that used to sit here claiming it was `24` §3.4's *"All"* row is
     * deleted. See the class docblock for what that overrides and why.
     *
     * ⚠️ **AN UNUSABLE WINDOW IS A CLOSED WINDOW, AND "UNUSABLE" MEANS THREE
     * THINGS** (1619). Both figures are registry rows an operator can edit with
     * no per-key validation between the Ops screen and `DefaultsRegistry::set()`,
     * so the shapes that reach here are: a value that is not a clock at all; a
     * value that is `HH:MM`-shaped and out of range, where `29:00` compares
     * greater than every real `H:i:s` and **silently deletes the evening half of
     * the band nationwide**; and a start equal to its end, which
     * `StateMessagingRule::prohibits()` reads as a zero-hour window that permits
     * every hour. The state table refuses the last of those in the schema
     * (`state_messaging_rules_window_is_a_window`); the registry has no such
     * constraint, which is the whole asymmetry.
     *
     * ⚠️ **AND THE SEED IS A FLOOR UNDER THE FLOOR** (1619). Range and
     * degeneracy checks still admit `07:00`–`07:30`, which is well-formed, in
     * range, non-degenerate and removes the federal band almost entirely — so
     * validation alone cannot make the claim above true. The manifest seed's own
     * band is therefore evaluated **as well as** the stored one, on 1617's union
     * rule applied to the third axis: Ops may only ever *widen* the prohibited
     * band, never narrow it below 47 CFR 64.1200(c)(1). That is what the word
     * floor means, and moving the federal figures is a manifest edit that costs
     * somebody a code review rather than a keystroke.
     *
     * @param  list<string>  $timezones
     */
    public function platformFloorRefusal(array $timezones, ?CarbonImmutable $now = null): ?SendRefusalReason
    {
        $seedRefusal = $this->seedFloorRefusal($timezones, $now);

        if ($seedRefusal !== null) {
            return $seedRefusal;
        }

        $start = $this->clockTime('messaging.quiet_hours_start');
        $end = $this->clockTime('messaging.quiet_hours_end');

        if ($start === null || $end === null || self::atSameClockTime($start, $end)) {
            return SendRefusalReason::QuietHours;
        }

        return $this->windowRefusal($start, $end, $timezones, $now);
    }

    /**
     * The band the manifest seeds, which no Ops row may narrow.
     *
     * ⚠️ **A MALFORMED SEED REFUSES TOO, AND CANNOT HAPPEN QUIETLY.** Unlike the
     * stored row, the seed is a reviewed literal pinned by its own test — so
     * reaching the refusal below means somebody edited the manifest to a value
     * that is not a window, and refusing is both the safe answer and the loud
     * one.
     *
     * @param  list<string>  $timezones
     */
    private function seedFloorRefusal(array $timezones, ?CarbonImmutable $now): ?SendRefusalReason
    {
        $start = self::clockOrNull($this->registry->seedOf('messaging.quiet_hours_start'));
        $end = self::clockOrNull($this->registry->seedOf('messaging.quiet_hours_end'));

        if ($start === null || $end === null || self::atSameClockTime($start, $end)) {
            return SendRefusalReason::QuietHours;
        }

        return $this->windowRefusal($start, $end, $timezones, $now);
    }

    /**
     * Whether one evening-to-morning band is closed in any of these zones.
     *
     * ⚠️ **THE ARITHMETIC IS `StateMessagingRule::prohibits()` AND IS NOT
     * REPEATED HERE.** The window wraps midnight on every real row, and a second
     * spelling of that comparison is the one that quietly stops matching — a
     * band that permits at every hour of the day looks exactly like a band that
     * is working (the model's own docblock says so).
     *
     * ⚠️ **NO ZONES IS A REFUSAL, NOT A PASS.** An empty list would make the
     * loop below fall through to `null` and permit — the vacuous-match failure
     * (256) rebuilt inside a compliance gate. The caller cannot produce one
     * today; that is exactly when the guard is cheap.
     *
     * @param  list<string>  $timezones
     */
    private function windowRefusal(
        string $start,
        string $end,
        array $timezones,
        ?CarbonImmutable $now,
    ): ?SendRefusalReason {
        if ($timezones === []) {
            return SendRefusalReason::QuietHours;
        }

        $moment = $now ?? CarbonImmutable::now();

        foreach ($timezones as $timezone) {
            if (StateMessagingRule::prohibits($start, $end, $moment->setTimezone($timezone))) {
                return SendRefusalReason::QuietHours;
            }
        }

        return null;
    }

    /**
     * A registry value that is a wall-clock time, or null when it is not one.
     *
     * Deliberately not `strtotime()`: that accepts `now`, `tomorrow` and a
     * hundred other strings, every one of which would produce a plausible window
     * from an Ops typo rather than the refusal its caller makes.
     */
    private function clockTime(string $key): ?string
    {
        return self::clockOrNull($this->registry->stringOrNull($key));
    }

    /**
     * The same value as a wall-clock time, whatever it arrived as.
     *
     * ⚠️ **SHAPE WAS NOT ENOUGH, AND THE DOCBLOCK ABOVE ASSERTED THAT IT WAS**
     * (1619). `/^\d{2}:\d{2}(:\d{2})?$/` accepts `29:00`, which is not a time —
     * and because `StateMessagingRule::prohibits()` compares `H:i:s` strings,
     * `'29:00:00'` is greater than every real hour, so the `$at >= $start` half
     * of the wrapping band **can never be true** and the evening half of the
     * floor disappears for every tenant at once. That is the exact outcome the
     * caller's docblock said this method existed to prevent.
     *
     * The range check is on the components rather than on
     * `DateTimeImmutable::createFromFormat`, which "helpfully" rolls `29:00`
     * over into the next day at 05:00 — a plausible window from a typo, which is
     * the failure this whole method refuses.
     */
    private static function clockOrNull(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^(\d{2}):(\d{2})(?::(\d{2}))?$/', $value, $parts) !== 1) {
            return null;
        }

        if ((int) $parts[1] > 23 || (int) $parts[2] > 59 || (int) ($parts[3] ?? '0') > 59) {
            return null;
        }

        return $value;
    }

    /**
     * Whether two wall-clock times name the same instant of the day.
     *
     * ⚠️ **A BAND WHOSE ENDS ARE EQUAL PERMITS EVERY HOUR** (1619).
     * `prohibits()` takes the non-wrapping branch for `$start <= $end` and asks
     * `$at >= $start && $at < $end`, which no moment satisfies — so `21:00` to
     * `21:00` is a floor that refuses nothing while reading, on the Ops screen,
     * exactly like one that works. `state_messaging_rules` refuses this in the
     * schema; the registry keys have no constraint to refuse it with, so the
     * refusal is here.
     *
     * The padding matches `StateMessagingRule::normaliseTime()`'s, because
     * `'21:00' === '21:00:00'` is false and the two forms both reach this.
     */
    private static function atSameClockTime(string $start, string $end): bool
    {
        return substr($start.':00:00', 0, 8) === substr($end.':00:00', 0, 8);
    }
}

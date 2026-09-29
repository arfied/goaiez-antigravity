<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Enums\Plan;
use App\Exceptions\RecordingAnnouncementNotAttested;
use App\Services\Ai\AiSpend;
use App\Services\Config\DefaultsRegistry;
use App\Services\Content\Publishing;
use App\Services\Marketing\MarketingClaims;
use App\Support\Admin\AdminAccess;
use App\Support\Admin\OperatedElsewhere;
use App\Support\DefaultsManifest;
use App\Support\PlanPricing;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Masmerise\Toaster\Toaster;

/**
 * Ops → Platform → Settings — the Defaults Registry's editor (`38` Part 2).
 *
 * `38`: "the Settings editor groups by domain, shows seed vs current with a
 * per-key **Reset to seed** chip (the D-137 pattern, applied to ourselves), and
 * every change is audited with before/after."
 *
 * ⚠️ **DELIBERATELY SMALL, LIKE `ReviewQueue` AND `LegalDocuments`, AND FOR THE
 * SAME REASON.** It exists so `DefaultsRegistry` has a caller. A service
 * written, documented and uncalled is the most repeated defect in this codebase
 * — `Business::provision()` (272), `autopilot_settings` (377), `feedback_pages`,
 * `review_destinations`, `plugins` (399) — and a registry nobody can edit is not
 * an admin-editable registry, it is a config file with extra steps.
 *
 * ## Prices are shown and not editable, on purpose
 *
 * ⚠️ `38` D-151 is explicit that a price edit "creates new Stripe Prices via API
 * and repoints checkout", with grandfathering by default. Cashier and Stripe are
 * row 22 and are not built. An editor that wrote a new entitlement version and
 * touched no Stripe object would produce a registry price that disagrees with
 * what customers are actually charged — and the disagreement would be invisible,
 * because both halves would look right on their own screen.
 *
 * So the prices render read-only with that said out loud. `DefaultsRegistry::
 * setEntitlement()` is exercised by `defaults:sync` and by its tests; its
 * *edit* path is row 22's to call, alongside the Stripe write that has to happen
 * in the same breath.
 */
final class PlatformSettings extends Component
{
    /**
     * The key currently being edited, or empty when none is.
     *
     * One at a time rather than a form over every row: each change is its own
     * audited act with its own before and after, and a bulk save would make one
     * log entry out of several decisions.
     *
     * ⛔ **`#[Locked]` BECAUSE IT NAMES THE KEY {@see self::save()} WRITES**
     * (5903). It is set by {@see self::edit()} and cleared by
     * {@see self::cancel()}, and it is bound to nothing in the template — only
     * `$draft` is. Without the attribute a crafted `$set('editing', …)` followed
     * by `save()` reaches the write with `edit()`'s refusals never asked, which
     * is 398's shape: the guard everybody drives is not the guard on the path.
     */
    #[Locked]
    public string $editing = '';

    public string $draft = '';

    public array $modelOptions = [];

    public string $effectiveModel = '';

    public string $search = '';

    /**
     * The switch waiting on a second press, or empty when none is (5883).
     *
     * ⚠️ **IT IS NOT A "PENDING VALUE" AND MUST NEVER BECOME ONE**, which is why
     * it holds a key rather than a key and a value: a pending-value field would
     * make a second press write whatever the first press put in it, and the whole
     * point of {@see Publishing::authoriseSiteWrites()} and
     * {@see MarketingClaims::publish()} is that the value is the literal `true`
     * the second press narrows.
     *
     * ⚠️ **IT NOW MEANS TWO THINGS AND THE LOAD-BEARING HALF IS UNCHANGED —
     * WIDENED 2026-08-28 (11705).** This read *"the only thing this may ever mean
     * is turning the site-write switch on"*. It also means turning a capability
     * claim switch on. **The refusal that sentence was written for is the
     * pending-value one and it is untouched**; what it did not anticipate is a
     * second switch with the same shape. ⛔ **The two confirm methods each check
     * their own kind before acting** — {@see self::confirmSiteWrites()} asks
     * {@see Publishing::isSiteWriteSwitch()} and {@see self::confirmClaim()} asks
     * {@see MarketingClaims::isClaimSwitch()} — so a key parked by one path
     * cannot be finished by the other, and neither can finish a key that is
     * neither.
     *
     * ⛔ **`#[Locked]` BECAUSE {@see self::toggle()} IS THE ONLY THING THAT MAY
     * SET IT** (5891). A public Livewire property is writable from the update
     * payload, so without this a crafted `$set('confirming', …)` followed by
     * `confirmSiteWrites()` would authorise writing to customers' websites with
     * no first press at all — the two-step reduced to one by a request nobody
     * pressed twice. That is the same reasoning `TenantLocations` and
     * `Credentials` give for theirs, on a property whose consequence is larger.
     */
    #[Locked]
    public string $confirming = '';

    public function mount(): void
    {
        $this->authorize(AdminAccess::GATE);
    }

    public function edit(string $key, DefaultsRegistry $registry): void
    {
        $this->authorize(AdminAccess::GATE);

        try {
            $current = $registry->value($key);
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        if (OperatedElsewhere::has($key)) {
            // ⛔ **SHOWN, NEVER MOVED** (5901). See {@see OperatedElsewhere} for
            // why the row keeps its value and loses its control.
            Toaster::error(OperatedElsewhere::refusal($key));

            return;
        }

        if ($this->isBoolean($key)) {
            // ⛔ **THE TEXT PATH IS CLOSED SERVER-SIDE AND NOT ONLY IN THE
            // TEMPLATE** (5881). A Livewire action argument arrives in the
            // update payload and nothing about it is trustworthy — the same
            // reason `made.up.key` is refused two lines above — so a screen that
            // merely stopped *rendering* a text box for a boolean would still
            // accept `edit('actuation.enabled')` from a crafted call and put the
            // trap back.
            Toaster::error('That setting is on or off. Use its switch — typing a value here is how one ends up saved as text that no reader accepts.');

            return;
        }

        if ($this->isModelKey($key)) {
            $task = AiTask::from(substr($key, strlen('ai.model.')));

            $this->modelOptions = array_values(array_map(
                static fn (AiModel $m): string => $m->value,
                array_filter(
                    AiModel::cases(),
                    static fn (AiModel $m): bool => $m->isEmbedding() === $task->producesEmbedding()
                        && $m->isImage() === $task->producesImage(),
                ),
            ));

            $this->effectiveModel = app(AiSpend::class)->modelFor($task)->value;
        }

        $this->editing = $key;

        // Scalars edit as themselves; anything structured edits as JSON, which
        // is honest about what the column holds rather than flattening a
        // structure into a string that cannot be read back.
        $this->draft = is_scalar($current) || $current === null
            ? (string) $current
            : (string) json_encode($current);
    }

    /**
     * Move a switch — the whole of the boolean write path (5880, 5881).
     *
     * ## ⛔ WHAT THIS REPLACES, AND WHY IT WAS WORTH A SLICE
     *
     * Every registry value rendered as `type="text"` and {@see self::parse()}
     * turned the input into a boolean only on the exact lowercase `true`.
     * So `True`, `1`, `yes` and `on` fell through the match and were stored as
     * **strings** — and every reader in this application tests strictly
     * ({@see Publishing::canWriteToSite()} is `!== true`), so the setting stayed
     * off while this screen showed the typed value back as the current one under
     * a "Changed" chip. **It looked configured.** The most consequential switch
     * in the product could be set to a string that reads as on and is off, and
     * it was found by somebody asking whether it ought to be a toggle rather
     * than by a test.
     *
     * ⚠️ **THE FIX IS NOT A MORE FORGIVING PARSER.** Accepting `True` and `1`
     * makes the trap quieter rather than smaller: it fails *closed* today, which
     * is the right direction, and the spellings it would still miss would fail
     * closed silently in exactly the same way. What is removed is the ability to
     * type a boolean at all.
     *
     * ## The value is computed here and never sent
     *
     * ⚠️ The next value is `$current !== true`, not `! $current`, and that is
     * what repairs a row already holding `'True'`: `! 'True'` is `false`, so the
     * negation spelling would answer a press on a switch reading Off by writing
     * `false` and leaving it reading Off for ever. The state an operator can see
     * is *"is it exactly true"*, and the press is against what they can see.
     */
    public function toggle(string $key, DefaultsRegistry $registry, Publishing $publishing, MarketingClaims $claims): void
    {
        $this->authorize(AdminAccess::GATE);

        if (OperatedElsewhere::has($key)) {
            // ⛔ **THE SECOND DOOR 5900 CLOSED, AND THE ONE THAT WAS OPEN**
            // (5901). `messaging.global_halt` seeds a boolean, so 5880's derived
            // set made it a switch here — one press, either direction, beside a
            // screen where stopping is one press and starting again asks. The
            // careful door is pointless while this one exists.
            Toaster::error(OperatedElsewhere::refusal($key));

            return;
        }

        if (! $this->isBoolean($key)) {
            // Unreachable from the rendered screen, and reachable from a crafted
            // payload — `edit()`'s reason, in the other direction.
            Toaster::error('That setting is not a switch.');

            return;
        }

        $next = $registry->value($key) !== true;

        if ($publishing->isSiteWriteSwitch($key) && $next) {
            // ⛔ **NOTHING IS WRITTEN HERE** (5883). This arm exists so the
            // confirmation is about something: the second screen names what
            // becomes possible, and {@see self::confirmSiteWrites()} is the only
            // place a literal `true` is narrowed for it.
            $this->cancel();
            $this->confirming = $key;

            return;
        }

        if ($publishing->isSiteWriteSwitch($key)) {
            // The other direction, and it costs one press. `DefaultsRegistry::
            // assertPreconditionsMet()` made the same call for `voice.enabled`:
            // a switch you cannot turn off in an incident is worse than the
            // thing it was protecting against.
            $publishing->withdrawSiteWriteAuthority($this->actor());

            $this->cancel();

            Toaster::success('Turned off — we will not write to anybody\'s website');

            return;
        }

        if ($claims->isClaimSwitch($key) && $next) {
            // ⛔ **NOTHING IS WRITTEN HERE** (11705), the arm above's reason on a
            // different consequence. Five registry rows publish a sentence on
            // `/features`, a row on `/compare` and an answer on `/faq`, and four
            // of the five describe things this application does not do. The
            // second screen asks whether the claim is TRUE rather than whether
            // the operator meant to press the switch, and
            // {@see self::confirmClaim()} is the only place a literal `true` is
            // narrowed for one.
            $this->cancel();
            $this->confirming = $key;

            return;
        }

        if ($claims->isClaimSwitch($key)) {
            // ⛔ **AND THE WAY BACK IS ONE PRESS, FOR A DIFFERENT REASON THAN THE
            // ARM ABOVE.** There it is that a switch you cannot turn off in an
            // incident is worse than the thing it protected against. Here it is
            // that withdrawing a claim must never cost more than making one — a
            // ceremony on the way back keeps a false statement published while
            // somebody reads a confirmation.
            $claims->withdraw($this->actor(), $key);

            $this->cancel();

            Toaster::success('Turned off — the site no longer says we do this');

            return;
        }

        try {
            $registry->set($key, $next, $this->actor());
        } catch (RecordingAnnouncementNotAttested $e) {
            // The precondition `voice.enabled` carries (4505, 4514) reaches the
            // switch path too, because `voice.enabled` seeds a boolean and now
            // renders as one. The message is written for this reader and names
            // the step to take.
            Toaster::error($e->getMessage());

            return;
        }

        $this->cancel();

        Toaster::success($next ? 'Turned on' : 'Turned off');
    }

    /**
     * The second press, and the only narrowing of the literal `true` (5883).
     *
     * ⛔ **DECISION 220's PATTERN, ON THE SWITCH THAT AUTHORISES WRITING TO A
     * STRANGER'S WEBSITE.** {@see Publishing::authoriseSiteWrites()} takes PHP's
     * literal `true` type, so this line is the only one in the application that
     * can produce it, and it is reachable only after
     * {@see self::toggle()} has put the key into `$confirming` and the operator
     * has been shown, in plain words, what stops being impossible.
     */
    public function confirmSiteWrites(Publishing $publishing): void
    {
        $this->authorize(AdminAccess::GATE);

        if ($this->confirming === '' || ! $publishing->isSiteWriteSwitch($this->confirming)) {
            return;
        }

        $publishing->authoriseSiteWrites($this->actor(), true);

        $this->cancel();

        Toaster::success('Turned on — this platform may now change customers\' websites');
    }

    /**
     * The second press on a capability claim, and the only narrowing of the
     * literal `true` for one (11705).
     *
     * ⛔ **THE QUESTION IT ANSWERS IS NOT THE QUESTION THE SWITCH ASKED.** The
     * switch asks whether the operator wants the row on. This asks whether the
     * claim is true today, in the words the manifest already wrote for this
     * reader — and it is reachable only after {@see self::toggle()} has put the
     * key into `$confirming` and the panel has shown what becomes public.
     *
     * ⚠️ **THE `isClaimSwitch()` CHECK IS NOT BELT AND BRACES OVER `#[Locked]`.**
     * It is what stops this becoming a general registry writer: without it, a
     * crafted call could not set `$confirming` — the attribute holds that — but
     * {@see MarketingClaims::publish()} would still be reached with whatever key
     * a future edit let through. The service refuses a non-claim key too, which
     * is the layer that survives this method being rewritten.
     */
    public function confirmClaim(MarketingClaims $claims): void
    {
        $this->authorize(AdminAccess::GATE);

        if ($this->confirming === '' || ! $claims->isClaimSwitch($this->confirming)) {
            return;
        }

        $claims->publish($this->actor(), $this->confirming, true);

        $this->cancel();

        Toaster::success('Turned on — the site now says we do this');
    }

    public function cancel(): void
    {
        $this->editing = '';
        $this->draft = '';
        $this->modelOptions = [];
        $this->effectiveModel = '';
        $this->confirming = '';
    }

    public function save(DefaultsRegistry $registry): void
    {
        $this->authorize(AdminAccess::GATE);

        $key = $this->editing;

        if ($key === '') {
            return;
        }

        if (OperatedElsewhere::has($key)) {
            // ⚠️ **NOT A DUPLICATE OF `edit()`'s REFUSAL — THIS IS THE WRITE**
            // (5903). `$editing` is `#[Locked]` above, so the two together are
            // belt and braces rather than one guard twice; the attribute stops
            // the property being named and this stops the write happening if it
            // ever is.
            Toaster::error(OperatedElsewhere::refusal($key));

            return;
        }

        try {
            $registry->set($key, $this->parse(trim($this->draft)), $this->actor());
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        } catch (RecordingAnnouncementNotAttested $e) {
            // ⛔ **A REFUSAL THE OPERATOR CAN ACT ON, RATHER THAN A 500** (4514).
            // `DefaultsRegistry::set()` refuses `voice.enabled` without a
            // recorded announcement attestation (4505), and that exception is a
            // `RuntimeException` — so it fell straight past the `catch` above
            // and the one screen the runbook sends an operator to answered the
            // correct decision with an error page. **The message is already
            // written for exactly this reader** and names the step to take, so
            // it is shown rather than replaced.
            Toaster::error($e->getMessage());

            return;
        }

        $this->cancel();

        // Outcome language (`22`), and no value in the toast: a setting's value
        // is not personal data, but the toast is not the record either — the
        // change log is, and it holds both sides.
        Toaster::success('Setting saved');
    }

    public function resetToSeed(string $key, DefaultsRegistry $registry): void
    {
        $this->authorize(AdminAccess::GATE);

        if (OperatedElsewhere::has($key)) {
            // ⛔ **PUTTING A HALT BACK TO ITS DEFAULT IS STARTING SENDING
            // AGAIN** (5901), in one press, on a screen that never says so.
            // Every key registered here reaches its own release the same way,
            // which is why this arm exists rather than only the two above.
            Toaster::error(OperatedElsewhere::refusal($key));

            return;
        }

        try {
            $registry->resetToSeed($key, $this->actor());
        } catch (InvalidArgumentException $e) {
            Toaster::error($e->getMessage());

            return;
        }

        $this->cancel();

        Toaster::success('Setting put back to its default');
    }

    public function render(
        DefaultsRegistry $registry,
        PlanPricing $pricing,
        Publishing $publishing,
        MarketingClaims $claims,
    ): View {
        $groups = $registry->grouped();
        foreach ($groups as $heading => $rows) {
            foreach ($rows as $index => $row) {
                $groups[$heading][$index]['isModelKey'] = $this->isModelKey($row['key']);
            }
        }
        $matchCount = 0;

        if ($this->search !== '') {
            $term = strtolower($this->search);
            foreach ($groups as $heading => $rows) {
                $filtered = array_filter($rows, function ($row) use ($term) {
                    return str_contains(strtolower($row['key']), $term)
                        || str_contains(strtolower((string) $row['description']), $term);
                });

                if (empty($filtered)) {
                    unset($groups[$heading]);
                } else {
                    $groups[$heading] = array_values($filtered);
                    $matchCount += count($filtered);
                }
            }
        }

        return view('livewire.admin.platform-settings', [
            'groups' => $groups,
            'matchCount' => $matchCount,
            // ⚠️ **ONE BOOLEAN ABOUT THE ROW UNDER CONFIRMATION RATHER THAN A
            // FLAG ON EVERY ROW** (11705), passed for the reason `doors` is
            // passed rather than folded into `grouped()`: which rows are
            // marketing claims is a fact about this application's signed-out
            // surface, not about the registry, and `DefaultsRegistry` has no
            // business knowing that `/compare` exists.
            //
            // ⛔ **BOTH CONFIRMATION PANELS BRANCH ON IT, IN OPPOSITE
            // DIRECTIONS.** `$confirming` holds two kinds of key now, and a
            // panel keyed only on `$confirming === $row['key']` would ask an
            // operator turning on `features.inbox` whether to allow changes to
            // customers' websites.
            'confirmingIsClaim' => $claims->isClaimSwitch($this->confirming),
            // The heading the public sees, for the row under confirmation. Null
            // on every other render, including a site-write confirmation.
            'claimHeading' => $claims->capabilityFor($this->confirming)?->heading(),
            // ⛔ **WHICH PAGES, RATHER THAN A PARAGRAPH PROMISING ALL THREE**
            // (12244). The panel told every operator their press would add a
            // section, a comparison row and an FAQ answer; that is true of
            // `features.commerce` and of no other switch, and for
            // `features.inbox` it contradicted the manifest description on the
            // line directly above it. Empty for a site-write confirmation,
            // which is the other kind of key `$confirming` holds.
            'claimSurfaces' => $claims->publishedSurfacesFor($this->confirming),
            // ⛔ **THE ONE FACT ON THIS SCREEN THAT IS ABOUT THE MACHINE RATHER
            // THAN THE DATABASE** (6184). `actuation.enabled`'s own description
            // used to tell an operator that *"the only adapter that exists
            // reports itself unwritable, so turning this on today changes
            // nothing"*, which was a claim about `.env` made by a string in the
            // repository — and it was false in production for part of
            // 2026-08-20 (5913, 6121). **This component runs inside the
            // deployment, so it asks the container instead of asserting.**
            'adapterReachesAWebsite' => $publishing->adapterReachesAWebsite(),
            'prices' => $this->prices($pricing),
            'withheld' => DefaultsManifest::withheld(),
            // ⚠️ **PASSED IN RATHER THAN ADDED TO `grouped()`** (5902). Which
            // screen owns a switch is a fact about this application's Ops
            // surfaces, not about the registry, and `DefaultsRegistry` has no
            // business knowing that a route exists.
            'doors' => OperatedElsewhere::all(),
            'history' => $this->focused() === '' ? [] : $registry->historyFor($this->focused(), 5),
        ]);
    }

    /**
     * The per-plan money values, formatted, for the read-only panel.
     *
     * Withheld figures are not asked for: `DefaultsRegistry` throws on one by
     * design, and a screen is not the place to discover that. They are listed
     * separately, with the reason, from the manifest.
     *
     * @return array<string, array<string, string>>
     */
    private function prices(PlanPricing $pricing): array
    {
        $prices = [];

        foreach (DefaultsManifest::entitlements() as $planValue => $keys) {
            $plan = Plan::from($planValue);

            foreach (array_keys($keys) as $key) {
                if (! str_contains($key, '_cents')) {
                    continue;
                }

                $prices[$plan->label()][$key] = $pricing->display($plan, $key);
            }
        }

        return $prices;
    }

    /**
     * What an operator typed, as the type the value should be.
     *
     * ⚠️ A BUDGET TYPED INTO A TEXT BOX ARRIVES AS A STRING, AND A STRING BUDGET
     * COMPARED WITH `>=` IS A BUG THAT SURFACES LATER AND ELSEWHERE.
     * `DefaultsRegistry::int()` already coerces on the way out, so this is the
     * second of two guards rather than the only one — but storing `"250"` where
     * `250` was meant would make every raw read of the row wrong, and the Ops
     * screen is the one place a human can introduce it. A JSON array edits as
     * JSON, so it parses back to an array.
     */
    private function parse(string $input): mixed
    {
        return match (true) {
            $input === '' => null,
            $input === 'true' => true,
            $input === 'false' => false,
            // Integers only. A float here would be a money value, and money is
            // integer cents in this system — accepting `199.99` would be
            // accepting the bug (`18` §Money handling).
            preg_match('/^-?\d+$/', $input) === 1 => (int) $input,
            // A JSON list or object, as edit() showed it. Only the shape the
            // manifest can seed — a flat list of scalars, or an object of them —
            // is accepted; anything else stays a string and reads back as one.
            self::isJsonStructure($input) => self::decodeJsonStructure($input),
            default => $input,
        };
    }

    private static function isJsonStructure(string $input): bool
    {
        $t = trim($input);
        if ($t === '' || ! (str_starts_with($t, '[') || str_starts_with($t, '{'))) {
            return false;
        }
        $decoded = json_decode($t, true);

        return is_array($decoded) && json_last_error() === JSON_ERROR_NONE
            && array_reduce($decoded, fn (bool $ok, mixed $v): bool => $ok && (is_scalar($v) || $v === null), true);
    }

    /** @return array<int|string, scalar|null> */
    private static function decodeJsonStructure(string $input): array
    {
        /** @var array<int|string, scalar|null> $decoded */
        $decoded = json_decode(trim($input), true);

        return $decoded;
    }

    /**
     * The key this screen currently has in hand, for the change history panel.
     *
     * One or the other is always empty: {@see self::toggle()} calls
     * {@see self::cancel()} before setting `$confirming`, and {@see self::edit()}
     * refuses a key that could be confirmed.
     */
    private function focused(): string
    {
        return $this->editing !== '' ? $this->editing : $this->confirming;
    }

    /**
     * Whether a key's reviewed default is a boolean, and therefore whether it is
     * a switch (5880).
     *
     * ⚠️ **THE MANIFEST'S ANSWER, NOT THE STORED ROW'S** — the reason
     * {@see DefaultsRegistry::grouped()} gives, and it is the whole reason this
     * reads a seed rather than `is_bool($registry->value($key))`: the one row on
     * the platform already holding `'True'` is the row that most needs a switch,
     * and asking the stored value would answer that it is text.
     */
    private function isBoolean(string $key): bool
    {
        $declared = DefaultsManifest::settings()[$key] ?? null;

        return $declared !== null && is_bool($declared['seed']);
    }

    private function isModelKey(string $key): bool
    {
        return str_starts_with($key, 'ai.model.')
            && AiTask::tryFrom(substr($key, strlen('ai.model.'))) instanceof AiTask;
    }

    /**
     * Who made the change, for `updated_by` and the change log.
     *
     * An actor label rather than a user id — `defaults:sync` writes here too,
     * and a foreign key would have nothing to point at for it.
     */
    private function actor(): string
    {
        $id = auth()->id();

        return $id === null ? 'admin' : 'user:'.$id;
    }
}

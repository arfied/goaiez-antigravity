<?php

declare(strict_types=1);

namespace App\Services\Config;

use App\Console\Commands\WatchPixelCanary;
use App\Enums\Plan;
use App\Exceptions\RecordingAnnouncementNotAttested;
use App\Exceptions\WithheldRegistryValue;
use App\Models\PlanEntitlement;
use App\Models\PlatformSetting;
use App\Models\RegistryChange;
use App\Services\Ops\OperatorAlerts;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Voice\RecordingAnnouncement;
use App\Support\DefaultsManifest;
use App\Support\LegalCanon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * THE DEFAULTS REGISTRY — doc `38` Part 2 (CFG1), the runtime half.
 *
 * `38` Part 2: "The docs remain the rationale; the registry is the runtime
 * truth." `DefaultsManifest` is the reviewed seed file; this is the only way to
 * read or write what it seeded, across both stores — `platform_settings` for
 * platform-wide numbers and `plan_entitlements` for per-plan ones. An
 * `ArchitectureTest` lint holds the chokepoint.
 *
 * ## Why callers no longer pass their own default
 *
 * `PlatformSetting::read()` requires one, and its docblock argues the point at
 * length: "READS FAIL CLOSED, AND THAT IS THE WHOLE DESIGN. Every caller passes
 * its own default, and the default a caller passes must be the conservative
 * one." That was right when the alternative was a bare `?? null`. It is not what
 * the registry wants, because a default at a call site is a hardcoded
 * cap/threshold literal — the exact thing `38` Part 2's lint refuses — and two
 * call sites reading the same key can disagree with each other.
 *
 * **The fail-closed property is kept and moved, not dropped.** The manifest seed
 * *is* the conservative default, it is written once, and it is reviewed. What
 * changes is that the fallback now lives in the file whose whole job is to hold
 * reviewed numbers. And the failure mode is stricter than before: a key the
 * manifest has never heard of raises rather than returning a caller's guess, so
 * a typo can no longer resolve to a working budget.
 *
 * ## Reads are not cached, deliberately
 *
 * Both tables are tiny and every read is an indexed lookup. A cache here would
 * buy microseconds and cost a class of staleness bug that is invisible in tests
 * — a settings row written in one place and read through a warm cache in
 * another. `38` Part 1 specifies a cached accessor for *credentials*, which are
 * read on every vendor call; nothing in this registry sits on a hot path. If one
 * ever does, cache it there rather than here, and bust on {@see self::set()}.
 */
final class DefaultsRegistry
{
    /**
     * The value of a platform-wide key: the stored row, or the manifest seed.
     *
     * @throws WithheldRegistryValue when the owner has not set this figure
     * @throws InvalidArgumentException when the manifest does not declare $key
     */
    public function value(string $key): mixed
    {
        $seed = $this->seedOf($key);

        // A row whose value is JSON null falls back to the seed, the same rule
        // PlatformSetting::read() applies: an operator who blanked a budget
        // meant to remove a number, not to remove a limit.
        return PlatformSetting::read($key, $seed);
    }

    /**
     * Several platform-wide keys, in one query (decision 5213).
     *
     * ⚠️ **THE SAME ANSWER AS CALLING {@see self::value()} PER KEY, AND THAT IS
     * THE ONLY THING IT MAY EVER BE.** Every key goes through `seedOf()` first,
     * so a withheld figure raises here exactly as it does there and an undeclared
     * key raises too — a batch accessor that quietly answered `null` for a key the
     * manifest has never heard of would be the side door 502 exists to close.
     *
     * ⚠️ **IT EXISTS FOR THE ONE PAGE WITH A QUERY BUDGET ON IT.** The marketing
     * home reads `billing.trial_days` and `features.industry_pages`, and
     * `DefaultsRegistryTest`'s lint moves its number *only* by the query a feature
     * genuinely adds (4337) — two keys on one tiny table do not genuinely add one.
     * Nothing else should reach for this to save a round trip it does not have a
     * gate on.
     *
     * @param  list<string>  $keys
     * @return array<string, mixed>
     *
     * @throws WithheldRegistryValue when the owner has not set one of these figures
     * @throws InvalidArgumentException when the manifest does not declare one of them
     */
    public function values(array $keys): array
    {
        $defaults = [];

        foreach ($keys as $key) {
            $defaults[$key] = $this->seedOf($key);
        }

        return PlatformSetting::readMany($defaults);
    }

    /**
     * The value of a platform-wide key as a list of integers.
     *
     * A blank or malformed row falls back to the seed rather than to an empty schedule,
     * because an empty schedule silently disables retries.
     *
     * @return list<int>
     */
    public function intList(string $key): array
    {
        $list = [];

        foreach (explode(',', $this->string($key)) as $part) {
            $part = trim($part);

            if ($part !== '' && ctype_digit($part)) {
                $list[] = (int) $part;
            }
        }

        if ($list === []) {
            foreach (explode(',', (string) $this->seedOf($key)) as $part) {
                $part = trim($part);

                if ($part !== '' && ctype_digit($part)) {
                    $list[] = (int) $part;
                }
            }
        }

        return $list;
    }

    /**
     * The value of a platform-wide key as an integer.
     *
     * Numbers arrive from jsonb, where `250` reads back as an int but a row
     * hand-edited to `"250"` reads back as a string — and a string budget
     * compared with `>=` is a bug that only appears after somebody edits the
     * row.
     *
     * @throws InvalidArgumentException when $key has no seed to fall back to
     */
    public function int(string $key): int
    {
        $seed = $this->seedOf($key);

        if (! is_int($seed)) {
            throw new InvalidArgumentException(
                "`{$key}` has no integer seed in DefaultsManifest, so there is nothing "
                .'to fail closed to. Use intOr() with an explicit fallback, or seed it.'
            );
        }

        return PlatformSetting::readInt($key, $seed);
    }

    /**
     * The value of a platform-wide key as a float.
     *
     * The weight/threshold twin of {@see self::int()}, for the figures doc `51`
     * §1 states with a decimal point — `numbers.health_weight_delivered` and its
     * siblings, `numbers.quarantine_stop_pct`. Numbers arrive from jsonb, where
     * `0.45` reads back as a float but an integer-valued seed such as `3` (no
     * fractional part) reads back as an int — accepted here too, since PHP
     * widens an int to a float on use and refusing one would make a whole-number
     * weight unseedable through this accessor for no reason.
     *
     * @throws InvalidArgumentException when $key has no numeric seed to fail
     *                                  closed to
     */
    public function float(string $key): float
    {
        $seed = $this->seedOf($key);

        if (! is_float($seed) && ! is_int($seed)) {
            throw new InvalidArgumentException(
                "`{$key}` has no float or integer seed in DefaultsManifest, so there is nothing "
                .'to fail closed to.'
            );
        }

        return PlatformSetting::readFloat($key, (float) $seed);
    }

    /**
     * The value of a platform-wide key as a string.
     *
     * {@see self::int()} and {@see self::float()}'s twin, for the canonical
     * sentences CC-4 landed in this registry — a footer composed onto a text
     * message and a guarantee quoted on a page. Both callers want *a string*
     * and neither has anything sensible to do with `null`.
     *
     * ⚠️ **A NON-STRING STORED VALUE FALLS BACK TO THE SEED RATHER THAN BEING
     * CAST.** `platform_settings.value` is jsonb, so a row hand-edited to `42`
     * or `true` reads back as an int or a bool; `(string)` on those produces
     * `"42"` and `"1"`, and a review-invite footer reading `1` is worse than one
     * reading the reviewed default. {@see self::stringOrNull()} is the accessor
     * for a key whose unset state means something — this one is for a key whose
     * unset state means "use the words we wrote".
     *
     * @throws WithheldRegistryValue when the owner has not set this figure
     * @throws InvalidArgumentException when $key has no string seed to fall
     *                                  back to
     */
    public function string(string $key): string
    {
        $seed = $this->seedOf($key);

        if (! is_string($seed)) {
            throw new InvalidArgumentException(
                "`{$key}` has no string seed in DefaultsManifest, so there is nothing to fail "
                .'closed to. Use stringOrNull() for a key whose unset state is meaningful.'
            );
        }

        $value = PlatformSetting::read($key, $seed);

        return is_string($value) ? $value : $seed;
    }

    /**
     * The value of a platform-wide key as an integer, with a runtime fallback.
     *
     * For the handful of keys whose correct default is *computed* rather than
     * written down — `public_audit.daily_spend_ceiling_cents` derives from the
     * audit budget times what an audit costs. Those keys are declared in
     * {@see DefaultsManifest::declaredWithoutSeed()} with the reason, and this
     * is the only way to read one as a number.
     *
     * ⚠️ **NOT AN ESCAPE HATCH FOR A KEY SOMEBODY DID NOT WANT TO SEED.** The
     * manifest still has to declare the key, so reaching for this does not let a
     * literal in through the side door — it makes the caller say, in the
     * manifest, why its default cannot be a constant.
     */
    public function intOr(string $key, int $fallback): int
    {
        $this->assertDeclared($key);

        return PlatformSetting::readInt($key, $fallback);
    }

    /**
     * The value of a platform-wide key as a string, or null when unset.
     *
     * Used for the model router's per-task overrides, which have no seed: unset
     * means the task's own priced default applies.
     */
    public function stringOrNull(string $key): ?string
    {
        $seed = $this->seedOf($key);

        $value = PlatformSetting::read($key, $seed);

        return is_string($value) ? $value : null;
    }

    /**
     * The seed the manifest declares for a key, or null when it declares the
     * key without one.
     *
     * ⚠️ **THE WITHHELD CHECK IS HERE RATHER THAN IN `value()`, SO EVERY READ
     * PATH FAILS THE SAME WAY** (T176 P21). `value()`, `int()`, `float()` and
     * `stringOrNull()` all funnel through this method, and putting the guard on
     * only one of them would leave three doors into a figure the owner has not
     * set — the shape 398 warns about, with the *inner* guard being the one
     * missing. `intOr()` is deliberately not one of them: it takes an explicit
     * caller fallback and never asks the manifest for a seed.
     *
     * ⛔ **AND IT IS CHECKED BEFORE THE SEED AND BEFORE THE STORED ROW**, which
     * is `entitlement()`'s ordering and its reason: a withheld figure somebody
     * typed into `platform_settings` by hand is exactly the guess the manifest
     * exists to refuse, and the value being in the database is not evidence the
     * owner set it.
     *
     * @throws WithheldRegistryValue when the owner has not set this figure
     * @throws InvalidArgumentException when the manifest does not declare $key
     */
    public function seedOf(string $key): mixed
    {
        $this->assertNotWithheld($key);

        $settings = DefaultsManifest::settings();

        if (array_key_exists($key, $settings)) {
            return $settings[$key]['seed'];
        }

        $this->assertDeclared($key);

        return null;
    }

    /**
     * Move a platform-wide value, recording both sides of the change.
     *
     * `38` Part 2: "every change is audited with before/after".
     *
     * ⚠️ **THE TRANSACTION IS LOAD-BEARING AND WAS MISSING WHEN THIS DOCBLOCK
     * FIRST CLAIMED IT** (decision 518). Without it a failing log insert leaves
     * the value moved and no record that it moved — which is the one outcome an
     * audit log exists to make impossible, and it would be invisible, because
     * the setting would look perfectly correct on the screen. That is 314–316's
     * pattern (a protection layer asserted before it is true) inside the slice
     * that quotes it twice.
     */
    public function set(string $key, mixed $value, string $actor): PlatformSetting
    {
        // ⚠️ **THE WRITE PATH IS THE ONE WORTH GUARDING AND IT IS GUARDED
        // SEPARATELY**, because it never calls `seedOf()`. `setEntitlement()`
        // makes the identical check for the identical reason: an Ops screen
        // typing a withheld figure in is how a guess becomes indistinguishable
        // from a decision.
        $this->assertNotWithheld($key);

        $this->assertDeclared($key);

        $this->assertPreconditionsMet($key, $value);

        return DB::transaction(function () use ($key, $value, $actor): PlatformSetting {
            $before = PlatformSetting::query()->find($key);

            $description = DefaultsManifest::settings()[$key]['description'] ?? null;

            $setting = PlatformSetting::write($key, $value, $actor, $description);

            RegistryChange::create([
                'setting_key' => $key,
                // `null` when the key had no row at all, which is a different
                // fact from a row holding JSON null and is why the column is
                // nullable.
                'value_before' => $before?->value,
                'value_after' => $value,
                'actor' => $actor,
                'created_at' => Carbon::now(),
            ]);

            return $setting;
        });
    }

    /**
     * Record a platform-scoped change that is not a registry value (4344).
     *
     * ⛔ **IT WRITES THE LOG AND NEVER THE THING** — no `platform_settings` row,
     * no entitlement, no validation of the subject against the manifest. That is
     * the whole difference between this and {@see self::set()}, and it is why the
     * parameter is called a *subject* rather than a key.
     *
     * ⚠️ **IT EXISTS SO THAT `registry_changes` STAYS THE ONE PLATFORM CHANGE
     * TRAIL, NOT SO THAT ANYTHING MAY WRITE TO IT.** `plan_offers` is the second
     * table holding a live price with no tenant and no RLS, and opening or
     * closing an offer changes what every future signup is charged — the exact
     * fact `38` Part 2 requires a before/after for. The alternatives were a
     * second log table (two places to look for one question) or adding
     * `PlanOffers` to the chokepoint lint that names this class as the only
     * writer of {@see RegistryChange} (a lint weakened to save an import).
     *
     * ⚠️ **THE CALLER SUPPLIES BOTH SIDES**, because only it knows what the row
     * said before — this class cannot read a table it knows nothing about, and
     * pretending otherwise would put a `plan_offers` query in the registry.
     *
     * @param  string  $subject  Dotted and area-first, `platform_settings`' own
     *                           convention, so one screen can render both.
     */
    public function recordPlatformChange(string $subject, mixed $before, mixed $after, string $actor): RegistryChange
    {
        return RegistryChange::create([
            'setting_key' => $subject,
            'value_before' => $before,
            'value_after' => $after,
            'actor' => $actor,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Put a platform-wide value back to its manifest seed.
     *
     * `38` Part 2's "Reset to seed" chip, "the D-137 pattern, applied to
     * ourselves". It writes the seed rather than deleting the row, so the change
     * log records the reset as the change it is; deleting would leave the value
     * correct and the history reading as though nothing happened.
     *
     * @throws InvalidArgumentException when $key is declared without a seed —
     *                                  there is nothing to reset it to
     */
    public function resetToSeed(string $key, string $actor): PlatformSetting
    {
        $settings = DefaultsManifest::settings();

        if (! array_key_exists($key, $settings)) {
            $this->assertDeclared($key);

            throw new InvalidArgumentException(
                "`{$key}` is declared without a seed, so there is no seed to reset it to. "
                .'Clearing it means removing the row, which is a deliberate act rather '
                .'than a reset.'
            );
        }

        return $this->set($key, $settings[$key]['seed'], $actor);
    }

    /**
     * The current value of a per-plan key.
     *
     * @throws WithheldRegistryValue when the owner has not set this figure
     * @throws InvalidArgumentException when the manifest does not declare it
     */
    public function entitlement(Plan $plan, string $key): mixed
    {
        $path = self::entitlementPath($plan, $key);

        $withheld = DefaultsManifest::withheld();

        // Checked BEFORE the stored row, and that ordering is the guard. A
        // withheld figure that somebody wrote into the table by hand is exactly
        // the guess the manifest exists to refuse — the number being in the
        // database is not evidence the owner set it.
        if (array_key_exists($path, $withheld)) {
            throw WithheldRegistryValue::for($path, $withheld[$path]);
        }

        $seeds = DefaultsManifest::entitlements();

        if (! array_key_exists($plan->value, $seeds) || ! array_key_exists($key, $seeds[$plan->value])) {
            throw new InvalidArgumentException(
                "`{$path}` is not declared in DefaultsManifest. Either it is a typo, or a "
                .'new registry value that belongs in the manifest before it is read.'
            );
        }

        $current = PlanEntitlement::current($plan, $key);

        return $current === null || $current->value === null
            ? $seeds[$plan->value][$key]['seed']
            : $current->value;
    }

    /**
     * Every declared value for one plan, in one query.
     *
     * ⚠️ **EXISTS BECAUSE THE PER-KEY ACCESSOR WAS AN N+1 ON THE ONE PAGE WITH A
     * PERFORMANCE GATE** (decision 519). The marketing home reads five prices
     * and each `entitlement()` is its own SELECT, so the LCP-gated page picked up
     * ten queries it did not need. Withheld figures cannot appear here for the
     * same reason they cannot appear anywhere: they have no seed, so there is
     * nothing to merge and nothing to return.
     *
     * @return array<string, mixed>
     */
    public function entitlementsFor(Plan $plan): array
    {
        $seeds = DefaultsManifest::entitlements()[$plan->value] ?? [];

        $values = [];

        foreach ($seeds as $key => $declared) {
            $values[$key] = $declared['seed'];
        }

        foreach (PlanEntitlement::currentFor($plan) as $key => $row) {
            // A stored key the manifest no longer declares is ignored rather
            // than returned. Removing a key from the manifest is how a setting
            // is retired, and the rows stay because this table is append-only —
            // returning them would make a retired value look live.
            if (! array_key_exists($key, $values) || $row->value === null) {
                continue;
            }

            $values[$key] = $row->value;
        }

        return $values;
    }

    /**
     * The current value of a per-plan key, as integer cents.
     *
     * Every money figure in this system is integer cents plus a currency code
     * (`18` §Money handling), and this is the accessor that keeps a float from
     * ever appearing.
     */
    public function entitlementCents(Plan $plan, string $key): int
    {
        $value = $this->entitlement($plan, $key);

        if (! is_int($value)) {
            throw new InvalidArgumentException(
                'A money value read as '.get_debug_type($value).' for `'
                .self::entitlementPath($plan, $key).'`. Money is integer cents here; a '
                .'string or a float in this column is a corrupted row, not a value to coerce.'
            );
        }

        return $value;
    }

    /**
     * Write the next version of a per-plan value.
     *
     * Never an update — see PlanEntitlement. `$effectiveAt` defaults to now,
     * because that is what an operator editing a value means; a price that takes
     * effect at the next cycle is a deliberate act with a date.
     */
    public function setEntitlement(
        Plan $plan,
        string $key,
        mixed $value,
        string $actor,
        ?Carbon $effectiveAt = null,
    ): PlanEntitlement {
        $path = self::entitlementPath($plan, $key);

        $withheld = DefaultsManifest::withheld();

        if (array_key_exists($path, $withheld)) {
            throw WithheldRegistryValue::for($path, $withheld[$path]);
        }

        $current = PlanEntitlement::current($plan, $key);

        return PlanEntitlement::create([
            'plan' => $plan->value,
            'key' => $key,
            'value' => $value,
            'version' => $current === null ? 1 : $current->version + 1,
            'effective_at' => $effectiveAt ?? Carbon::now(),
            'set_by' => $actor,
        ]);
    }

    /**
     * Registry paths grouped for the Ops editor, seed and current value side by
     * side (`38` Part 2: "shows seed vs current with a per-key Reset to seed
     * chip").
     *
     * ⛔ **`isBoolean` IS THE MANIFEST'S ANSWER AND NEVER THE STORED ROW'S**
     * (5880). It is what tells the Ops editor to render a switch rather than a
     * text box, and deriving it from the *current* value would make it lie
     * exactly when it matters: a key whose row already holds the string `'True'`
     * would report itself non-boolean and be offered as free text again, so the
     * one row already carrying the defect is the one row the fix would miss. The
     * seed is the reviewed statement of what the key is; the stored value is
     * whatever somebody managed to put there.
     *
     * ⚠️ **A KEY WITH NO SEED IS NOT A BOOLEAN HERE, WHATEVER IT MEANS.**
     * {@see DefaultsManifest::declaredWithoutSeed()} keys are the ones nobody
     * may invent a value for, and inventing a *type* for one is the same act one
     * step earlier — a switch rendered for a key with no reviewed default offers
     * a press that writes a figure the manifest deliberately withholds.
     *
     * @return array<string, list<array{key: string, seed: mixed, current: mixed, description: string, isSeeded: bool, isBoolean: bool}>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach (DefaultsManifest::settings() as $key => $declared) {
            $grouped[$declared['group']][] = [
                'key' => $key,
                'seed' => $declared['seed'],
                'current' => $this->value($key),
                'description' => $declared['description'],
                'isSeeded' => true,
                'isBoolean' => is_bool($declared['seed']),
            ];
        }

        foreach (DefaultsManifest::declaredWithoutSeed() as $key => $reason) {
            // Grouped under one heading rather than guessed from the key's
            // prefix: these are the keys whose absence is correct, and an
            // operator scanning for something to change should meet them
            // together with the reason they are empty.
            $grouped['Set by an operator only'][] = [
                'key' => $key,
                'seed' => null,
                'current' => PlatformSetting::read($key, null),
                'description' => $reason,
                'isSeeded' => false,
                'isBoolean' => false,
            ];
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * The change history for one platform-wide key, newest first.
     *
     * Ordered by `id`, which is the only descending sort this codebase permits
     * without saying NULLS LAST — and here it is also the correct one, because
     * two changes within the same second have an order and `created_at` cannot
     * express it.
     *
     * @return list<RegistryChange>
     */
    public function historyFor(string $key, int $limit = 20): array
    {
        return array_values(
            RegistryChange::query()
                ->where('setting_key', $key)
                ->latest('id')
                ->limit($limit)
                ->get()
                ->all()
        );
    }

    /**
     * The change history by whoever made it — `28` §14.1's audit explorer.
     *
     * ⚠️ **This lives here rather than in `AuditExplorer` because the lint says
     * it must.** *"Only the defaults registry reads or writes a registry
     * store"* names reading, not only writing, and `ArchitectureTest` enforces
     * it — so the explorer adding a `RegistryChange::query()` of its own would
     * have been that lint weakened by a screen rather than a decision. The
     * reason on the test still holds: these tables carry no RLS, because they
     * are our prices and our caps, so the chokepoint is the whole protection.
     *
     * A null `$actor` is everyone, which is the question an incident asks.
     *
     * ⚠️ **`registry_changes` is `platform_settings`' history and not
     * `plan_entitlements`'.** That table versions instead (507), so a price a
     * subscriber signed up under survives somebody editing the current one —
     * and a reader looking here for a plan-price edit finds nothing and must
     * not conclude none happened.
     *
     * @return LengthAwarePaginator<int, RegistryChange>
     */
    public function changesBy(?string $actor, int $perPage, string $pageName = 'page'): LengthAwarePaginator
    {
        $query = RegistryChange::query();

        if ($actor !== null) {
            $query->where('actor', $actor);
        }

        // `id` for the reason historyFor() gives: two changes in one second
        // have an order and `created_at` cannot express it — and it is the one
        // descending sort decision 289's lint permits without NULLS LAST.
        return $query->orderByDesc('id')->paginate($perPage, pageName: $pageName);
    }

    /**
     * The manifest path a per-plan key is named by, and the form
     * {@see DefaultsManifest::withheld()} uses.
     */
    public static function entitlementPath(Plan $plan, string $key): string
    {
        return "plan.{$plan->value}.{$key}";
    }

    /**
     * Refuse a platform-wide key the owner has not set.
     *
     * ⚠️ **`withheld()` USED TO REACH ONLY THE PER-PLAN PATHS, AND NOTHING SAID
     * SO** (T176 P21). Every key in it was a `plan.…` entitlement, `entitlement()`
     * and `setEntitlement()` checked it, and `value()` did not — so a withheld
     * *platform* key would have raised `InvalidArgumentException` reading "not
     * declared in DefaultsManifest", which sends the reader to look for a typo
     * rather than to the decision that left the figure open. The gap had no
     * occupant until `mail.postal_address`; closing it is what makes that key's
     * fail-closed behaviour a property of the registry rather than of one
     * caller remembering to ask.
     *
     * @throws WithheldRegistryValue
     */
    private function assertNotWithheld(string $key): void
    {
        $withheld = DefaultsManifest::withheld();

        if (array_key_exists($key, $withheld)) {
            throw WithheldRegistryValue::for($key, $withheld[$key]);
        }
    }

    /**
     * Refuse a value that is only safe once something else is true (4505).
     *
     * ⛔ **BOTH MEMBERS ARE HERE BECAUSE THIS IS THE ONLY DOOR.** An
     * `ArchitectureTest` lint holds *"only the defaults registry reads or writes
     * a registry store"*, so a precondition attached anywhere else — an Ops
     * screen, a command, a service — is a precondition the next writer routes
     * around without noticing.
     *
     * **`voice.enabled` (4505).** Call recording cannot be switched on until an
     * operator has recorded that every caller hears the announcement first:
     * `29` §2 and 2104 make it unconditional in every state, and the penalty for
     * a recorded call without one lands on the tenant. ⚠️ **Only the true
     * direction is gated.** Turning recording *off*, or resetting to the seed of
     * `false`, must never be blocked by a missing attestation — a switch you
     * cannot turn off in an incident is worse than the thing it was protecting
     * against.
     *
     * **`legal.sms_ask_footer` (5173).** The footer composed onto every
     * review-ask text may be reworded and may not be emptied.
     * `MissedCallTextBack::compose()` refuses to read its own footer from a
     * registry key at all, in as many words — *"a wording an owner can edit is a
     * wording an owner can edit the disclosure out of"* — and this is that
     * objection answered rather than overruled: `LegalCanon` refuses any value
     * that drops the lane disclosure or the opt-out instruction. ⚠️ **Every
     * direction is gated here, and the asymmetry with `voice.enabled` above is
     * the point.** There is no safe direction for a compliance sentence: an
     * empty footer is the damage rather than the absence of it, and the way back
     * from a mistake is {@see self::resetToSeed()}, which writes the reviewed
     * value and never reaches this method.
     *
     * **`ops.alert_quiet_minutes` (7580-7599).** The only mute the whole pager
     * has, and it had no ceiling: `1051200` — two years — passed the two guards
     * above and the Ops screen's parser untouched, and for that long the same
     * `(kind, subject)` recovering and breaking again would have told nobody.
     * {@see OperatorAlerts::MIN_QUIET_MINUTES} carries the full argument,
     * including the three reasons in the tree for **not** bounding it and what
     * beats each. ⚠️ **Both directions are gated, and unlike the footer above
     * that is not because there is no safe direction** — it is because there is
     * no safe *edge*: zero rings on every sweep and two years rings never.
     *
     * **`ops.vendor_error_rate_bp` (7580-7599).** The same shape found by
     * looking: `999999` basis points is a rate no vendor can reach, so it
     * switched the vendor check off while the row read as configured.
     * {@see PlatformHealthChecks::MAX_ERROR_RATE_BP} carries it, including why
     * `ops.health_window_minutes` was looked at and deliberately left alone.
     *
     * **`pixel.canary_halt_regression_bp` (7740–7759).** The one key in this
     * application where the platform's own *"set that check's threshold to zero
     * to switch it off"* convention (2409) is not merely absent but **inverted**:
     * zero halts a pixel release on a single basis point of regression, and
     * `PixelDelivery::haltCanary()` rolls the release back rather than ringing
     * and continuing. {@see WatchPixelCanary::MIN_REGRESSION_BP} carries the
     * argument for refusing a zero at the write while the read goes on honouring
     * one, which is a different division of 7589's split than the two keys above
     * make.
     *
     * ⚠️ **A GENERIC MAP IS STILL REFUSED, ON 256's TERMS, AND THE ARGUMENT HAS
     * MOVED TWICE RATHER THAN SURVIVED UNCHANGED.** It read: *"a
     * `preconditions()` table keyed by setting would be a mechanism sized for a
     * third member that does not exist, and the two that do exist share no shape
     * at all."* There are **five** members now and the *"no third member"* half
     * is long spent — but the conclusion holds for the reason underneath it. One
     * is a boolean gated in one direction against an external attestation; one is
     * a string gated in every direction against its own contents; and the three
     * integers, which do look alike, have derivations with nothing in common — a
     * **judgement** about how long a pager may be off, **arithmetic** about what
     * a percentage is, and a **consequence** that is a rollback rather than a
     * bell. ⛔ **A `min`/`max` slot on the manifest was the other candidate and is
     * the same refusal one level up**: around two hundred entries would gain two
     * fields that three of them need, and the two-word schema would carry none of
     * the argument that makes any of the figures defensible — nor the asymmetry
     * that the newest member turns on, where the refused range and the clamped
     * range deliberately differ by one. **Five named keys, each delegating to the
     * class that owns the rule, still beat a framework.** ⚠️ **What would settle
     * it the other way is a member whose bound is arithmetic with no argument
     * attached**, and none of the five is that yet.
     *
     * ⛔ **AND TWO KEYS WERE LOOKED AT IN THE SAME SLICE AND DELIBERATELY LEFT
     * OUT**, which is the evidence that this is a per-key rule rather than a
     * framework short of members. `pixel.canary_min_pageviews` is corrected at the
     * read to one and has no consequence to refuse —
     * `ops.health_window_minutes`' reasoning exactly. And
     * `voice.tenant_daily_inbound_minutes_ceiling` has **no honest upper bound**:
     * a tenant with several numbers can take more inbound minutes in a day than
     * the day has, so there is no arithmetic maximum to refuse, and its zero is a
     * documented emergency brake rather than a mistake.
     *
     * @throws RecordingAnnouncementNotAttested
     * @throws InvalidArgumentException when a review-ask footer drops the lane
     *                                  disclosure or the opt-out instruction, or
     *                                  when a bounded figure is outside its range
     */
    private function assertPreconditionsMet(string $key, mixed $value): void
    {
        if ($key === LegalCanon::ASK_FOOTER_KEY) {
            LegalCanon::refuseIncompleteAskFooter($value);

            return;
        }

        if ($key === OperatorAlerts::QUIET_KEY) {
            OperatorAlerts::refuseUnworkableQuietWindow($value);

            return;
        }

        if ($key === PlatformHealthChecks::VENDOR_RATE_KEY) {
            PlatformHealthChecks::refuseUnreachableErrorRate($value);

            return;
        }

        if ($key === WatchPixelCanary::THRESHOLD_KEY) {
            WatchPixelCanary::refuseUnworkableHaltThreshold($value);

            return;
        }

        if ($key !== RecordingAnnouncement::SWITCH_KEY || $value !== true) {
            return;
        }

        if (app(RecordingAnnouncement::class)->isAttested()) {
            return;
        }

        throw RecordingAnnouncementNotAttested::forSwitch($key);
    }

    /**
     * @throws InvalidArgumentException when the manifest declares neither a seed
     *                                  nor an explicit no-seed entry for $key
     */
    private function assertDeclared(string $key): void
    {
        if (array_key_exists($key, DefaultsManifest::settings())) {
            return;
        }

        if (array_key_exists($key, DefaultsManifest::declaredWithoutSeed())) {
            return;
        }

        throw new InvalidArgumentException(
            "`{$key}` is not declared in DefaultsManifest. The registry answers only for "
            .'keys the manifest names — a key it has never heard of is a typo far more '
            .'often than it is a new setting, and returning a fallback for one is how a '
            .'misspelled budget resolves to a working number.'
        );
    }
}

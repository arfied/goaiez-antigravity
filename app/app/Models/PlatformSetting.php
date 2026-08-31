<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One platform-wide operational number (DATA-MODEL §5.12).
 *
 * Ours, not a tenant's — so it is on the `TenancyTest` allowlist as
 * platform-wide reference data. A nullable `business_id` on this table would
 * create a second per-tenant store that no global scope covers, which is why a
 * per-business override does not belong here.
 *
 * ⛔ **THAT SENTENCE USED TO CONTINUE "`feature_flags` already carries
 * `(flag_key, business_id NULL)` for that", AND THERE HAS NEVER BEEN SUCH A
 * TABLE — CORRECTED 2026-08-23 (8410).** ⚠️ **"Not here" therefore does not
 * mean "there"; it means nowhere.** There is no generic per-business override
 * of a registry key anywhere in this application, under any name:
 * `DefaultsRegistry` reads by `key` or by `(Plan, key)` and has **no business
 * dimension at all**. What this application uses instead is a typed column on a
 * per-business table (`autopilot_settings`, `support_settings`,
 * `review_destinations.invite_threshold`) — one migration per setting, on
 * purpose. **The creating migration carries the full correction**; the absence
 * is pinned by `tests/Feature/PlatformSettingTest.php` rather than claimed
 * here, so it reddens the day somebody builds one.
 *
 * READS FAIL CLOSED, AND THAT IS THE WHOLE DESIGN. There is no `get()` without a
 * default. The first user of this table is the free audit's daily budget
 * (decision 193, 250 audits/day), and a budget that reads `null` and treats it
 * as "no limit" is how an unauthenticated endpoint spends real money on someone
 * else's meter (BUILD-PLAN §6). So this class never invents permission: it
 * returns what the caller asked to fall back to.
 *
 * ⚠️ **THE CALLER IS NOW ALWAYS `DefaultsRegistry`, AND THIS DOCBLOCK USED TO
 * SAY OTHERWISE.** It read "every caller passes its own default, and the default
 * a caller passes must be the conservative one", which was right when the
 * alternative was a bare `?? null` — and wrong once doc `38`'s CFG1 landed
 * (decision 505). A default at a call site is a hardcoded threshold literal,
 * which is what `38` Part 2's registry lint refuses, and two call sites reading
 * one key can disagree about policy with whichever runs first winning silently.
 *
 * The property is moved rather than dropped: the conservative default is the
 * seed in `App\Support\DefaultsManifest`, written once and reviewed. An
 * `ArchitectureTest` lint holds the chokepoint.
 *
 * NOT THE DEFAULTS REGISTRY ITSELF. `38` Part 8 puts the registry in this table
 * plus `plan_entitlements` plus the manifest file — "no separate table needed" —
 * so CFG1 is the seeding authority for the rows in here and does not replace the
 * table. `defaults:sync` writes what is missing and never overwrites a value
 * somebody set. Note that `38`'s own seed manifest still carries the superseded
 * Free/49/149/349 prices (decision 204) and was **not** shipped as written; the
 * live figures are rebuilt from `CLAUDE.md` (decision 501).
 *
 * @property string $key
 * @property mixed $value
 * @property ?string $description
 * @property ?string $updated_by
 * @property ?Carbon $updated_at
 */
final class PlatformSetting extends Model
{
    /**
     * The key a caller already holds is the identifier; there is no surrogate
     * one. See the creating migration for why decision 179 does not apply.
     */
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * A setting's interesting timestamp is when its value last moved, so the
     * table carries `updated_at` alone (DATA-MODEL §5.12).
     */
    public const null CREATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The value stored under $key, or $default when no row exists.
     *
     * $default is required rather than optional on purpose — see the class
     * docblock. A caller that cannot name a safe fallback does not have a
     * setting, it has a dependency, and should fail rather than guess.
     */
    public static function read(string $key, mixed $default): mixed
    {
        $setting = self::query()->find($key);

        if ($setting === null) {
            return $default;
        }

        // A row whose value is JSON null also falls back. Fail closed: an
        // operator who blanked a budget meant to remove a number, not to remove
        // a limit.
        return $setting->value ?? $default;
    }

    /**
     * Several keys in one query, each falling back to its own default.
     *
     * ⚠️ **IT EXISTS FOR ONE PAGE AND THAT PAGE HAS A GATE ON IT** (decision
     * 5213). The marketing home is measured against `29` §11.2 row 1's LCP budget
     * and `DefaultsRegistryTest`'s query-count lint, whose own comment sets the
     * rule this follows: *"the number moves by exactly the one query the feature
     * genuinely adds, so this stays a lint that can fail tomorrow rather than a
     * ceiling with room in it"* (511, 4337). CC-2 gave that page a second
     * platform-setting key, and two keys on one tiny table is one `whereIn`
     * rather than two `find`s — so the budget did not have to move at all.
     *
     * ⚠️ **THE FALLBACK RULE IS `read()`'s, KEY BY KEY.** A missing row and a row
     * whose value is JSON null both fall back, because an operator who blanked a
     * budget meant to remove a number and not to remove a limit.
     *
     * ⛔ **IT IS NOT A CACHE.** 510 keeps the registry uncached on purpose — a
     * value written in one place and read through a warm cache in another is a
     * staleness bug no test sees. This holds nothing beyond the call.
     *
     * @param  array<string, mixed>  $defaults  key => the value to fall back to
     * @return array<string, mixed>
     */
    public static function readMany(array $defaults): array
    {
        if ($defaults === []) {
            return [];
        }

        $stored = self::query()
            ->whereIn('key', array_keys($defaults))
            ->get()
            ->keyBy('key');

        $values = [];

        foreach ($defaults as $key => $default) {
            $setting = $stored->get($key);

            $values[$key] = $setting instanceof self ? $setting->value ?? $default : $default;
        }

        return $values;
    }

    /**
     * The value stored under $key as an integer, or $default.
     *
     * Numbers arrive from jsonb, where a value written as `250` reads back as
     * an int but one edited by hand to `"250"` reads back as a string. Callers
     * comparing a budget against a count should not have to care, and a
     * silently-string budget compared with `>=` is the kind of bug that only
     * appears once someone edits the row.
     */
    public static function readInt(string $key, int $default): int
    {
        $value = self::read($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * The value stored under $key as a float, or $default.
     *
     * {@see self::readInt()}'s twin, for the weights and percentages doc `51`
     * §1 states with a decimal point. `is_numeric` accepts a whole-number seed
     * such as `3` as well as `0.45`, since jsonb round-trips an integer-valued
     * float back as a PHP int and refusing that would make a whole-number
     * weight unreadable through this accessor for no reason.
     */
    public static function readFloat(string $key, float $default): float
    {
        $value = self::read($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    /**
     * Write a value, recording who moved it.
     *
     * $updatedBy is an actor label rather than a user id — a seeder, a console
     * command and a staff member all write here, and only one of those has one.
     */
    public static function write(
        string $key,
        mixed $value,
        string $updatedBy,
        ?string $description = null,
    ): self {
        $attributes = [
            'value' => $value,
            'updated_by' => $updatedBy,
            'updated_at' => Carbon::now(),
        ];

        if ($description !== null) {
            $attributes['description'] = $description;
        }

        return self::query()->updateOrCreate(['key' => $key], $attributes);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'updated_at' => 'datetime',
        ];
    }
}

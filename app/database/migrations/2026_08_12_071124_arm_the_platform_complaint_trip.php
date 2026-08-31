<?php

declare(strict_types=1);

use App\Models\PlatformSetting;
use App\Models\RegistryChange;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Database\Migrations\Migration;

/**
 * Arm the automatic platform halt on an install that already stored `0`
 * — decision 2684, and the half a seed change cannot reach (2862).
 *
 * ⛔ **CHANGING `DefaultsManifest` IS NOT ENOUGH, AND BELIEVING OTHERWISE IS
 * `CLAUDE.md`'S SEVENTEENTH WRITERLESS SHAPE.** `SyncDefaultsRegistry` is
 * deliberately never-overwrite — *"a sync that reset an operator's edited budget
 * on every deploy would make the Ops screen a lie"* — so every install that has
 * run `composer deploy` since 2400–2410 already holds a `platform_settings` row
 * for these two keys with the value `0`. The seed is only consulted when there
 * is no row. **On those installs the owner's ruling would land as a manifest
 * edit that changed nothing**, the screen would go on saying the platform will
 * not stop itself, and it would be right.
 *
 * ⚠️ **IT MOVES ONLY THE SHIPPED-DISARMED STATE, NEVER A CHOSEN ZERO.** A row is
 * touched only when its value is exactly `0` *and* nothing but `defaults:sync`
 * has ever written it — so an operator who deliberately disarmed the trip keeps
 * their decision, and finds it where they left it. That check is the reason this
 * is a migration rather than a `defaults:sync --force`, which could not tell the
 * two zeroes apart.
 *
 * It goes through {@see DefaultsRegistry::resetToSeed()} rather than writing the
 * column, so the arming lands in `registry_changes` with before, after and an
 * actor — the same evidence any operator edit leaves. A safety control that
 * turned itself on with no trace is the mirror of one that turned itself off
 * with no trace.
 */
return new class extends Migration
{
    /**
     * The two keys 2409 shipped at zero and 2684 arms.
     *
     * @var list<string>
     */
    private const KEYS = [
        'messaging.platform_complaint_trip_bp',
        'messaging.platform_complaint_min_delivered',
    ];

    /**
     * The actor `SyncDefaultsRegistry` records against a row nobody typed.
     *
     * Spelled out rather than referenced through the command's own constant: a
     * migration is a historical record and must keep meaning what it meant on
     * the day it ran, even if that constant is later renamed.
     */
    private const SEED_ACTOR = 'defaults:sync';

    private const ACTOR = 'migration:2684-arm-platform-trip';

    public function up(): void
    {
        $registry = app(DefaultsRegistry::class);

        foreach (self::KEYS as $key) {
            $existing = PlatformSetting::query()->find($key);

            if ($existing === null) {
                // No row at all: the manifest seed is already what every reader
                // resolves to, so there is nothing to correct and writing one
                // would only add a change-log row saying nothing happened.
                continue;
            }

            if ($existing->value !== 0) {
                continue;
            }

            $chosenByAPerson = RegistryChange::query()
                ->where('setting_key', $key)
                ->where('actor', '!=', self::SEED_ACTOR)
                ->exists();

            if ($chosenByAPerson) {
                // Somebody decided this. ⚠️ Including somebody who decided to
                // put it back to zero after an alert — 511's failure is exactly
                // the shape a migration must not quietly undo.
                continue;
            }

            $registry->resetToSeed($key, self::ACTOR);
        }
    }

    /**
     * ⛔ **DELIBERATELY IRREVERSIBLE, AND NOT OUT OF LAZINESS.**
     *
     * The inverse of "arm the containment 2113 calls a precondition of sending
     * at all" is "disarm it", and a rollback that silently stopped the platform
     * protecting itself is the worst thing in this file. Disarming is an
     * operator's act on the sending-controls screen, with an actor against it.
     */
    public function down(): void {}
};

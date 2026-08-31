<?php

declare(strict_types=1);

use App\Models\PlatformSetting;
use App\Models\RegistryChange;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Database\Migrations\Migration;

/**
 * Point `campaigns.overlay_font_path` at the font this application now ships —
 * decisions 4365–4368, and the half a seed change cannot reach (4480–4483).
 *
 * ⛔ **CHANGING `DefaultsManifest` IS NOT ENOUGH, AND BELIEVING OTHERWISE IS
 * `CLAUDE.md`'S SEVENTEENTH WRITERLESS SHAPE.** This is the second time in five
 * days, and `2026_08_12_071124_arm_the_platform_complaint_trip.php` is the first
 * — it is the file this one is modelled on, and its own docblock says that same
 * sentence in capitals. `SyncDefaultsRegistry` is deliberately never-overwrite:
 * where a row exists it refreshes the *description* and `continue`s. `composer
 * deploy` runs `defaults:sync`, and production has run it — so
 * `platform_settings` already holds this key with
 * `/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf`, and the manifest seed
 * is consulted only where there is no row.
 *
 * ⚠️ **WHAT A SEED-ONLY CHANGE SHIPS IS WORSE THAN AN UNFIXED DEFECT.** On every
 * deployed install `CampaignMedia::fontPath()` would go on reading the DejaVu
 * path, `is_readable()` would go on being false, and every personalised MMS
 * would go on degrading to SMS — the exact defect 4366 was raised to fix,
 * unchanged. And because `defaults:sync` *does* refresh the description, the Ops
 * screen would print *"Ships with the application — Public Sans Bold… A relative
 * path is resolved against the application root"* **beside the DejaVu value**:
 * the screen actively asserting that the fix had landed. ⚠️ **The suite cannot
 * see any of it** — `RefreshDatabase` gives a row-less registry, so every test
 * resolves the manifest seed and passes.
 *
 * ⚠️ **IT MOVES ONLY THE SHIPPED-DEFAULT VALUE, NEVER A CHOSEN FACE.** A row is
 * touched only when its value is **exactly** the old DejaVu literal *and*
 * nothing but `defaults:sync` has ever written it. An operator who pointed this
 * at their own brand face keeps it; so does an operator who deliberately typed
 * the DejaVu path themselves, because the change log can tell those two values
 * apart and a `defaults:sync --force` could not. That check is the whole reason
 * this is a migration.
 *
 * ⚠️ **THE OLD VALUE IS SPELLED OUT HERE RATHER THAN IMPORTED.** A migration is
 * a historical record and must keep meaning what it meant on the day it ran; the
 * literal it matches no longer appears anywhere else in the application, and a
 * shared constant would be one with a single reader whose only job is to make
 * this file stop working when somebody deletes it.
 *
 * It goes through {@see DefaultsRegistry::resetToSeed()} rather than writing the
 * column, so the correction lands in `registry_changes` with before, after and
 * an actor — the same evidence any operator edit leaves. ⚠️ **This key is not a
 * safety control**, so the argument is weaker than 2862's and still holds: an
 * operator who opens the settings screen and finds a value they did not type is
 * owed a row saying who did.
 */
return new class extends Migration
{
    private const string KEY = 'campaigns.overlay_font_path';

    /**
     * The value 4365 replaced — absent on the cPanel production box, which is
     * what made the whole personalised-picture pipeline inert there.
     */
    private const string SHIPPED_DEFAULT = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    /**
     * The actor `SyncDefaultsRegistry` records against a row nobody typed.
     *
     * Spelled out rather than referenced through the command's own constant, for
     * the reason the class docblock gives about the DejaVu literal.
     */
    private const string SEED_ACTOR = 'defaults:sync';

    private const string ACTOR = 'migration:4365-ship-the-overlay-font';

    public function up(): void
    {
        $existing = PlatformSetting::query()->find(self::KEY);

        if ($existing === null) {
            // No row at all: the manifest seed is already what every reader
            // resolves to, so there is nothing to correct and writing one would
            // only add a change-log row saying nothing happened. This is the
            // branch every test run and every fresh install takes.
            return;
        }

        if ($existing->value !== self::SHIPPED_DEFAULT) {
            return;
        }

        $chosenByAPerson = RegistryChange::query()
            ->where('setting_key', self::KEY)
            ->where('actor', '!=', self::SEED_ACTOR)
            ->exists();

        if ($chosenByAPerson) {
            // Somebody decided this — including somebody who decided to put it
            // back to DejaVu after trying the shipped face. 511's failure is
            // exactly the shape a migration must not quietly undo.
            return;
        }

        app(DefaultsRegistry::class)->resetToSeed(self::KEY, self::ACTOR);
    }

    /**
     * ⛔ **DELIBERATELY IRREVERSIBLE.**
     *
     * The inverse of "point at a font that exists" is "point at one that does
     * not", and a rollback that silently switched the picture pipeline back off
     * everywhere is the worst thing in this file. Pointing the key elsewhere is
     * an operator's act on the settings screen, with an actor against it.
     */
    public function down(): void {}
};

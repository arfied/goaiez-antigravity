<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Livewire\Admin\PlatformSettings;
use App\Livewire\Admin\SendingControls;
use App\Services\Config\DefaultsRegistry;
use App\Services\Messaging\SendingGuard;
use App\Services\Voice\RecordingAnnouncement;

/**
 * The registry keys the generic settings editor may show and may not move
 * (5900).
 *
 * ## ⛔ WHAT THIS CLOSES, AND WHY IT IS A SHAPE RATHER THAN A KEY
 *
 * 5880 made every boolean-seeded registry key a switch in
 * {@see PlatformSettings}, which was the right fix for a boolean that could be
 * saved as the string `'True'`. **`messaging.global_halt` is a boolean.** So the
 * platform-wide stop acquired a second door — one press, either direction, no
 * guard, no ordering, no confirmation — beside the one
 * {@see SendingControls} had been given deliberately.
 *
 * ⚠️ **THE DEFECT IS NOT THAT SOMEBODY FORGOT A KEY. IT IS THAT A GENERIC
 * SURFACE GAINED A CAPABILITY AUTOMATICALLY**, over a set derived from the
 * manifest, and the set silently included switches that had been argued about
 * somewhere else. Nothing about that is specific to booleans and it will happen
 * again the next time something here is made generic — the next derived set will
 * be *every integer*, or *every key in a group*, and it will sweep up whatever
 * has ceremony at the time. **The registration below is what a derived set has
 * to be subtracted from**, and a lint keeps it subtracted.
 *
 * ## Refuse and point, rather than hide or replicate
 *
 * ⚠️ **THE VALUE STAYS VISIBLE AND ONLY THE CONTROL GOES.** An operator asking
 * *"is the platform halted"* has to be able to get an answer from the settings
 * screen — 2402 records that the registry key **is** the switch and that no
 * second row anywhere answers that question — so hiding these rows would trade a
 * duplicate control for a missing fact, which is the worse half.
 *
 * ⛔ **AND THE CEREMONY IS NOT COPIED HERE.** Two implementations of one
 * confirmation is the duplicate-guard shape this codebase keeps finding, and
 * they drift: the release on the sending screen clears **both** halt keys
 * (3980–3983), so a second copy that cleared one would leave a machine halt
 * standing while the screen that wrote it read "running".
 *
 * ## What is deliberately NOT registered
 *
 * ⚠️ **`voice.enabled` IS NOT HERE, AND THE DIFFERENCE IS WHERE THE GUARD
 * LIVES.** Its precondition is enforced inside {@see DefaultsRegistry::set()},
 * so it rides *every* door and the generic editor already honours it (4514).
 * `legal.sms_ask_footer`'s shape refusal is the same pattern. **A guard on the
 * write path does not need a registration; a guard on a screen does.** That is
 * the test for whether the next key belongs here.
 *
 * ⚠️ **THE SITE-WRITE SWITCH IS NOT HERE EITHER, AND IT IS THE THIRD ANSWER.**
 * Its two-step confirmation (5883) lives *inside* {@see PlatformSettings}, so
 * registering it would tell the editor to refuse the door it already is. It is
 * named nowhere in this file on purpose — a lint keeps that key scarce (5892).
 */
final class OperatedElsewhere
{
    /**
     * Every key with a door of its own, and where that door is.
     *
     * @return array<string, array{door: string, route: string|null, why: string}>
     */
    public static function all(): array
    {
        return [
            SendingGuard::OPERATOR_HALT_KEY => [
                'door' => 'Ops → Platform → Stop and start sending',
                'route' => 'admin.sending-controls',
                'why' => 'There, stopping everyone is one press and starting again asks first — '
                    .'and starting again also clears an automatic stop, which a switch here would leave standing.',
            ],
            SendingGuard::AUTOMATIC_HALT_KEY => [
                'door' => 'Ops → Platform → Stop and start sending',
                'route' => 'admin.sending-controls',
                'why' => 'Only the complaint-rate watch turns this on. A person turning it on here would be '
                    .'signing a measurement nobody took; starting sending again on that screen is how it comes off.',
            ],
            RecordingAnnouncement::SETTING_KEY => [
                'door' => 'php artisan voice:announcement-attestation record',
                'route' => null,
                'why' => 'That command shows an operator the statement and stores what they answered, against the '
                    .'clip they named. Typing here would not make an attestation — it would destroy the one in force.',
            ],
        ];
    }

    /**
     * The door for one key, or null when the generic editor owns it.
     *
     * @return array{door: string, route: string|null, why: string}|null
     */
    public static function doorFor(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    /**
     * What an operator is told when they reach one of these from the editor.
     *
     * ⚠️ **IT NAMES THE DOOR AND THE CONSEQUENCE, NOT THE KEY** (`22`, outcome
     * language). Somebody who pressed a switch meant to stop or start something;
     * being told "that setting is protected" leaves them looking for the other
     * half themselves.
     */
    public static function refusal(string $key): string
    {
        $door = self::doorFor($key);

        if ($door === null) {
            // Unreachable through every caller, which asks first — asserted
            // rather than assumed, because a blank refusal reads as success.
            return 'That setting is operated somewhere else.';
        }

        return 'This one is operated on '.$door['door'].'. '.$door['why'];
    }
}

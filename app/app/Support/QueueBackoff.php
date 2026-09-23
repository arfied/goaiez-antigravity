<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Config\DefaultsRegistry;

/**
 * One retry ladder, spread out so that a synchronised failure does not produce a
 * synchronised retry.
 *
 * ⛔ **`29` §2 RULE 40 IS *"RETRIED WITH BACKOFF"* AND `CLAUDE.md` IS *"BACKOFF
 * AND JITTER"*, AND THE THREE ACTUATION JOBS HAD THE FIRST WITHOUT THE SECOND**
 * (6267). Every one of them declared `public array $backoff = [300, 1800]` — a
 * fixed ladder — so a customer's website that went down at three in the morning
 * would be asked again by every one of that tenant's queued measurements at the
 * *same instant*, five minutes later and again half an hour after that. On the
 * one path in `app/` that touches hardware we do not own, that is the burst
 * `OutboundSiteBudget` then has to refuse.
 *
 * ⚠️ **THE FORMULA IS `AutopilotJob`'s AND IS NOW ONLY HERE.** It was a private
 * method on the base class the 142 catalog automations extend, which the three
 * standalone actuation jobs do not; copying it would have been two numbers for
 * one rule, and the second copy is the one that drifts.
 */
final class QueueBackoff
{
    public const array STANDARD = [60, 300];

    public const array MEDIA = [60, 600];

    public const array ACTUATION = [300, 1800];

    public const array VOICE = [60, 300, 900];

    public const array PIXEL_ARCHIVE = [30, 120];

    /**
     * Reads a list of backoff delays from the registry.
     *
     * @return list<int>
     */
    public static function fromSetting(string $key): array
    {
        return app(DefaultsRegistry::class)->intList($key);
    }

    /**
     * A delay within ±25% of the one asked for.
     *
     * ⚠️ **±25% RATHER THAN A LARGER SPREAD.** Enough to break up a synchronised
     * failure and small enough that the shape of the ladder — five minutes, then
     * half an hour — still means what it says on the job that declares it.
     */
    public static function jittered(int $seconds): int
    {
        return (int) round($seconds * (0.75 + (random_int(0, 500) / 1000)));
    }

    /**
     * A whole ladder, jittered.
     *
     * @param  list<int>  $seconds
     * @return list<int>
     */
    public static function ladder(array $seconds): array
    {
        return array_map(static fn (int $step): int => self::jittered($step), $seconds);
    }
}

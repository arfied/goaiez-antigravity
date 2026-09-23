<?php

declare(strict_types=1);

namespace App\Jobs\Voice\Concerns;

use App\Support\QueueBackoff;

/**
 * `AutopilotJob`'s retry ladder, for the one job on this path that cannot
 * extend it.
 *
 * ⚠️ **JITTER MATTERS MORE THAN THE BASE DELAY** — `AutopilotJob`'s own words:
 * without it, a provider outage that fails hundreds of jobs at once retries all
 * of them in lockstep, which is a thundering herd aimed at a service already in
 * trouble. The ladder is deliberately the same one, so a voice event and a
 * review invite behave identically under the same outage.
 */
trait JitteredBackoff
{
    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return array_map(
            fn (int $step): int => $this->jittered($step),
            QueueBackoff::fromSetting('queue.backoff.voice_seconds')
        );
    }

    /**
     * ⚠️ **±25%, WHICH IS `AutopilotJob::jittered()`'S OWN SPREAD, COPIED
     * EXACTLY.** *"Enough to spread a synchronised failure without making the
     * backoff curve meaningless."* Two different spreads on one retry ladder
     * would be two behaviours under one outage.
     */
    private function jittered(int $seconds): int
    {
        return (int) round($seconds * (0.75 + (random_int(0, 500) / 1000)));
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RecordQueueHeartbeat;
use App\Services\Ops\PlatformHealth;
use App\Services\Ops\PlatformHealthChecks;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * "The scheduler ran, and here is a job to prove a worker ran too" (P23).
 *
 * ⛔ **THIS COMMAND RAISES NOTHING AND CHECKS NOTHING, AND THAT IS THE DESIGN.**
 * It only writes down that it happened. The failure being watched for is this
 * command *not running*, and a check inside it could never observe that — see
 * {@see PlatformHealthChecks} for where the observing lives
 * and why it lives in a different process.
 *
 * ⚠️ **TWO BEATS, ONE TICK, AND THEY MUST NOT BE COLLAPSED INTO ONE.** Writing
 * the queue's beat here as well would make it a record of the scheduler
 * *dispatching* rather than of a worker *running* — which is exactly the state a
 * platform with a dead worker is in, reported as healthy.
 *
 * Cheap enough to run every minute: two writes, no enumeration, no vendor call.
 */
#[AsCommand(name: 'ops:heartbeat')]
final class RecordOpsHeartbeat extends Command
{
    protected $description = 'Record that the scheduler ran, and dispatch the job that proves a worker is running';

    public function handle(PlatformHealth $health): int
    {
        $health->beat('scheduler');

        RecordQueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}

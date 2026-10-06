<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice\Live;

use App\Services\Ops\PlatformHealth;
use App\Services\Ops\PlatformHealthChecks;
use Illuminate\Http\JsonResponse;

/**
 * The voice worker says it is alive, every 30 seconds (AI receptionist plan, 2026-10-05). A beat is written under
 * `voice_worker`, and {@see PlatformHealthChecks::sweepVoiceWorker()} pages the operator when the beats stop while the
 * receptionist is switched on. It names no tenant and returns nothing a worker could act on.
 */
final class HeartbeatController
{
    public function __invoke(PlatformHealth $health): JsonResponse
    {
        $health->beat(PlatformHealthChecks::VOICE_WORKER);

        return response()->json(['status' => 'ok']);
    }
}

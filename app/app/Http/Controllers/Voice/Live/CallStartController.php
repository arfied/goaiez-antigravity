<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice\Live;

use App\Services\Config\DefaultsRegistry;
use Illuminate\Http\JsonResponse;

/**
 * The voice worker asks this at every ring: may the AI receptionist answer, and with what (plan 2026-10-05).
 *
 * Wave 1 is the skeleton: with `voice.live_agent.enabled` off it answers "disabled" — the worker then plays its fallback (take a
 * message) — and with the switch on it says plainly that call start is not built yet rather than pretending to answer. Wave 2
 * builds the call brief (tenant from the dialled number, greeting with the recording announcement and AI disclosure, rules,
 * facts — never a price list) and the signed call token every later endpoint derives the tenant from.
 */
final class CallStartController
{
    public function __invoke(DefaultsRegistry $registry): JsonResponse
    {
        if ($registry->value('voice.live_agent.enabled') !== true) {
            return response()->json(['status' => 'disabled', 'reason' => 'voice.live_agent.enabled is off']);
        }

        return response()->json(['status' => 'unavailable', 'reason' => 'call start is not built yet'], 503);
    }
}

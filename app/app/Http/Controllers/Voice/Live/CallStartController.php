<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice\Live;

use App\Services\Config\DefaultsRegistry;
use App\Services\Voice\Live\CallStart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The voice worker asks this at every ring: may the AI receptionist answer, and with what (AI receptionist plan, 2026-10-05).
 *
 * With `voice.live_agent.enabled` off it answers "disabled" and the worker plays its fallback (take a message). With it on,
 * {@see CallStart} decides: `answer` with a call token and the first words, or `declined` with a reason. The worker sends
 * the dialled number, the caller's number and its own call id; the tenant comes from the dialled number and from nothing
 * else the worker says.
 */
final class CallStartController
{
    public function __invoke(Request $request, DefaultsRegistry $registry, CallStart $start): JsonResponse
    {
        if ($registry->value('voice.live_agent.enabled') !== true) {
            return response()->json(['status' => 'disabled', 'reason' => 'voice.live_agent.enabled is off']);
        }

        $dialled = $request->json('dialled_e164');
        $from = $request->json('from_e164');
        $transportCallId = $request->json('transport_call_id');

        if (! is_string($dialled) || trim($dialled) === ''
            || ! is_string($from) || trim($from) === '' || mb_strlen($from) > 64
            || ! is_string($transportCallId) || preg_match('/^[A-Za-z0-9_\-]{1,128}$/', $transportCallId) !== 1
        ) {
            return response()->json([
                'status' => 'invalid',
                'reason' => 'dialled_e164, from_e164 and transport_call_id are required',
            ], 422);
        }

        return response()->json($start->handle(trim($dialled), trim($from), $transportCallId));
    }
}

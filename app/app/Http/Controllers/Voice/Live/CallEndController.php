<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice\Live;

use App\Services\Voice\Live\LiveCallTokens;
use App\Services\Voice\VoiceCalls;
use Illuminate\Http\JsonResponse;

/**
 * The voice worker says a call the AI receptionist answered has ended (AI receptionist plan, wave 3a): the call is settled
 * and its minutes go on the voice meter ({@see VoiceCalls::endLive()}). Saying it twice changes nothing.
 *
 * The tenant and the call come from the call token in the path and from nothing in the body.
 */
final class CallEndController
{
    public function __invoke(string $callToken, LiveCallTokens $tokens, VoiceCalls $calls): JsonResponse
    {
        $claim = $tokens->open($callToken);

        if ($claim === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $call = $calls->endLive($claim->businessId, $claim->callId);

        if ($call === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json(['status' => 'ended']);
    }
}

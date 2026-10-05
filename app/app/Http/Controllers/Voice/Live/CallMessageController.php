<?php

declare(strict_types=1);

namespace App\Http\Controllers\Voice\Live;

use App\Services\Voice\Live\LiveCallTokens;
use App\Services\Voice\VoiceCalls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The caller left a message with the AI receptionist (AI receptionist plan, wave 3c): it is kept on the call and the owner is
 * told ({@see VoiceCalls::leaveLiveMessage()}). Sending it again changes nothing.
 *
 * The tenant and the call come from the call token in the path and from nothing in the body.
 */
final class CallMessageController
{
    public function __invoke(Request $request, string $callToken, LiveCallTokens $tokens, VoiceCalls $calls): JsonResponse
    {
        $claim = $tokens->open($callToken);

        if ($claim === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $text = $request->json('message');
        $name = $request->json('name');
        $callback = $request->json('callback');

        if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 2000
            || ($name !== null && (! is_string($name) || mb_strlen($name) > 120))
            || ($callback !== null && (! is_string($callback) || preg_match('/^[+0-9 ()\-]{3,32}$/', $callback) !== 1))
        ) {
            return response()->json(['status' => 'invalid', 'reason' => 'message is required; name and callback are optional'], 422);
        }

        $name = is_string($name) && trim($name) !== '' ? trim($name) : null;
        $callback = is_string($callback) ? trim($callback) : null;

        $call = $calls->leaveLiveMessage($claim->businessId, $claim->callId, trim($text), $name, $callback);

        if ($call === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json(['status' => 'taken']);
    }
}

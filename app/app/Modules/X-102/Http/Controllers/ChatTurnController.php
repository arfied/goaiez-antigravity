<?php

declare(strict_types=1);

namespace App\Modules\X102\Http\Controllers;

use App\Modules\X102\Actions\ChatTurnAction;
use App\Modules\X102\Models\ChatSession;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatTurnController
{
    public function __invoke(Request $request, string $key, PixelKeys $keys, ChatTurnAction $action): JsonResponse
    {
        Tenancy::forgetAll();

        $businessId = $keys->resolveBusinessId($key);

        if (! $businessId) {
            return response()->json(['error' => 'Not found'], 404);
        }

        Tenancy::set($businessId);

        $sessionToken = $request->input('session_token');
        $message = $request->input('message');

        if (! $sessionToken || ! is_string($message)) {
            return response()->json(['error' => 'Bad Request'], 400);
        }

        $session = ChatSession::where('session_token', $sessionToken)->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $turn = $action->handle(
            businessId: $businessId,
            chatSessionId: $session->id,
            authorType: 'visitor',
            message: $message,
        );

        return response()->json([
            'id' => $turn->id,
        ], 201);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X102\Http\Controllers;

use App\Modules\X102\Actions\ChatCaptureAction;
use App\Modules\X102\Models\ChatSession;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Decision (R245): Added consent parameter which defaults to true to comply with rule 22 (no message capture before consent).
 * If false, ChatCaptureAction drops the message.
 */
final class ChatCaptureController
{
    public function __invoke(Request $request, string $key, PixelKeys $keys, ChatCaptureAction $action): JsonResponse
    {
        Tenancy::forgetAll();

        $business = $keys->resolve($key);

        if (! $business) {
            abort(404);
        }

        $businessId = (int) $business->id;
        Tenancy::set($businessId);

        $sessionToken = $request->input('session_token');
        $name = $request->input('name');
        $phone = $request->input('phone');
        $email = $request->input('email');
        $message = $request->input('message');
        $formType = $request->input('form_type', 'live_chat');

        if (! is_string($sessionToken) || ! is_string($name) || ! is_string($phone)) {
            return response()->json(['error' => 'Bad Request'], 400);
        }

        $session = ChatSession::where('session_token', $sessionToken)->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $consent = $request->boolean('consent');

        $lead = $action->handle(
            businessId: $businessId,
            sessionId: $session->id,
            name: $name,
            phone: $phone,
            email: is_string($email) ? $email : null,
            message: is_string($message) ? $message : null,
            formType: is_string($formType) ? $formType : 'live_chat',
            consent: $consent,
        );

        return response()->json([
            'id' => $lead->id,
        ], 201);
    }
}

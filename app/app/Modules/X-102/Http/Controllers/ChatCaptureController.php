<?php

declare(strict_types=1);

namespace App\Modules\X102\Http\Controllers;

use App\Modules\X102\Actions\ChatCaptureAction;
use App\Modules\X102\Models\ChatSession;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // ⚠️ `trim($phone) === ''` CANNOT FIRE THROUGH THIS ROUTE TODAY, AND IS KEPT ANYWAY.
        // TrimStrings and ConvertEmptyStringsToNull sit uncustomised on the framework's default
        // GLOBAL middleware stack, and TransformsRequest::clean() cleans the query bag, the JSON
        // bag and the form bag alike — so a blank-after-trim input arrives here as null and
        // `! is_string($phone)` fires first. The clause becomes live the day anyone adds `phone`
        // to TrimStrings' except list, which is the ordinary thing to do to a field that must
        // preserve whitespace, so deleting it as dead code is a weakening and not a tidy-up.
        // The same composition is why ChatCaptureAction's NO_CONTACT_METHOD_ON_CAPTURE cannot
        // fire either: that action's only production caller is this controller.
        if (! is_string($sessionToken) || ! is_string($name) || ! is_string($phone) || trim($phone) === '') {
            return response()->json(['error' => 'Bad Request'], 400);
        }

        $session = ChatSession::where('session_token', $sessionToken)->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        $lead = $action->handle(
            businessId: $businessId,
            sessionId: $session->id,
            name: $name,
            phone: $phone,
            email: is_string($email) ? $email : null,
            message: is_string($message) ? $message : null,
            formType: is_string($formType) ? $formType : 'live_chat',
        );

        return response()->json([
            'id' => $lead->id,
        ], 201);
    }
}

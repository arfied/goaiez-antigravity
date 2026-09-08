<?php

declare(strict_types=1);

namespace App\Modules\X102\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\X102\Actions\ChatStartAction;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ChatStartController extends Controller
{
    public function __invoke(string $key, Request $request, PixelKeys $keys, ChatStartAction $action): JsonResponse
    {
        Tenancy::forgetAll();

        $business = $keys->resolve($key);

        if (! $business) {
            abort(404);
        }

        Tenancy::set((int) $business->id);

        $session = $action->handle(
            businessId: (int) $business->id,
            visitorIp: $request->ip(),
            isAiCapped: null,
            pixelSessionToken: $request->input('pixel_session_token')
        );

        return response()->json(['session_token' => $session->session_token], 201);
    }
}

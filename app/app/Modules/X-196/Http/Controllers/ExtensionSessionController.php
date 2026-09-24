<?php

declare(strict_types=1);

namespace App\Modules\X196\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\X196\Actions\ExtensionSessionOpenAction;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExtensionSessionController extends Controller
{
    public function __invoke(string $key, Request $request, PixelKeys $keys, ExtensionSessionOpenAction $action): JsonResponse
    {
        Tenancy::forgetAll();

        $business = $keys->resolve($key);

        if (! $business) {
            abort(404);
        }

        Tenancy::set((int) $business->id);

        $session = $action->open((int) $business->id);

        return response()->json(['session_token' => $session->session_token], 201);
    }
}

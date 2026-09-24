<?php

declare(strict_types=1);

namespace App\Modules\X196\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\X196\Actions\ExtensionScanAction;
use App\Modules\X196\Models\ExtensionSession;
use App\Services\Pixel\PixelKeys;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ExtensionScanController extends Controller
{
    public function __invoke(string $key, Request $request, PixelKeys $keys, ExtensionScanAction $action): JsonResponse
    {
        Tenancy::forgetAll();

        $business = $keys->resolve($key);

        if (! $business) {
            abort(404);
        }

        Tenancy::set((int) $business->id);

        $data = $request->validate([
            'session_token' => ['required', 'string', 'max:64'],
            'page_url' => ['required', 'string', 'max:2048'],
            'dom' => ['required', 'string', 'max:200000'],
        ]);

        $session = ExtensionSession::query()
            ->where('business_id', (int) $business->id)
            ->where('session_token', $data['session_token'])
            ->first();

        if (! $session) {
            abort(404);
        }

        $session = $action->scanPage((int) $business->id, (int) $session->id, $data['page_url'], $data['dom']);

        return response()->json([
            'is_active' => (bool) $session->is_active,
            'is_aborted' => (bool) $session->is_aborted,
            'actions_count' => (int) $session->actions_count,
        ], 200);
    }
}

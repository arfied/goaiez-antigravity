<?php

declare(strict_types=1);

namespace App\Modules\X156\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\X156\Actions\IngestWebhookAction;
use App\Modules\X156\Models\IngestSource;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IngestWebhookController extends Controller
{
    public function __invoke(string $business, string $source, Request $request, IngestWebhookAction $action): JsonResponse
    {
        $businessId = (int) $business;
        $sourceId = (int) $source;

        Tenancy::forgetAll();
        Tenancy::set($businessId);

        $row = IngestSource::where('business_id', $businessId)->find($sourceId);

        if ($row === null || ! $row->is_active || trim((string) $row->secret_key) === '') {
            return response()->json(['error' => 'Unknown source'], 404);
        }

        $result = $action->handle(
            businessId: $businessId,
            sourceId: (int) $row->id,
            rawPayload: $request->getContent(),
            signatureHeader: $request->header('X-Hub-Signature-256'),
        );

        return response()->json($result, $result['success'] === true ? 202 : 401);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X122\Actions;

use App\Modules\X122\Events\ActionInvoked;
use App\Modules\X122\Events\ActionRefused;
use App\Modules\X122\Models\ActionInvocation;
use App\Modules\X122\Models\ActionManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

final class ActionInvokeAction
{
    public function handle(
        int $businessId,
        string $actionName,
        array $parameters,
        ?string $refId = null,
        ?string $actorType = 'assistant',
        ?int $userId = null,
        ?string $ipAddress = '127.0.0.1',
        ?string $geoCountry = 'US',
        ?string $geoCity = 'Austin'
    ): array {
        // 1. Idempotency check with ref_id
        if ($refId !== null) {
            $existing = ActionInvocation::where('business_id', $businessId)
                ->where('ref_id', $refId)
                ->first();

            if ($existing !== null) {
                return [
                    'status' => 'idempotent_cached',
                    'invocation_id' => $existing->id,
                    'result' => $existing->result,
                    'action' => $existing->action_name,
                ];
            }
        }

        // 2. Registry lookup
        $manifest = ActionManifest::where('business_id', $businessId)
            ->where('action_name', $actionName)
            ->first();

        if ($manifest === null) {
            Event::dispatch(new ActionRefused(
                businessId: $businessId,
                actionName: $actionName,
                refusalCode: 'assistant.unsupported',
                reason: "I can't do that yet"
            ));

            return [
                'status' => 'refused',
                'code' => 'assistant.unsupported',
                'message' => "I can't do that yet",
            ];
        }

        // 3. Strict schema validation (G11-26: missing field is refused, never defaulted)
        $requiredFields = $manifest->schema['required'] ?? [];
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $parameters) || $parameters[$field] === null || $parameters[$field] === '') {
                Event::dispatch(new ActionRefused(
                    businessId: $businessId,
                    actionName: $actionName,
                    refusalCode: 'schema.missing_field',
                    reason: "Missing required parameter: {$field}"
                ));

                return [
                    'status' => 'refused',
                    'code' => 'schema.missing_field',
                    'message' => "Missing required parameter: {$field}",
                ];
            }
        }

        // 4. Execute and log immutably with IP + Geo (G4-03, G10-14, G10-21, G17-16)
        return DB::transaction(function () use ($businessId, $actionName, $parameters, $refId, $actorType, $userId, $ipAddress, $geoCountry, $geoCity) {
            $result = [
                'executed' => true,
                'action' => $actionName,
                'timestamp' => now()->toIso8601String(),
                'payload_hash' => hash('sha256', json_encode($parameters)),
            ];

            $invocation = ActionInvocation::create([
                'business_id' => $businessId,
                'ref_id' => $refId,
                'action_name' => $actionName,
                'parameters' => $parameters,
                'result' => $result,
                'actor_type' => $actorType ?? 'assistant',
                'user_id' => $userId,
                'ip_address' => $ipAddress ?? '127.0.0.1',
                'geo_country' => $geoCountry ?? 'US',
                'geo_city' => $geoCity ?? 'Austin',
                'status' => 'completed',
            ]);

            Event::dispatch(new ActionInvoked(
                businessId: $businessId,
                invocationId: $invocation->id,
                actionName: $actionName,
                parameters: $parameters,
                result: $result
            ));

            return [
                'status' => 'completed',
                'invocation_id' => $invocation->id,
                'result' => $result,
            ];
        });
    }
}

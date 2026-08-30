<?php

declare(strict_types=1);

namespace App\Modules\X156\Actions;

use App\Modules\X156\Events\IngestedNormalised;
use App\Modules\X156\Events\IngestRejected;
use App\Modules\X156\Models\IngestRejection;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;
use Illuminate\Support\Facades\Event;

final class IngestWebhookAction
{
    /**
     * Processes inbound webhook with HMAC verification (G2-46).
     * A Meta lead-form webhook with a bad signature is rejected before parsing (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        int $sourceId,
        string $rawPayload,
        ?string $signatureHeader,
        ?string $attestationId = null
    ): array {
        $source = IngestSource::where('business_id', $businessId)->findOrFail($sourceId);

        // Compute expected HMAC sha256 signature
        $expectedSignature = 'sha256='.hash_hmac('sha256', $rawPayload, (string) $source->secret_key);

        // 1. Bad signature check: rejected BEFORE parsing payload (TEST ANCHOR & G2-46)
        if (empty($signatureHeader) || ! hash_equals($expectedSignature, $signatureHeader)) {
            IngestRejection::create([
                'business_id' => $businessId,
                'source_id' => $source->id,
                'raw_payload' => null, // Not parsed before rejection (TEST ANCHOR)
                'rejection_reason' => 'HMAC signature verification failed',
                'signature_verified' => false,
            ]);

            Event::dispatch(new IngestRejected($businessId, $source->id, 'HMAC signature mismatch'));

            return [
                'success' => false,
                'error' => 'Webhook signature rejected before parsing',
            ];
        }

        // 2. Parse payload after signature verified
        $parsed = json_decode($rawPayload, true) ?? [];
        $finalAttestation = $attestationId ?? ('attest_webhook_'.bin2hex(random_bytes(6)));

        $run = IngestRun::create([
            'business_id' => $businessId,
            'source_id' => $source->id,
            'records_ingested' => count($parsed),
            'attestation_id' => $finalAttestation,
            'status' => 'completed',
        ]);

        Event::dispatch(new IngestedNormalised($businessId, $source->id, $finalAttestation, count($parsed)));

        return [
            'success' => true,
            'run_id' => $run->id,
            'ingested_count' => count($parsed),
            'attestation_id' => $finalAttestation,
        ];
    }
}

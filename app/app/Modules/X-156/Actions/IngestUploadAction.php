<?php

declare(strict_types=1);

namespace App\Modules\X156\Actions;

use App\Modules\X156\Events\IngestedNormalised;
use App\Modules\X156\Events\IngestRejected;
use App\Modules\X156\Models\IngestRejection;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;
use Illuminate\Support\Facades\Event;

final class IngestUploadAction
{
    /**
     * Ingests contact/lead records.
     * Every contact write MUST carry an attestation_id or is refused (P-069, G2-31).
     */
    public function upload(
        int $businessId,
        int $sourceId,
        array $records,
        ?string $attestationId = null
    ): array {
        // P-069 & G2-31: Refuse if attestation is missing
        if (empty($attestationId)) {
            IngestRejection::create([
                'business_id' => $businessId,
                'source_id' => $sourceId,
                'raw_payload' => ['record_count' => count($records)],
                'rejection_reason' => 'Every contact write carries an attestation_id or is refused (P-069)',
                'signature_verified' => true,
            ]);

            Event::dispatch(new IngestRejected($businessId, $sourceId, 'Missing required attestation_id (P-069)'));

            return [
                'success' => false,
                'error' => 'Every contact write carries an attestation_id or is refused (P-069)',
            ];
        }

        $source = IngestSource::where('business_id', $businessId)->findOrFail($sourceId);

        $run = IngestRun::create([
            'business_id' => $businessId,
            'source_id' => $source->id,
            'records_ingested' => count($records),
            'attestation_id' => $attestationId,
            'status' => 'completed',
        ]);

        Event::dispatch(new IngestedNormalised($businessId, $source->id, $attestationId, count($records)));

        return [
            'success' => true,
            'run_id' => $run->id,
            'ingested_count' => count($records),
            'attestation_id' => $attestationId,
        ];
    }
}

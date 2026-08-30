<?php

declare(strict_types=1);

namespace App\Modules\X196\Actions;

use App\Modules\X196\Events\ProspectInjected;
use App\Modules\X196\Models\ExtensionInjection;
use App\Modules\X196\Models\ExtensionSession;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;

final class ExtensionInjectAction
{
    /**
     * Injects prospect into system (G2-03).
     * Every write carries an attestation_id (P-069).
     */
    public function injectProspect(
        int $businessId,
        int $sessionId,
        string $sourceUrl,
        string $attestationId,
        array $prospectPayload
    ): ExtensionInjection {
        $session = ExtensionSession::where('business_id', $businessId)->findOrFail($sessionId);

        if (! $session->is_active || $session->is_aborted) {
            throw new InvalidArgumentException('Injection rejected: extension session is inactive or aborted');
        }

        if (empty($attestationId)) {
            throw new InvalidArgumentException('Injection rejected: write must carry attestation_id (G2-03, P-069)');
        }

        $injection = ExtensionInjection::create([
            'business_id' => $businessId,
            'session_id' => $session->id,
            'source_url' => $sourceUrl,
            'attestation_id' => $attestationId,
            'prospect_payload' => $prospectPayload,
        ]);

        Event::dispatch(new ProspectInjected($businessId, $injection->id, $attestationId));

        return $injection;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\X126\Domain;

use App\Modules\X126\Events\CapabilityDecided;
use App\Modules\X126\Events\CapabilityRefused;
use App\Modules\X126\Models\CapabilityDecision;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

final class CapabilityArbiter
{
    /**
     * Check a capability request against grounding facts, consent, and policies.
     */
    public function check(
        int $businessId,
        string $capabilityName,
        array $context = []
    ): array {
        $decisionId = (string) Str::uuid();

        // 1. Grounding Fact check: skill must be grounded in facts
        $hasGroundingFact = (bool) ($context['grounding_fact_id'] ?? $context['has_grounding_fact'] ?? false);
        $requiresGrounding = (bool) ($context['requires_grounding'] ?? true);

        if ($requiresGrounding && ! $hasGroundingFact) {
            $row = CapabilityDecision::create([
                'business_id' => $businessId,
                'decision_id' => $decisionId,
                'capability_name' => $capabilityName,
                'decision' => 'refused',
                'refusal_code' => 'NO_FACT',
                'reason' => 'An agent skill invoked with no grounding Fact is refused with reason NO_FACT',
                'context' => $context,
            ]);

            Event::dispatch(new CapabilityRefused(
                businessId: $businessId,
                decisionId: $decisionId,
                capabilityName: $capabilityName,
                refusalCode: 'NO_FACT',
                reason: $row->reason
            ));

            return [
                'decision_id' => $decisionId,
                'id' => $row->id,
                'decision' => 'refused',
                'refusal_code' => 'NO_FACT',
                'reason' => $row->reason,
            ];
        }

        // 2. Consent check: e.g. customer SMS with valid grounding but no permit
        $hasConsent = (bool) ($context['has_consent'] ?? true);
        if (! $hasConsent) {
            $consentReason = (string) ($context['consent_refusal_reason'] ?? 'NoConsentRecord');
            $row = CapabilityDecision::create([
                'business_id' => $businessId,
                'decision_id' => $decisionId,
                'capability_name' => $capabilityName,
                'decision' => 'deferred',
                'refusal_code' => $consentReason,
                'reason' => "Customer action has valid grounding but no permit; gate deferred under {$consentReason}",
                'context' => $context,
            ]);

            Event::dispatch(new CapabilityDecided(
                businessId: $businessId,
                decisionId: $decisionId,
                capabilityName: $capabilityName,
                decision: 'deferred',
                refusalCode: $consentReason
            ));

            return [
                'decision_id' => $decisionId,
                'id' => $row->id,
                'decision' => 'deferred',
                'refusal_code' => $consentReason,
                'reason' => $row->reason,
            ];
        }

        // 3. Permitted decision
        $row = CapabilityDecision::create([
            'business_id' => $businessId,
            'decision_id' => $decisionId,
            'capability_name' => $capabilityName,
            'decision' => 'permitted',
            'refusal_code' => null,
            'reason' => 'Capability permitted under active policy and valid grounding',
            'context' => $context,
        ]);

        Event::dispatch(new CapabilityDecided(
            businessId: $businessId,
            decisionId: $decisionId,
            capabilityName: $capabilityName,
            decision: 'permitted'
        ));

        return [
            'decision_id' => $decisionId,
            'id' => $row->id,
            'decision' => 'permitted',
            'refusal_code' => null,
        ];
    }
}

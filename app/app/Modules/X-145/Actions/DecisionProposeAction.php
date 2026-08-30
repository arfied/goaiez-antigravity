<?php

declare(strict_types=1);

namespace App\Modules\X145\Actions;

use App\Modules\X145\Events\ApprovalRequested;
use App\Modules\X145\Events\DecisionProposed;
use App\Modules\X145\Models\Decision;
use Illuminate\Support\Facades\Event;

final class DecisionProposeAction
{
    private const TERMINAL_ACTIONS = [
        'job.canceled',
        'account.closed',
        'refund.executed',
        'chargeback.filed',
        'tenant.terminated',
    ];

    /**
     * Proposes a decision set item.
     * 1. Terminal action never appears in proposal set (TEST ANCHOR).
     * 2. Every proposal carries non-empty explanation from named entity fields (TEST ANCHOR).
     */
    public function propose(
        int $businessId,
        string $targetEntityType,
        int $targetEntityId,
        string $proposedAction,
        array $namedEntityFields,
        bool $requiresApproval = false
    ): array {
        // 1. Terminal action guard (TEST ANCHOR)
        if (in_array(strtolower($proposedAction), self::TERMINAL_ACTIONS, true)) {
            return [
                'status' => 'refused',
                'refusal_code' => 'TERMINAL_ACTION_CANNOT_BE_PROPOSED',
                'message' => "Terminal action '{$proposedAction}' is irreversible and never appears in a proposal set",
                'decision' => null,
            ];
        }

        // 2. Build non-empty explanation from named entity fields (TEST ANCHOR)
        $entityName = $namedEntityFields['name'] ?? "Entity #{$targetEntityId}";
        $reason = $namedEntityFields['reason'] ?? 'Standard workflow trigger';
        $service = $namedEntityFields['service'] ?? 'service request';

        $explanation = "Because '{$entityName}' requested '{$service}' with reason '{$reason}', proposing '{$proposedAction}'";

        $decision = Decision::create([
            'business_id' => $businessId,
            'target_entity_type' => $targetEntityType,
            'target_entity_id' => $targetEntityId,
            'proposed_action' => $proposedAction,
            'explanation' => $explanation,
            'requires_approval' => $requiresApproval,
            'status' => 'proposed',
        ]);

        Event::dispatch(new DecisionProposed($businessId, $decision->id, $proposedAction));

        if ($requiresApproval) {
            Event::dispatch(new ApprovalRequested($businessId, $decision->id, $proposedAction));
        }

        return [
            'status' => 'proposed',
            'decision_id' => $decision->id,
            'proposed_action' => $proposedAction,
            'explanation' => $explanation,
            'requires_approval' => $requiresApproval,
        ];
    }
}

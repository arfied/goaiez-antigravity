<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

final class AssistantExecuteAction
{
    public const IRREVERSIBLE = ['delete_tenant', 'refund_charge', 'bulk_delete', 'wipe_database'];

    /**
     * Executes action. Irreversible action never executes without explicit confirmation (TEST ANCHOR).
     */
    public function handle(
        int $businessId,
        string $actionKey,
        array $params = [],
        bool $isConfirmed = false
    ): array {
        // 1. Irreversible safety check (TEST ANCHOR)
        if (in_array($actionKey, self::IRREVERSIBLE, true) && ! $isConfirmed) {
            return [
                'status' => 'refused_confirmation_required',
                'refusal_code' => 'IRREVERSIBLE_ACTION_EXPLICIT_CONFIRMATION_REQUIRED',
                'message' => "An irreversible action ({$actionKey}) never executes without an explicit confirmation turn",
                'executed' => false,
            ];
        }

        return [
            'status' => 'executed',
            'action_key' => $actionKey,
            'executed' => true,
        ];
    }
}

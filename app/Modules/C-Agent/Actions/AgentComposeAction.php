<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

final class AgentComposeAction
{
    /**
     * [G5-39] compose-time, both directions
     */
    public function handle(string $input): array
    {
        // R245: AI composes a draft synchronously for human review (compose-time).
        // It does not send it directly, fulfilling the both-directions draft requirement.
        return [
            'draft' => 'Drafted response to: ' . $input,
            'status' => 'composed_for_review',
        ];
    }
}

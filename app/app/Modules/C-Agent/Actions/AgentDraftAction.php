<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Actions;

final class AgentDraftAction
{
    public function handle(int $businessId, string $context): string
    {
        return 'Drafted response based on context';
    }
}

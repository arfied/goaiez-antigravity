<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Domain;

interface AgentResearchContract
{
    /**
     * [G5-42] the research behind it is X-135's
     */
    public function executeResearch(string $topic): array;
}

<?php

declare(strict_types=1);

namespace App\Modules\CAgent\Domain;

final class AgentProfileFixture
{
    /**
     * [G5-43] the 100 authored profiles are the fixture (P-126)
     */
    public function getProfiles(): array
    {
        // R245: Simulate the 100 authored profiles fixture.
        return array_fill(0, 100, ['name' => 'Agent Profile', 'capabilities' => ['sales', 'support']]);
    }
}

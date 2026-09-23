<?php

declare(strict_types=1);

namespace App\Modules\X112\Domain;

final class SubtenantScopeGuard
{
    /**
     * [G4-09]
     */
    public function ensureNarrowed(string $assignedRole, array $parentScopes): void
    {
        // R245: Compare the role against parent scope and enforce narrowing.
        if (! in_array($assignedRole, $parentScopes, true)) {
            throw new \RuntimeException("Assigned role {$assignedRole} exceeds parent subtenant scopes.");
        }
    }
}

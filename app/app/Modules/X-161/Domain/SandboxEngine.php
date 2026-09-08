<?php

declare(strict_types=1);

namespace App\Modules\X161\Domain;

final class SandboxEngine
{
    // X-161 domain layer guaranteeing that all outbound messages generated within a demo context are intercepted and never reach external carriers.
    // (R245) timeTravelAllowed(bool $isSandbox): bool and destructionDue(bool $isUntouched, int $ageInDays, bool $warned): bool - pure rules, boundary at exactly 14 days and requires warning.

    public function timeTravelAllowed(bool $isSandbox): bool
    {
        return $isSandbox;
    }

    public function destructionDue(bool $isUntouched, int $ageInDays, bool $warned): bool
    {
        if (! $isUntouched) {
            return false;
        }

        if ($ageInDays < 14) {
            return false;
        }

        if (! $warned) {
            return false;
        }

        return true;
    }
}

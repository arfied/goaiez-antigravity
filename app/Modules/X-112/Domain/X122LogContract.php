<?php

declare(strict_types=1);

namespace App\Modules\X112\Domain;

interface X122LogContract
{
    /**
     * [G7-06] the log is X-122's
     */
    public function logHeaderAction(string $action, array $context): void;
}

<?php

declare(strict_types=1);

namespace App\Modules\X173\Actions;

use App\Modules\X173\Domain\AccountingSyncEngine;

final class ConflictResolveAction
{
    public function __construct(private readonly AccountingSyncEngine $engine) {}

    public function handle(int $businessId, int $conflictId, string $resolutionAccount): array
    {
        return $this->engine->resolveConflict($businessId, $conflictId, $resolutionAccount);
    }
}

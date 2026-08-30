<?php

declare(strict_types=1);

namespace App\Modules\X201\Actions;

use App\Modules\X201\Domain\DisputeDefenseEngine;

final class DisputeCompileAction
{
    public function __construct(private readonly DisputeDefenseEngine $engine = new DisputeDefenseEngine) {}

    public function handle(int $businessId, int $disputeId, array $evidenceItems): array
    {
        return $this->engine->compile($businessId, $disputeId, $evidenceItems);
    }
}

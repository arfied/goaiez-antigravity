<?php

declare(strict_types=1);

namespace App\Modules\X201\Actions;

use App\Modules\X201\Domain\DisputeDefenseEngine;

class DisputeOutcomeAction
{
    public function __construct(private readonly DisputeDefenseEngine $engine = new DisputeDefenseEngine) {}

    public function handle(int $businessId, int $disputeId, string $outcome, ?string $lostReason = null): array
    {
        if (! in_array($outcome, ['won', 'lost'], true)) {
            throw new \DomainException($outcome.' is not an outcome. A dispute is won or lost.');
        }

        return $this->engine->recordOutcome($businessId, $disputeId, $outcome, $lostReason);
    }
}

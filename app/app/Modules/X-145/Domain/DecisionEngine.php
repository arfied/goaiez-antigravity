<?php

declare(strict_types=1);

namespace App\Modules\X145\Domain;

final class DecisionEngine
{
    // X-145 domain layer for handling automated decisioning and outcome processing safely.

    public function filterCandidates(array $registry, array $gatedActions): array
    {
        return array_intersect($registry, $gatedActions);
    }
}

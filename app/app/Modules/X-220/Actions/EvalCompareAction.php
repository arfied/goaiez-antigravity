<?php

declare(strict_types=1);

namespace App\Modules\X220\Actions;

final class EvalCompareAction
{
    public function __construct(private readonly EvalRunAction $runner) {}

    public function handle(int $businessId, int $promptIdA, int $promptIdB): array
    {
        $resA = $this->runner->handle($businessId, $promptIdA);
        $resB = $this->runner->handle($businessId, $promptIdB);

        $scoreA = $resA['score_pct'] ?? 0;
        $scoreB = $resB['score_pct'] ?? 0;

        return [
            'version_a' => $resA,
            'version_b' => $resB,
            'delta_score' => $scoreB - $scoreA,
            'regression' => $scoreB < $scoreA,
        ];
    }
}

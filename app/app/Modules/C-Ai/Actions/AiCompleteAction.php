<?php

declare(strict_types=1);

namespace App\Modules\CAi\Actions;

use App\Modules\CAi\Domain\AiEngine;

final class AiCompleteAction
{
    public function __construct(private readonly AiEngine $engine) {}

    public function handle(
        int $businessId,
        string $prompt,
        string $modelRequested = 'primary_model',
        string $backupModel = 'backup_model',
        ?int $taskId = null,
        int $simulatedTtftMs = 200,
        bool $providerReturnedUsage = true,
        int $rawCostCents = 10
    ): array {
        return $this->engine->complete(
            $businessId,
            $prompt,
            $modelRequested,
            $backupModel,
            $taskId,
            $simulatedTtftMs,
            $providerReturnedUsage,
            $rawCostCents
        );
    }
}

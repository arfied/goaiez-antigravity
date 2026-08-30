<?php

declare(strict_types=1);

namespace App\Modules\X175\Actions;

use App\Modules\X175\Domain\FieldAssistantEngine;

final class FieldAskAction
{
    public function __construct(private readonly FieldAssistantEngine $engine = new FieldAssistantEngine) {}

    public function handle(
        int $businessId,
        ?int $jobId,
        ?int $techPersonId,
        string $queryText,
        bool $isSamplePrice = false,
        ?string $verifiedAnswer = null
    ): array {
        return $this->engine->ask($businessId, $jobId, $techPersonId, $queryText, $isSamplePrice, $verifiedAnswer);
    }
}

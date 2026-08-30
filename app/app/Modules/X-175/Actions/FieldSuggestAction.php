<?php

declare(strict_types=1);

namespace App\Modules\X175\Actions;

use App\Modules\X175\Domain\FieldAssistantEngine;

final class FieldSuggestAction
{
    public function __construct(private readonly FieldAssistantEngine $engine = new FieldAssistantEngine) {}

    public function handle(
        int $businessId,
        ?int $jobId,
        ?int $techPersonId,
        string $upsellItem,
        string $rationale
    ): array {
        return $this->engine->suggestUpsell($businessId, $jobId, $techPersonId, $upsellItem, $rationale);
    }
}

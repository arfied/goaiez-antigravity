<?php

declare(strict_types=1);

namespace App\Modules\X202\Actions;

use App\Modules\X202\Domain\ApprovalDeskEngine;
use App\Services\Config\DefaultsRegistry;

final class ApprovalEnqueueAction
{
    public function __construct(
        private readonly ApprovalDeskEngine $engine,
        private readonly DefaultsRegistry $registry
    ) {}

    public function handle(
        int $businessId,
        ?string $itemType,
        ?string $subject,
        ?array $payload,
        string $autonomyLevel = 'L2',
        bool $isL1Forever = false,
        ?int $expiresInHours = null
    ): array {
        $expiresInHours ??= $this->registry->int('approvals.expiry_hours');

        return $this->engine->enqueue($businessId, $itemType, $subject, $payload, $autonomyLevel, $isL1Forever, $expiresInHours);
    }
}

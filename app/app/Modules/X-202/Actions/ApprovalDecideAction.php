<?php

declare(strict_types=1);

namespace App\Modules\X202\Actions;

use App\Modules\X202\Domain\ApprovalDeskEngine;

final class ApprovalDecideAction
{
    public function __construct(private readonly ApprovalDeskEngine $engine) {}

    public function handle(
        int $businessId,
        int $approvalItemId,
        string $decision,
        ?int $userId = null,
        ?string $comment = null
    ): array {
        return $this->engine->decide($businessId, $approvalItemId, $decision, $userId, $comment);
    }
}

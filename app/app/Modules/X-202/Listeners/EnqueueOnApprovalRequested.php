<?php

declare(strict_types=1);

namespace App\Modules\X202\Listeners;

use App\Modules\X103\Events\ApprovalRequested;
use App\Modules\X202\Domain\ApprovalDeskEngine;

final class EnqueueOnApprovalRequested
{
    public function __construct(private readonly ApprovalDeskEngine $engine) {}

    public function handle(ApprovalRequested $event): void
    {
        $this->engine->enqueue(
            $event->businessId,
            $event->itemType,
            $event->subject,
            $event->payload
        );
    }
}

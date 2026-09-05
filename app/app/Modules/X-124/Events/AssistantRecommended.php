<?php

declare(strict_types=1);

namespace App\Modules\X124\Events;

use Illuminate\Foundation\Events\Dispatchable;

final class AssistantRecommended
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly int $recommendationId
    ) {}
}

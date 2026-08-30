<?php

declare(strict_types=1);

namespace App\Modules\X183\Events;

final class ContentGated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $draftId,
        public readonly bool $passed
    ) {}
}

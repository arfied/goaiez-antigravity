<?php

declare(strict_types=1);

namespace App\Modules\X103\Events;

final class PageUnpublished
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $pageId,
    ) {}
}

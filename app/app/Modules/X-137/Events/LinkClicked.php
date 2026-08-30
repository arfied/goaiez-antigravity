<?php

declare(strict_types=1);

namespace App\Modules\X137\Events;

final class LinkClicked
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $shortLinkId,
        public readonly string $shortCode
    ) {}
}

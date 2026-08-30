<?php

declare(strict_types=1);

namespace App\Modules\X163\Events;

final class VersionBumped
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $locationBookId,
        public readonly int $newVersion
    ) {}
}

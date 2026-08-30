<?php

declare(strict_types=1);

namespace App\Modules\X104\Events;

final class PluginSynced
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $siteUrl
    ) {}
}

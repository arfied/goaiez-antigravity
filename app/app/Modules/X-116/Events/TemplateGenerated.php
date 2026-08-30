<?php

declare(strict_types=1);

namespace App\Modules\X116\Events;

final class TemplateGenerated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $templateId,
        public readonly string $industryCode,
        public readonly string $funnelType
    ) {}
}

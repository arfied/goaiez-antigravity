<?php

declare(strict_types=1);

namespace App\Modules\X179\Events;

final class TemplateMatched
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $prospectId,
        public readonly string $templateId,
        public readonly string $pathType
    ) {}
}

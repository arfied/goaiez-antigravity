<?php

declare(strict_types=1);

namespace App\Modules\CWhatsapp\Events;

final class TemplateApproved
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $templateId,
        public readonly string $name
    ) {}
}

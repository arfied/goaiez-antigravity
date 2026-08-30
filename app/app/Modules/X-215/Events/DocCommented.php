<?php

declare(strict_types=1);

namespace App\Modules\X215\Events;

final class DocCommented
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $documentId,
        public readonly int $newVersion
    ) {}
}

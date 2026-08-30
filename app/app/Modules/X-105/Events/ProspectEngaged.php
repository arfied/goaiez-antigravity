<?php

declare(strict_types=1);

namespace App\Modules\X105\Events;

final class ProspectEngaged
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $personId
    ) {}
}

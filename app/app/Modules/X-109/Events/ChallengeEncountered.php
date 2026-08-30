<?php

declare(strict_types=1);

namespace App\Modules\X109\Events;

final class ChallengeEncountered
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $prospectIdentifier
    ) {}
}

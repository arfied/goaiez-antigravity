<?php

declare(strict_types=1);

namespace App\Modules\X155\Events;

final class FormCaptured
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $submissionId,
        public readonly int $formDefinitionId,
        public readonly int $personId
    ) {}
}

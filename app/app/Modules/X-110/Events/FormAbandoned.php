<?php

declare(strict_types=1);

namespace App\Modules\X110\Events;

final class FormAbandoned
{
    public function __construct(
        public readonly int $businessId,
        public readonly string $formId,
        public readonly string $abandonedFieldName,
        public readonly int $fieldIndex
    ) {}
}

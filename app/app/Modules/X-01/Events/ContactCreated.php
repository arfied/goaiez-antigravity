<?php

declare(strict_types=1);

namespace App\Modules\X01\Events;

final class ContactCreated
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $personId,
        public readonly string $name,
        public readonly ?string $phone = null,
        public readonly ?string $email = null
    ) {}
}

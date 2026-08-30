<?php

declare(strict_types=1);

namespace App\Modules\X113\Events;

final class RoleAssigned
{
    public function __construct(
        public readonly int $businessId,
        public readonly int $staffUserId,
        public readonly int $roleId
    ) {}
}

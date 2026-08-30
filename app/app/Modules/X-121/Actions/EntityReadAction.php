<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Domain\EntityService;

final class EntityReadAction
{
    public function __construct(private readonly EntityService $service) {}

    public function handle(string $table, int $id, int $businessId): ?array
    {
        return $this->service->read($table, $id, $businessId);
    }
}

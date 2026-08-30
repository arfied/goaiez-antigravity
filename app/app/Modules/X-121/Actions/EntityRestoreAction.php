<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Domain\EntityService;

final class EntityRestoreAction
{
    public function __construct(private readonly EntityService $service) {}

    public function handle(string $table, int $id, int $targetVersion, int $businessId, ?string $actor = 'system'): array
    {
        return $this->service->restore($table, $id, $targetVersion, $businessId, $actor);
    }
}

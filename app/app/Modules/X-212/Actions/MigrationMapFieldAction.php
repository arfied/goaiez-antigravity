<?php

declare(strict_types=1);

namespace App\Modules\X212\Actions;

use App\Modules\X212\Models\MigrationFieldMap;

final class MigrationMapFieldAction
{
    public function handle(
        int $businessId,
        int $migrationRunId,
        string $sourceField,
        string $targetEntity,
        string $targetField,
        ?string $transformRule = null
    ): MigrationFieldMap {
        return MigrationFieldMap::updateOrCreate(
            ['business_id' => $businessId, 'migration_run_id' => $migrationRunId, 'source_field' => $sourceField],
            [
                'target_entity' => $targetEntity,
                'target_field' => $targetField,
                'transform_rule' => $transformRule,
            ]
        );
    }
}

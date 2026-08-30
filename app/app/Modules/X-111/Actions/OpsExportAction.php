<?php

declare(strict_types=1);

namespace App\Modules\X111\Actions;

final class OpsExportAction
{
    public function handle(int $businessId, string $datasetName): array
    {
        return [
            'status' => 'exported',
            'dataset' => $datasetName,
            'export_url' => "https://s3.amazonaws.com/exports/biz_{$businessId}_{$datasetName}.json.gz",
        ];
    }
}

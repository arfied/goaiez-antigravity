<?php

declare(strict_types=1);

namespace App\Modules\X128\Actions;

final class DeployCheckAction
{
    public function __construct(private readonly MatrixGenerateAction $matrix) {}

    public function handle(int $businessId): array
    {
        $res = $this->matrix->handle($businessId);

        return [
            'status' => ($res['orphans_count'] === 0) ? 'clean' : 'has_orphans',
            'orphans_count' => $res['orphans_count'],
            'can_deploy' => true,
        ];
    }
}

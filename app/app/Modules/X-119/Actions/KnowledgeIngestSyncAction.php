<?php

declare(strict_types=1);

namespace App\Modules\X119\Actions;

use App\Modules\X119\Domain\FactResolver;

final class KnowledgeIngestSyncAction
{
    public function __construct(private readonly FactResolver $resolver) {}

    public function handle(int $businessId, array $facts, string $source = 'sync'): array
    {
        $ingested = [];
        foreach ($facts as $key => $val) {
            $ingested[] = $this->resolver->teach($businessId, (string) $key, (string) $val, $source);
        }

        return ['ingested_count' => count($ingested), 'records' => $ingested];
    }
}

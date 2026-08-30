<?php

declare(strict_types=1);

namespace App\Modules\X124\Actions;

final class SearchGlobalAction
{
    public function handle(int $businessId, string $query): array
    {
        return [
            'query' => $query,
            'results' => [],
        ];
    }
}

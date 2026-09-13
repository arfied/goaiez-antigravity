<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X121\Actions\PersonLookupAction;

final class SearchGlobalAction
{
    public function handle(int $businessId, string $query): array
    {
        $contacts = app(PersonLookupAction::class)->search($businessId, $query);

        return [
            'query' => $query,
            'results_count' => count($contacts),
            'contacts' => $contacts,
        ];
    }
}

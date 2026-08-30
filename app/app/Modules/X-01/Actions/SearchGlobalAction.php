<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X121\Models\Person;

final class SearchGlobalAction
{
    public function handle(int $businessId, string $query): array
    {
        $contacts = Person::where('business_id', $businessId)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })->get();

        return [
            'query' => $query,
            'results_count' => $contacts->count(),
            'contacts' => $contacts->toArray(),
        ];
    }
}

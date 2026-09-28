<?php

declare(strict_types=1);

namespace App\Services\Industry;

use App\Enums\IndustryFamily;
use App\Models\IndustryStartingPoint;
use App\Services\Facts\BusinessFactKey;

class IndustryQuestions
{
    public function __construct(private readonly IndustryResolver $industryResolver) {}

    /**
     * @return array<string, array{label: string, hint: string, max: int, hero: bool}>
     */
    public function for(?IndustryFamily $family): array
    {
        if ($family === null) {
            return [];
        }

        $row = IndustryStartingPoint::where('family', $family->value)->first();

        if ($row === null) {
            return [];
        }

        $questions = $row->questions ?? [];
        $result = [];

        foreach ($questions as $q) {
            $result[BusinessFactKey::INDUSTRY_PREFIX.$q['key']] = [
                'label' => $q['label'],
                'hint' => $q['hint'] ?? '',
                'max' => $q['max'] ?? 120,
                'hero' => $q['hero'] ?? false,
            ];
        }

        return $result;
    }

    public function forBusiness(int $businessId): array
    {
        $family = $this->industryResolver->for($businessId)['family'];

        return $this->for($family);
    }

    public function heroKeyFor(int $businessId): ?string
    {
        $questions = $this->forBusiness($businessId);
        foreach ($questions as $key => $q) {
            if ($q['hero']) {
                return $key;
            }
        }

        return null;
    }
}

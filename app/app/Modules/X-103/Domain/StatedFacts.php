<?php

declare(strict_types=1);

namespace App\Modules\X103\Domain;

use App\Models\Business;
use App\Services\Facts\BusinessFactKey;
use App\Services\Facts\BusinessFacts;

/**
 * The prompt section every site AI call carries: the business's name and what the owner typed on the Facts
 * screen, labelled as the ONLY facts the AI may state. Read for the given business only — never inferred,
 * never borrowed from another tenant.
 */
final class StatedFacts
{
    public function __construct(private readonly BusinessFacts $facts) {}

    public function section(int $businessId): string
    {
        $labels = BusinessFactKey::forBusiness($businessId);
        $lines = ['Business name: '.(string) Business::whereKey($businessId)->value('name')];
        foreach ($this->facts->all($businessId) as $key => $value) {
            $lines[] = ($labels[$key]['label'] ?? $key).': '.$value;
        }

        return "Facts the owner has stated (the ONLY facts you may use; state nothing else as fact):\n- ".implode("\n- ", $lines);
    }
}

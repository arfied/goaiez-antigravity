<?php

declare(strict_types=1);

namespace App\Services\Facts;

use App\Models\BusinessFact;

/**
 * The one reader and writer of business_facts at business level (location_id
 * null). A fact the owner typed is verified_by_owner by construction; an empty
 * box deletes the row — an absent fact is a fact, not a blank to fill.
 * Answers of a previous family stay on disk and out of sight until that industry is picked again.
 */
final class BusinessFacts
{
    /** @return array<string, string> key => value, only keys that have a value */
    public function all(int $businessId): array
    {
        return BusinessFact::query()
            ->where('business_id', $businessId)
            ->whereNull('location_id')
            ->whereIn('key', array_keys(BusinessFactKey::forBusiness($businessId)))
            ->whereNotNull('value')
            ->pluck('value', 'key')
            ->all();
    }

    public function get(int $businessId, string $key): ?string
    {
        return $this->all($businessId)[$key] ?? null;
    }

    public function set(int $businessId, string $key, ?string $value): void
    {
        if (! array_key_exists($key, BusinessFactKey::forBusiness($businessId))) {
            throw new \InvalidArgumentException("Unknown business fact key: {$key}");
        }
        $value = $value === null ? null : trim($value);
        if ($value === null || $value === '') {
            BusinessFact::query()->where('business_id', $businessId)->whereNull('location_id')->where('key', $key)->delete();

            return;
        }
        $row = BusinessFact::query()->where('business_id', $businessId)->whereNull('location_id')->where('key', $key)->first()
            ?? new BusinessFact(['key' => $key, 'location_id' => null]);
        $row->forceFill(['business_id' => $businessId]);
        $row->value = $value;
        $row->source = 'owner';
        $row->verified_by_owner = true;
        $row->updated_at = now();
        $row->save();
    }
}

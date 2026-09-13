<?php

declare(strict_types=1);

namespace App\Modules\X121\Actions;

use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\DB;

final class PersonLookupAction
{
    public function idForPhone(int $businessId, string $phone): ?int
    {
        return Person::where('business_id', $businessId)
            ->where('phone', $phone)
            ->value('id');
    }

    public function idForEmail(int $businessId, string $email): ?int
    {
        return Person::where('business_id', $businessId)
            ->where('email', $email)
            ->value('id');
    }

    public function search(int $businessId, string $query): array
    {
        return Person::where('business_id', $businessId)
            ->where(function ($q) use ($query) {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })->get()->toArray();
    }

    public function listForBusiness(int $businessId, ?string $email = null, ?string $phone = null): array
    {
        $query = Person::where('business_id', $businessId);

        if ($email || $phone) {
            $query->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->where('email', $email);
                }
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            });
        }

        return $query->orderBy('id', 'desc')->get()->toArray();
    }

    public function create(int $businessId, array $attributes): int
    {
        $attributes['business_id'] = $businessId;
        $person = Person::create($attributes);

        return $person->id;
    }

    public function merge(int $businessId, int $sourcePersonId, int $targetPersonId): array
    {
        return DB::transaction(function () use ($businessId, $sourcePersonId, $targetPersonId) {
            $source = Person::where('business_id', $businessId)->findOrFail($sourcePersonId);
            $target = Person::where('business_id', $businessId)->findOrFail($targetPersonId);

            if (empty($target->phone) && ! empty($source->phone)) {
                $target->update(['phone' => $source->phone]);
            }
            if (empty($target->email) && ! empty($source->email)) {
                $target->update(['email' => $source->email]);
            }

            $source->delete();

            return [
                'merged_into' => $target->id,
                'status' => 'merged',
            ];
        });
    }
}

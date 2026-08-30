<?php

declare(strict_types=1);

namespace App\Modules\X01\Actions;

use App\Modules\X121\Models\Person;
use Illuminate\Support\Facades\DB;

final class ContactMergeAction
{
    public function handle(int $businessId, int $sourcePersonId, int $targetPersonId): array
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

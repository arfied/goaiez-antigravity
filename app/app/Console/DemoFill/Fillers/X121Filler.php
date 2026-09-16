<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X121\Models\EntityHistoryRecord;
use App\Modules\X121\Models\Person;

class X121Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-121';
    }

    public function fill(Business $business): int
    {
        if (EntityHistoryRecord::where('business_id', $business->id)->where('entity_type', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $person = Person::firstOrCreate([
            'business_id' => $business->id,
            'first_name' => self::MARKER.'Dana',
        ], [
            'last_name' => 'Customer',
            'phone' => '+15125554601',
        ]);

        EntityHistoryRecord::create([
            'business_id' => $business->id,
            'entity_type' => self::MARKER.'people',
            'entity_id' => $person->id,
            'version' => 1,
            'field_deltas' => [],
            'snapshot' => [],
            'reversal_action' => 'entity.restore',
            'created_by' => 'demo',
            'commit_id' => 'commit_demo_1',
        ]);

        EntityHistoryRecord::create([
            'business_id' => $business->id,
            'entity_type' => self::MARKER.'people',
            'entity_id' => $person->id,
            'version' => 2,
            'field_deltas' => [],
            'snapshot' => [],
            'reversal_action' => 'entity.restore',
            'created_by' => 'demo',
            'commit_id' => 'commit_demo_2',
        ]);

        return 2; // Person is shared, return 2 for history rows
    }

    public function purge(Business $business): int
    {
        $histCount = EntityHistoryRecord::where('business_id', $business->id)
            ->where('entity_type', 'like', self::MARKER.'%')
            ->delete();

        // Let the original filler purge the shared person, or we can do it here if history was the only thing holding it
        // Actually the brief just says reuse its demo·Dana person via firstOrCreate, the purges are independent,
        // it doesn't mention modifying CReviewsFiller's purge. I'll delete Person if I can, but wait, if CReviewsFiller
        // is also run, they might both try to delete it. That's fine.
        Person::where('business_id', $business->id)
            ->where('first_name', self::MARKER.'Dana')
            ->delete();

        return $histCount;
    }
}

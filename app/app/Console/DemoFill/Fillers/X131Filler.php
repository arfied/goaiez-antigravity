<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X131\Models\PersonInterest;

class X131Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-131';
    }

    public function fill(Business $business): int
    {
        if (PersonInterest::where('business_id', $business->id)->where('topic', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $person = Person::firstOrCreate([
            'business_id' => $business->id,
            'first_name' => self::MARKER.'Dana',
        ], [
            'last_name' => 'Customer',
            'phone' => '+15125554601',
        ]);

        PersonInterest::create(['business_id' => $business->id, 'person_id' => $person->id, 'topic' => self::MARKER.'Int1', 'confidence_rate' => 0.9]);
        PersonInterest::create(['business_id' => $business->id, 'person_id' => $person->id, 'topic' => self::MARKER.'Int2', 'confidence_rate' => 0.8]);

        return 2;
    }

    public function purge(Business $business): int
    {
        return PersonInterest::where('business_id', $business->id)->where('topic', 'like', self::MARKER.'%')->delete();
    }
}

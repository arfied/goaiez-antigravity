<?php

declare(strict_types=1);

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X132\Models\PersonLink;
use App\Modules\X132\Models\ResolutionEvidence;

class X132Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-132';
    }

    public function fill(Business $business): int
    {
        if (PersonLink::where('business_id', $business->id)->where('confidence_rate', 0.999)->exists()) {
            return 0;
        }

        $canonical = Person::create(['business_id' => $business->id, 'first_name' => self::MARKER.'John', 'last_name' => 'Doe']);
        $linked = Person::create(['business_id' => $business->id, 'first_name' => self::MARKER.'Johnny', 'last_name' => 'Doe']);

        PersonLink::create(['business_id' => $business->id, 'canonical_person_id' => $canonical->id, 'linked_person_id' => $linked->id, 'confidence_rate' => 0.999]);
        
        ResolutionEvidence::create(['business_id' => $business->id, 'canonical_person_id' => $canonical->id, 'field_name' => 'email', 'field_value' => self::MARKER.'evidence1', 'source_provider' => 'test', 'confidence_rate' => 0.99]);
        ResolutionEvidence::create(['business_id' => $business->id, 'canonical_person_id' => $canonical->id, 'field_name' => 'phone', 'field_value' => self::MARKER.'evidence2', 'source_provider' => 'test', 'confidence_rate' => 0.99]);

        return 5;
    }

    public function purge(Business $business): int
    {
        $count = ResolutionEvidence::where('business_id', $business->id)->where('field_value', 'like', self::MARKER.'%')->delete();
        $count += PersonLink::where('business_id', $business->id)->where('confidence_rate', 0.999)->delete();
        $count += Person::where('business_id', $business->id)->where('first_name', 'like', self::MARKER.'%')->delete();
        return $count;
    }
}

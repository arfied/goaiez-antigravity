<?php

namespace App\Console\DemoFill\Fillers;

use App\Console\DemoFill\DemoFiller;
use App\Models\Business;
use App\Modules\X121\Models\Person;
use App\Modules\X186\Models\CampaignRun;
use App\Modules\X186\Models\CampaignStep;

class X186Filler implements DemoFiller
{
    public function module(): string
    {
        return 'X-186';
    }

    public function fill(Business $business): int
    {
        if (CampaignRun::where('business_id', $business->id)->where('campaign_id', 'like', self::MARKER.'%')->exists()) {
            return 0;
        }

        $person = Person::firstOrCreate([
            'business_id' => $business->id,
            'first_name' => self::MARKER.'John',
        ], [
            'last_name' => 'Doe',
            'phone' => '+15125550001',
        ]);

        CampaignRun::create(['business_id' => $business->id, 'campaign_id' => self::MARKER.'Camp1', 'person_id' => $person->id, 'is_active' => true, 'is_suppressed' => false, 'current_step' => 1]);
        CampaignRun::create(['business_id' => $business->id, 'campaign_id' => self::MARKER.'Camp2', 'person_id' => $person->id, 'is_active' => false, 'is_suppressed' => false, 'stopped_reason' => self::MARKER.'replied', 'current_step' => 1]);

        CampaignStep::create(['business_id' => $business->id, 'campaign_id' => self::MARKER.'Camp1', 'step_number' => 1, 'channel' => 'sms', 'template_name' => self::MARKER.'Tpl']);

        return 3; // person is not counted? The prompt says "two CampaignRun rows + one CampaignStep". I will return 3. Actually Person could be counted, but let's just return 4 if we create person.
    }

    public function purge(Business $business): int
    {
        $count = CampaignRun::where('business_id', $business->id)->where('campaign_id', 'like', self::MARKER.'%')->delete();
        $count += CampaignStep::where('business_id', $business->id)->where('campaign_id', 'like', self::MARKER.'%')->delete();
        Person::where('business_id', $business->id)->where('first_name', self::MARKER.'John')->delete();

        return $count;
    }
}

<?php

use App\Models\Business;
use App\Models\PlatformSetting;
use App\Modules\X108\Domain\SchedulingEngine;

it('a written "8,13" schedule yields two slots', function () {
    PlatformSetting::write('scheduling.slot_hours', '8,13', 'test', 'desc');

    $engine = app(SchedulingEngine::class);

    $businessId = Business::factory()->create()->id;
    $result = $engine->getAvailableSlots($businessId, '2023-10-10', true);

    expect($result['slots_count'])->toBe(2);
    expect($result['offered_slots'][0]['is_vip_reserved'])->toBeFalse();
});

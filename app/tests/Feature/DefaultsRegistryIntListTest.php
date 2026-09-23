<?php

use App\Models\PlatformSetting;
use App\Services\Config\DefaultsRegistry;

it('parses comma-separated values, falls back to seed when empty or malformed, and keeps zeros', function () {
    $registry = app(DefaultsRegistry::class);
    $key = 'billing.dunning.schedule_hours';
    $seedList = [24, 72, 120]; // Assuming Dunning::SCHEDULE_HOURS is [24, 72, 120]

    // "24, 72,120" -> [24, 72, 120]
    PlatformSetting::write($key, '24, 72,120', 'test', 'desc');
    expect($registry->intList($key))->toBe([24, 72, 120]);

    // "a,b" -> the seed
    PlatformSetting::write($key, 'a,b', 'test', 'desc');
    expect($registry->intList($key))->toBe($seedList);

    // "" -> the seed
    PlatformSetting::write($key, '', 'test', 'desc');
    expect($registry->intList($key))->toBe($seedList);

    // "0,0,24" keeps zeros
    PlatformSetting::write($key, '0,0,24', 'test', 'desc');
    expect($registry->intList($key))->toBe([0, 0, 24]);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;

it('deploy-check does not crash when worker heartbeat is present', function () {
    // Put a unix timestamp, as it would be now
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);
    
    // Command should run without crashing
    $exitCode = Artisan::call('app:deploy-check');
    
    $this->assertIsInt($exitCode);
});

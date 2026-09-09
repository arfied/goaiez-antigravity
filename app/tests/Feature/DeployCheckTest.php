<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

it('deploy-check asserts on heartbeat age and output', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);

    Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('ok   worker running')
        ->and($output)->toContain('ok   scheduler running')
        ->and($output)->toMatch('/last heartbeat [0-5]s ago/')
        ->and($output)->toMatch('/last tick [0-5]s ago/');
});

it('deploy-check asserts FAIL when worker heartbeat is 400s old', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->subSeconds(400)->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('FAIL worker running');
});

it('deploy-check handles garbage in worker heartbeat', function () {
    Cache::put('goaiez:worker:heartbeat', 'garbage');
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($exitCode)->toBeInt()
        ->and($output)->toContain('FAIL worker running');
});

it('deploy-check handles object in scheduler heartbeat', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', new stdClass);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($exitCode)->toBeInt()
        ->and($output)->toContain('FAIL scheduler running');
});

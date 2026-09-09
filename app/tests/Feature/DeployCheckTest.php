<?php

declare(strict_types=1);

use App\Models\PixelBundleVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

it('deploy-check asserts on heartbeat age and output', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);
    PixelBundleVersion::factory()->create(['sha' => 'abcdef']);

    Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('ok   worker running')
        ->and($output)->toContain('ok   scheduler running')
        ->and($output)->toContain('ok   pixel bundle published')
        ->and($output)->toMatch('/last heartbeat [0-5]s ago/')
        ->and($output)->toMatch('/last tick [0-5]s ago/');
});

it('deploy-check asserts FAIL when worker heartbeat is 400s old', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->subSeconds(400)->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);
    PixelBundleVersion::factory()->create(['sha' => 'abcdef']);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('FAIL worker running');
});

it('deploy-check handles garbage in worker heartbeat', function () {
    Cache::put('goaiez:worker:heartbeat', 'garbage');
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);
    PixelBundleVersion::factory()->create(['sha' => 'abcdef']);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($exitCode)->toBeInt()
        ->and($output)->toContain('FAIL worker running');
});

it('deploy-check handles object in scheduler heartbeat', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', new stdClass);
    PixelBundleVersion::factory()->create(['sha' => 'abcdef']);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($exitCode)->toBeInt()
        ->and($output)->toContain('FAIL scheduler running');
});

it('deploy-check pixel probe fails when missing', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);

    // Ensure no PixelBundleVersion
    DB::table('pixel_bundle_versions')->delete();

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('FAIL pixel bundle published')
        ->and($output)->toContain('pixel:publish');
});

it('deploy-check handles DateTimeInterface in heartbeat correctly', function () {
    Cache::put('goaiez:worker:heartbeat', new DateTimeImmutable('-30 seconds'));
    Cache::put('goaiez:scheduler:heartbeat', new DateTimeImmutable('-30 seconds'));
    PixelBundleVersion::factory()->create(['sha' => 'abcdef']);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('ok   worker running')
        ->and($output)->toContain('ok   scheduler running');
});

it('deploy-check absent heartbeat shows appropriate failure message', function () {
    Cache::forget('goaiez:worker:heartbeat');
    Cache::forget('goaiez:scheduler:heartbeat');
    PixelBundleVersion::factory()->create(['sha' => 'abcdef']);

    $exitCode = Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('NO WORKER HEARTBEAT. Queued jobs are accepted and never run — including the missed-call text-back.')
        ->and($output)->toContain('NO SCHEDULER TICK. 29 scheduled tasks are not firing — token refresh, review reminders, deletions, parked-number release.');
});

it('deploy-check asserts published pixel bundle reads ok', function () {
    Cache::put('goaiez:worker:heartbeat', Carbon::now()->timestamp);
    Cache::put('goaiez:scheduler:heartbeat', Carbon::now()->timestamp);
    PixelBundleVersion::factory()->create(['sha' => 'abcdef']);

    Artisan::call('app:deploy-check');
    $output = Artisan::output();
    expect($output)->toContain('ok   pixel bundle published');
});

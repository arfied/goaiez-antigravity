<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Jobs\Actuation\JudgeSpeedFixJob;
use App\Jobs\ProbeLocationSiteJob;
use App\Jobs\RecomputeProofNumbersJob;
use Illuminate\Support\Facades\DB;

it('reads the standard ladder from the platform settings at backoff time', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'queue.backoff.standard_seconds'],
        ['value' => json_encode('5,10'), 'updated_at' => now()]
    );

    $job = new RecomputeProofNumbersJob(1, []);
    $backoff = $job->backoff();

    expect($backoff)->toHaveCount(2);
    expect($backoff[0])->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(7);
    expect($backoff[1])->toBeGreaterThanOrEqual(7)->toBeLessThanOrEqual(13);
});

it('reads the media ladder from the platform settings at backoff time', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'queue.backoff.media_seconds'],
        ['value' => json_encode('5,10'), 'updated_at' => now()]
    );

    $job = new ProbeLocationSiteJob(1, 1);
    $backoff = $job->backoff();

    expect($backoff)->toHaveCount(2);
    expect($backoff[0])->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(7);
    expect($backoff[1])->toBeGreaterThanOrEqual(7)->toBeLessThanOrEqual(13);
});

it('reads the actuation ladder from the platform settings at backoff time', function () {
    DB::table('platform_settings')->updateOrInsert(
        ['key' => 'queue.backoff.actuation_seconds'],
        ['value' => json_encode('5,10'), 'updated_at' => now()]
    );

    $job = new JudgeSpeedFixJob(1, 1, 1);
    $backoff = $job->backoff();

    expect($backoff)->toHaveCount(2);
    expect($backoff[0])->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(7);
    expect($backoff[1])->toBeGreaterThanOrEqual(7)->toBeLessThanOrEqual(13);
});

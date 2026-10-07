<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\SiteCloneJob;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    config([
        'site_clone.root' => storage_path('framework/testing/clones-'.getmypid()),
    ]);
});

afterEach(function () {
    File::deleteDirectory((string) config('site_clone.root'));
});

it('prunes the directory of a finished job older than the horizon and clears work_dir', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Prune Test',
    ]);

    $dir = config('site_clone.root').'/'.$business->id.'/93-184-216-34';
    File::ensureDirectoryExists($dir);
    File::put($dir.'/test.txt', 'data');

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::DONE,
        'progress' => 100,
        'message' => 'Done.',
        'work_dir' => $dir,
        'finished_at' => CarbonImmutable::now()->subDays(8),
    ]);

    $this->artisan('site-clone:prune-evidence')->assertSuccessful();

    expect(is_dir($dir))->toBeFalse();
    expect($job->fresh()->work_dir)->toBeNull();
});

it('keeps a finished job younger than the horizon', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Prune Test',
    ]);

    $dir = config('site_clone.root').'/'.$business->id.'/93-184-216-34';
    File::ensureDirectoryExists($dir);
    File::put($dir.'/test.txt', 'data');

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::DONE,
        'progress' => 100,
        'message' => 'Done.',
        'work_dir' => $dir,
        'finished_at' => CarbonImmutable::now()->subDays(1),
    ]);

    $this->artisan('site-clone:prune-evidence')->assertSuccessful();

    expect(is_dir($dir))->toBeTrue();
    expect($job->fresh()->work_dir)->toBe($dir);
});

it('never touches a running job', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Prune Test',
    ]);

    $dir = config('site_clone.root').'/'.$business->id.'/93-184-216-34';
    File::ensureDirectoryExists($dir);
    File::put($dir.'/test.txt', 'data');

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::RUNNING,
        'progress' => 50,
        'message' => 'Running...',
        'work_dir' => $dir,
        'started_at' => CarbonImmutable::now()->subDays(30),
        'finished_at' => null,
    ]);

    $this->artisan('site-clone:prune-evidence')->assertSuccessful();

    expect(is_dir($dir))->toBeTrue();
    expect($job->fresh()->work_dir)->toBe($dir);
});

it('refuses a work_dir outside the clone root', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Prune Test',
    ]);

    $dir = sys_get_temp_dir().'/outside-clone-dir-'.getmypid();
    File::ensureDirectoryExists($dir);
    File::put($dir.'/test.txt', 'data');

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::DONE,
        'progress' => 100,
        'message' => 'Done.',
        'work_dir' => $dir,
        'finished_at' => CarbonImmutable::now()->subDays(8),
    ]);

    $this->artisan('site-clone:prune-evidence')
        ->expectsOutputToContain('Refused')
        ->assertSuccessful();

    expect(is_dir($dir))->toBeTrue();
    expect($job->fresh()->work_dir)->toBe($dir);

    File::deleteDirectory($dir);
});

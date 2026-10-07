<?php

declare(strict_types=1);

use App\Jobs\SiteClone\RunSiteCloneJob;
use App\Models\Business;
use App\Models\SiteCloneJob;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Services\SiteClone\SiteCloneJobs;
use App\Support\PlatformCredentials;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config([
        'site_clone.root' => storage_path('framework/testing/clones-'.getmypid()),
        'site_clone.runner' => base_path('tests/Fixtures/site-clone/fake-runner.sh'),
        'credentials.anthropic_api_key' => 'test-key',
    ]);
});

afterEach(function () {
    File::deleteDirectory(config('site_clone.root'));
});

it('runs a successful clone', function () {
    expect(PlatformCredentials::has('anthropic_api_key'))->toBeTrue();

    $user = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $res = app(SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);
    $jobId = $res['job_id'];

    (new RunSiteCloneJob($jobId, $business->id))->handle(app(SiteCloneJobs::class), app(DefaultsRegistry::class));

    $row = SiteCloneJob::find($jobId);
    expect($row->status)->toBe(SiteCloneJob::DONE);
    expect($row->progress)->toBe(100);
    expect($row->message)->toBe('Done.');
    expect($row->cost_usd)->toEqual(0.1234);
    expect($row->pid)->not->toBeNull();

    $workDir = $row->work_dir;
    expect(str_starts_with($workDir, config('site_clone.root')))->toBeTrue();
    expect(file_exists($workDir.'/evidence/bundle.json'))->toBeTrue();

    foreach ($row->logs as $log) {
        expect(in_array($log, SiteCloneJobs::MESSAGES))->toBeTrue();
    }
});

it('fails the clone and cleans up on runner failure', function () {
    $user = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $res = app(SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);

    config(['site_clone.runner' => base_path('tests/Fixtures/site-clone/fake-runner-fail.sh')]);
    (new RunSiteCloneJob($res['job_id'], $business->id))->handle(app(SiteCloneJobs::class), app(DefaultsRegistry::class));

    $row = SiteCloneJob::find($res['job_id']);
    expect($row->status)->toBe(SiteCloneJob::FAILED);
    expect($row->error)->toBe('Clone failed. Please try again.');
    expect(str_contains($row->internal_error, 'boom'))->toBeTrue();
    expect(file_exists($row->work_dir))->toBeFalse();
});

it('times out and cleans up', function () {
    $user = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $res = app(SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);

    app(DefaultsRegistry::class)->set('sites.clone.timeout_seconds', 3, 'test');

    config(['site_clone.runner' => base_path('tests/Fixtures/site-clone/fake-runner-hang.sh')]);
    (new RunSiteCloneJob($res['job_id'], $business->id))->handle(app(SiteCloneJobs::class), app(DefaultsRegistry::class));

    $row = SiteCloneJob::find($res['job_id']);
    expect($row->status)->toBe(SiteCloneJob::FAILED);
    expect($row->error)->toBe('The clone took too long and was stopped. Please try again.');
    expect(file_exists($row->work_dir))->toBeFalse();
    expect(posix_kill($row->pid, 0))->toBeFalse();
});

it('returns if cancelled before start', function () {
    $user = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $res = app(SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);
    app(SiteCloneJobs::class)->cancel($business->id, $res['job_id']);

    (new RunSiteCloneJob($res['job_id'], $business->id))->handle(app(SiteCloneJobs::class), app(DefaultsRegistry::class));

    $row = SiteCloneJob::find($res['job_id']);
    expect($row->work_dir)->toBeNull();
    expect($row->status)->toBe(SiteCloneJob::CANCELLED);
});

it('fails on no credential', function () {
    config(['credentials.anthropic_api_key' => null]);
    $user = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $res = app(SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);

    (new RunSiteCloneJob($res['job_id'], $business->id))->handle(app(SiteCloneJobs::class), app(DefaultsRegistry::class));

    $row = SiteCloneJob::find($res['job_id']);
    expect($row->status)->toBe(SiteCloneJob::FAILED);
    expect($row->error)->toBe('Cloning is not switched on for this platform yet.');
});

it('recovers on read', function () {
    $user = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::RUNNING,
        'progress' => 50,
        'message' => 'cloning',
        'heartbeat_at' => now()->subMinutes(10),
        'pid' => 999999,
    ]);

    $active = app(SiteCloneJobs::class)->active($business->id);
    expect($active)->toBeNull();

    $row = SiteCloneJob::find($job->id);
    expect($row->status)->toBe(SiteCloneJob::FAILED);
    expect($row->error)->toBe('The clone was interrupted. Please try again.');
});

it('request dispatches to queue', function () {
    Queue::fake();
    $user = User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    app(SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);

    Queue::assertPushedOn('clone', RunSiteCloneJob::class);
});

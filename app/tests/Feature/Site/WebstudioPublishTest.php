<?php

use App\Jobs\Webstudio\PublishWebstudioSiteJob;
use App\Models\SiteCloneJob;
use App\Models\WebstudioSite;
use App\Modules\X157\Actions\PlatformSiteAddressAction;
use App\Modules\X157\Actions\StaticSiteDeployAction;
use App\Modules\X157\Models\Deployment;
use App\Services\Webstudio\WebstudioSites;
use App\Support\Tenancy;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Queue::fake();
    Storage::fake('local');
    config([
        'site_clone.root' => storage_path('framework/testing/clones-'.getmypid()),
        'site_clone.runner' => base_path('tests/Fixtures/site-clone/fake-runner.sh'),
        'site_clone.publisher' => base_path('tests/Fixtures/site-clone/fake-publisher.sh'),
        'credentials.anthropic_api_key' => 'test-key',
    ]);
});

afterEach(function () {
    File::deleteDirectory(config('site_clone.root'));
});

it('publishes a site: the publisher output becomes a static deployment and the row records it', function () {
    $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    $site = WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com']);

    $res = app(WebstudioSites::class)->requestPublish($business->id, $site->id);
    expect($res)->toBe(['status' => 'queued', 'site_id' => $site->id]);

    Queue::assertPushedOn('clone', PublishWebstudioSiteJob::class);

    (new PublishWebstudioSiteJob($site->id, $business->id))->handle(app(WebstudioSites::class), app(StaticSiteDeployAction::class), app(PlatformSiteAddressAction::class));

    $site->refresh();
    expect($site->publish_status)->toBe(WebstudioSite::PUBLISHED)
        ->and($site->deploy_hash)->toMatch('/^deploy_[A-Za-z0-9]{16}$/')
        ->and($site->deployment_id)->not->toBeNull();

    $deployment = Deployment::find($site->deployment_id);
    expect($deployment->kind)->toBe('static')
        ->and($deployment->status)->toBe('deployed');

    $this->get("/sites/{$business->id}/{$site->deploy_hash}/")
        ->assertOk()
        ->assertSee('Published 7733');

    $pubDir = rtrim((string) config('site_clone.root'), '/').'/'.$business->id.'/publish/'.$site->deploy_hash;
    expect(is_dir($pubDir))->toBeFalse();

    expect(in_array($site->publish_message, WebstudioSites::MESSAGES))->toBeTrue();
});

it('fails honestly when the publisher fails', function () {
    config(['site_clone.publisher' => base_path('tests/Fixtures/site-clone/fake-publisher-fail.sh')]);

    $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    $site = WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com', 'publish_status' => WebstudioSite::PUBLISHING]);

    (new PublishWebstudioSiteJob($site->id, $business->id))->handle(app(WebstudioSites::class), app(StaticSiteDeployAction::class), app(PlatformSiteAddressAction::class));

    $site->refresh();
    expect($site->publish_status)->toBe(WebstudioSite::FAILED)
        ->and($site->publish_message)->toBe(WebstudioSites::MESSAGES['failed'])
        ->and($site->publish_error)->toContain('boom');

    expect(Deployment::count())->toBe(0);

    // Find the deploy hash if any was generated, or just check directory empty
    $pubBase = rtrim((string) config('site_clone.root'), '/').'/'.$business->id.'/publish';
    if (File::exists($pubBase)) {
        expect(File::directories($pubBase))->toBeEmpty();
    } else {
        expect(true)->toBeTrue(); // Directory doesn't exist, which is also fine.
    }
});

it('refuses a second publish while one is running', function () {
    $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    $site = WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com']);

    app(WebstudioSites::class)->requestPublish($business->id, $site->id);
    $res = app(WebstudioSites::class)->requestPublish($business->id, $site->id);
    expect($res)->toBe(['status' => 'refused', 'reason' => 'already_publishing']);

    $sentence = app(WebstudioSites::class)->ownerSentence('already_publishing');
    expect($sentence)->toBe(WebstudioSites::REFUSALS['already_publishing']);
});

it('refuses a site of another tenant', function () {
    $userA = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $userB = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $bizA = $this->provisionTenant(['owner_user_id' => $userA->id, 'name' => 'Tenant A']);
    $bizB = $this->provisionTenant(['owner_user_id' => $userB->id, 'name' => 'Tenant B']);

    Tenancy::set((int) $bizB->id);
    $siteOfB = WebstudioSite::create(['business_id' => $bizB->id, 'project_id' => 'proj-b', 'editor_token' => 'tok-b', 'title' => 'b.example']);

    Tenancy::set((int) $bizA->id);
    $res = app(WebstudioSites::class)->requestPublish($bizA->id, $siteOfB->id);

    expect($res)->toBe(['status' => 'refused', 'reason' => 'no_site']);
});

it('creates a site row from a finished clone job and not from one without a project', function () {
    $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    $job = SiteCloneJob::create(['business_id' => $business->id, 'status' => SiteCloneJob::DONE, 'webstudio_project_id' => 'proj-1', 'editor_token' => 'tok-1', 'host' => 'example.com', 'url' => 'https://example.com/', 'slug' => 'example-com', 'user_id' => $user->id]);

    $site = app(WebstudioSites::class)->fromCloneJob($job);
    expect($site)->not->toBeNull()
        ->and($site->title)->toBe('example.com')
        ->and($site->source)->toBe(WebstudioSite::SOURCE_CLONE)
        ->and($site->site_clone_job_id)->toBe($job->id);

    $site2 = app(WebstudioSites::class)->fromCloneJob($job);
    expect($site2->id)->toBe($site->id);

    $jobNoProj = SiteCloneJob::create(['business_id' => $business->id, 'status' => SiteCloneJob::DONE, 'host' => 'no-proj.com', 'url' => 'https://no-proj.com/', 'slug' => 'no-proj-com', 'user_id' => $user->id]);
    $siteNoProj = app(WebstudioSites::class)->fromCloneJob($jobNoProj);
    expect($siteNoProj)->toBeNull();
});

it('recovers a stale publishing row', function () {
    $user = \App\Models\User::factory()->create(['role' => \App\Enums\UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    $site = WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com', 'publish_status' => WebstudioSite::PUBLISHING, 'publish_started_at' => now()->subMinutes(30)]);

    app(WebstudioSites::class)->recoverStale($business->id);
    $site->refresh();

    expect($site->publish_status)->toBe(WebstudioSite::FAILED)
        ->and($site->publish_message)->toBe(WebstudioSites::MESSAGES['interrupted']);
});

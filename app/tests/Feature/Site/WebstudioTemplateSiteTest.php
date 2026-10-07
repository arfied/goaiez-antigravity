<?php

use App\Enums\UserRole;
use App\Jobs\Webstudio\CreateWebstudioSiteFromTemplateJob;
use App\Models\User;
use App\Models\WebstudioSite;
use App\Models\WebstudioTemplateProject;
use App\Services\Webstudio\WebstudioSites;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    config([
        'site_clone.project_script' => base_path('tests/Fixtures/site-clone/fake-project.mjs'),
        'credentials.webstudio_auth_secret' => 'x',
    ]);
});

it('creates a site from a template', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    WebstudioTemplateProject::create([
        'template_id' => 'trades-pro',
        'project_id' => 'proj-tpl-1',
        'label' => 'Trades Pro',
        'builder_origin' => 'https://wstd.dev:5174',
        'imported_at' => now(),
    ]);

    $res = app(WebstudioSites::class)->fromTemplate($business->id, 'trades-pro', 'My New Site');
    expect($res['status'])->toBe('queued');

    $siteId = $res['site_id'];
    $site = WebstudioSite::find($siteId);

    expect($site->creation_status)->toBe(WebstudioSite::CREATING)
        ->and($site->source)->toBe(WebstudioSite::SOURCE_TEMPLATE)
        ->and($site->template_id)->toBe('trades-pro');

    Queue::assertPushedOn('clone', CreateWebstudioSiteFromTemplateJob::class);

    config(['credentials.webstudio_auth_secret' => 'secret-here']);

    (new CreateWebstudioSiteFromTemplateJob($site->id, $business->id))->handle(app(WebstudioSites::class));

    $site->refresh();
    expect($site->creation_status)->toBe(WebstudioSite::READY)
        ->and($site->project_id)->toBe('fake-clone-proj-tpl-1')
        ->and($site->editor_token)->toBe('fake-token')
        ->and(app(WebstudioSites::class)->editorUrl($site))->toContain('authToken=fake-token');
});

it('fails honestly when the script fails', function () {
    config(['site_clone.project_script' => base_path('tests/Fixtures/site-clone/fake-project-fail.mjs')]);

    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    WebstudioTemplateProject::create([
        'template_id' => 'trades-pro',
        'project_id' => 'proj-tpl-1',
        'label' => 'Trades Pro',
        'builder_origin' => 'https://wstd.dev:5174',
        'imported_at' => now(),
    ]);

    $site = WebstudioSite::create([
        'business_id' => $business->id,
        'source' => WebstudioSite::SOURCE_TEMPLATE,
        'template_id' => 'trades-pro',
        'title' => 'My New Site',
        'creation_status' => WebstudioSite::CREATING,
    ]);

    config(['credentials.webstudio_auth_secret' => 'secret-here']);

    (new CreateWebstudioSiteFromTemplateJob($site->id, $business->id))->handle(app(WebstudioSites::class));

    $site->refresh();
    expect($site->creation_status)->toBe(WebstudioSite::CREATION_FAILED)
        ->and($site->creation_error)->toContain('boom')
        ->and(app(WebstudioSites::class)->editorUrl($site))->toBeNull();
});

it('refuses an unknown template', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    $res = app(WebstudioSites::class)->fromTemplate($business->id, 'unknown-template', 'My New Site');
    expect($res['status'])->toBe('refused')
        ->and($res['reason'])->toBe('no_template');

    expect(WebstudioSite::count())->toBe(0);
});

it('refuses a second fromTemplate while one is creating', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    WebstudioTemplateProject::create([
        'template_id' => 'trades-pro',
        'project_id' => 'proj-tpl-1',
        'label' => 'Trades Pro',
        'builder_origin' => 'https://wstd.dev:5174',
        'imported_at' => now(),
    ]);

    app(WebstudioSites::class)->fromTemplate($business->id, 'trades-pro', 'My New Site');
    $res = app(WebstudioSites::class)->fromTemplate($business->id, 'trades-pro', 'Another Site');

    expect($res['status'])->toBe('refused')
        ->and($res['reason'])->toBe('already_creating');
});

it('recovers a stale creating row', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant(['owner_user_id' => $user->id]);

    $site = WebstudioSite::create([
        'business_id' => $business->id,
        'source' => WebstudioSite::SOURCE_TEMPLATE,
        'template_id' => 'trades-pro',
        'title' => 'My New Site',
        'creation_status' => WebstudioSite::CREATING,
        'created_at' => now()->subMinutes(30),
    ]);

    app(WebstudioSites::class)->recoverStale($business->id);
    $site->refresh();

    expect($site->creation_status)->toBe(WebstudioSite::CREATION_FAILED)
        ->and($site->creation_message)->toBe(WebstudioSites::MESSAGES['creation_failed']);
});

it('isolates tenants in all()', function () {
    $userA = User::factory()->create(['role' => UserRole::Owner]);
    $userB = User::factory()->create(['role' => UserRole::Owner]);
    $bizA = $this->provisionTenant(['owner_user_id' => $userA->id, 'name' => 'Tenant A']);
    $bizB = $this->provisionTenant(['owner_user_id' => $userB->id, 'name' => 'Tenant B']);

    Tenancy::set((int) $bizA->id);
    $siteOfA = WebstudioSite::create(['business_id' => $bizA->id, 'source' => WebstudioSite::SOURCE_TEMPLATE, 'title' => 'a.example', 'creation_status' => WebstudioSite::CREATING]);

    Tenancy::set((int) $bizB->id);
    $allB = app(WebstudioSites::class)->all($bizB->id);

    expect($allB->isEmpty())->toBeTrue();
});

<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Jobs\Webstudio\PublishWebstudioSiteJob;
use App\Livewire\Site\SiteClone;
use App\Models\SiteCloneJob;
use App\Models\User;
use App\Models\WebstudioSite;
use App\Services\SiteClone\SiteCloneJobs;
use App\Services\Webstudio\WebstudioSites;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('renders for an owner', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);
    $response = $this->get(route('site.clone'));

    $response->assertOk()
        ->assertSee('Website address to clone')
        ->assertSee('Start cloning')
        ->assertDontSee('internal_error');
});

test('refuses a no-tenant request', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $this->actingAs($user);
    $this->withoutExceptionHandling();

    $refused = false;
    try {
        $this->get(route('site.clone'));
    } catch (TenantNotResolved $e) {
        $refused = true;
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
        $refused = true;
    }

    expect($refused)->toBeTrue();
});

test('a private address is refused and no row is written', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    Livewire::test(SiteClone::class)
        ->set('url', 'http://127.0.0.1/')
        ->set('attested', true)
        ->call('start')
        ->assertSee('That address is not a public website');

    expect(SiteCloneJob::count())->toBe(0);
});

test('a public address queues one job, and a second start is refused with the same job', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    Livewire::test(SiteClone::class)
        ->set('url', 'http://93.184.216.34/')
        ->set('attested', true)
        ->call('start');

    expect(SiteCloneJob::count())->toBe(1);

    $job = SiteCloneJob::first();
    expect($job->status)->toBe('queued')
        ->and($job->host)->toBe('93.184.216.34')
        ->and($job->slug)->toBe('93-184-216-34')
        ->and($job->message)->toBe('Queued — your clone starts in a moment.');

    Livewire::test(SiteClone::class)
        ->set('url', 'http://1.1.1.1/')
        ->set('attested', true)
        ->call('start')
        ->assertSee('A clone is already running');

    expect(SiteCloneJob::count())->toBe(1);
});

test('cancel', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    $component = Livewire::test(SiteClone::class)
        ->set('url', 'http://93.184.216.34/')
        ->set('attested', true)
        ->call('start');

    $component->call('cancel')
        ->assertSee('Website address to clone');

    $job = SiteCloneJob::first();
    expect($job->status)->toBe('cancelled')
        ->and($job->finished_at)->not->toBeNull();
});

test('the Studio shows the banner while a job is active', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    app(SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);

    $this->get(route('site.studio'))
        ->assertSee('Your website clone is in progress');

    app(SiteCloneJobs::class)->cancel($business->id, SiteCloneJob::first()->id);

    $this->get(route('site.studio'))
        ->assertDontSee('Your website clone is in progress');
});

test('an unattested request is refused and no row is written', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    Livewire::test(SiteClone::class)
        ->set('url', 'http://93.184.216.34/')
        ->set('attested', false)
        ->call('start')
        ->assertSee("Confirm you may use this website's content");

    expect(SiteCloneJob::count())->toBe(0);
});

test('a bad address is refused and no row is written', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    Livewire::test(SiteClone::class)
        ->set('url', 'ftp://example.com/')
        ->set('attested', true)
        ->call('start')
        ->assertSee('Enter a full web address starting with http:// or https://');

    expect(SiteCloneJob::count())->toBe(0);
});

test('the editor action redirects to the builder for a done job with both fields', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    config(['site_clone.builder_origin' => 'https://wstd.dev:5174']);

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::DONE,
        'progress' => 100,
        'message' => 'Done.',
        'webstudio_project_id' => 'fake-uuid-456',
        'editor_token' => 'fake-token-value-abc123',
    ]);

    Livewire::test(SiteClone::class)
        ->call('openEditor', $job->id)
        ->assertRedirect('https://p-fake-uuid-456.wstd.dev:5174/?authToken=fake-token-value-abc123');
});

test('the editor action does not redirect for a failed job', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::FAILED,
        'progress' => 100,
        'message' => 'Failed.',
        'webstudio_project_id' => 'fake-uuid-456',
        'editor_token' => 'fake-token-value-abc123',
    ]);

    Livewire::test(SiteClone::class)
        ->call('openEditor', $job->id)
        ->assertNoRedirect();
});

test('the rendered screen shows Open editor and does not contain authToken', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    $job = SiteCloneJob::create([
        'business_id' => $business->id,
        'user_id' => $user->id,
        'url' => 'http://93.184.216.34/',
        'host' => '93.184.216.34',
        'slug' => '93-184-216-34',
        'status' => SiteCloneJob::DONE,
        'progress' => 100,
        'message' => 'Done.',
        'webstudio_project_id' => 'fake-uuid-456',
        'editor_token' => 'fake-token-value-abc123',
    ]);

    $this->get(route('site.clone'))
        ->assertSee('Open editor')
        ->assertDontSee('authToken', false);
});

test('the screen lists a site with its message and a Publish button, and not before one exists', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);
    $this->get(route('site.clone'))->assertOk()->assertDontSee('Your sites');

    WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com']);

    $this->get(route('site.clone'))
        ->assertSee('Your sites')
        ->assertSee('example.com')
        ->assertSee('Not published yet')
        ->assertSee('Publish')
        ->assertDontSee('View site')
        ->assertDontSee('authToken', false);
});

test('publish queues the job and the row turns to publishing', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);
    $site = WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com']);

    Queue::fake();
    Livewire::test(SiteClone::class)->call('publish', $site->id)->assertSee('Publishing…');
    Queue::assertPushedOn('clone', PublishWebstudioSiteJob::class);

    expect($site->refresh()->publish_status)->toBe('publishing');
    Livewire::test(SiteClone::class)->assertDontSee('>Publish<', false);
});

test('a published site shows View site pointing at the platform address', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);
    WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com', 'publish_status' => 'published', 'deploy_hash' => 'deploy_abcdefghijklmnop', 'published_at' => now()]);

    $this->get(route('site.clone'))
        ->assertSee('View site')
        ->assertSee('/sites/'.$business->id.'/deploy_abcdefghijklmnop', false)
        ->assertDontSee('authToken', false);
});

test('open site editor redirects to the builder', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);
    $site = WebstudioSite::create(['business_id' => $business->id, 'project_id' => 'proj-'.Str::random(8), 'editor_token' => 'tok-x', 'title' => 'example.com']);

    config(['site_clone.builder_origin' => 'https://wstd.dev:5174']);

    Livewire::test(SiteClone::class)
        ->call('openSiteEditor', $site->id)
        ->assertRedirect('https://p-'.$site->project_id.'.wstd.dev:5174/?authToken=tok-x');
});

test('a site of another tenant cannot be published from this screen', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    $userB = User::factory()->create(['role' => UserRole::Owner]);
    $bizB = $this->provisionTenant(['owner_user_id' => $userB->id, 'name' => 'Tenant B']);
    Tenancy::set((int) $bizB->id);
    $siteOfB = WebstudioSite::create(['business_id' => $bizB->id, 'project_id' => 'proj-b', 'editor_token' => 'tok-b', 'title' => 'b.example']);
    Tenancy::set((int) $business->id);

    Queue::fake();
    Livewire::test(SiteClone::class)->call('publish', $siteOfB->id)->assertSee('That site was not found.');
    Queue::assertNothingPushed();

    Tenancy::set((int) $bizB->id);
    expect($siteOfB->refresh()->publish_status)->toBe('never');
});

test('the screen renders a creating site with its message and no editor or publish buttons', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    WebstudioSite::create(['business_id' => $business->id, 'project_id' => null, 'editor_token' => null, 'title' => 'Trades Pro site',
        'source' => WebstudioSite::SOURCE_TEMPLATE, 'template_id' => 'trades-pro',
        'creation_status' => WebstudioSite::CREATING, 'creation_message' => WebstudioSites::MESSAGES['creating']]);

    $this->get(route('site.clone'))
        ->assertSee(WebstudioSites::MESSAGES['creating'])
        ->assertDontSee('Open editor')
        ->assertDontSee('>Publish<', false);
});

test('the screen renders a failed creating site with its message and no editor or publish buttons', function () {
    $user = User::factory()->create(['role' => UserRole::Owner]);
    $business = $this->provisionTenant([
        'owner_user_id' => $user->id,
        'name' => 'Studio Test Business',
    ]);

    $this->actingAs($user);

    WebstudioSite::create(['business_id' => $business->id, 'project_id' => null, 'editor_token' => null, 'title' => 'Trades Pro site',
        'source' => WebstudioSite::SOURCE_TEMPLATE, 'template_id' => 'trades-pro',
        'creation_status' => WebstudioSite::CREATION_FAILED, 'creation_message' => WebstudioSites::MESSAGES['creation_failed']]);

    $this->get(route('site.clone'))
        ->assertSee(WebstudioSites::MESSAGES['creation_failed'])
        ->assertDontSee('Open editor')
        ->assertDontSee('>Publish<', false);
});

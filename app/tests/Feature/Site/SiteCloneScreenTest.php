<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\UserRole;
use App\Exceptions\TenantNotResolved;
use App\Livewire\Site\SiteClone;
use App\Models\SiteCloneJob;
use App\Models\User;
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

    app(\App\Services\SiteClone\SiteCloneJobs::class)->request($business->id, $user->id, 'http://93.184.216.34/', true);

    $this->get(route('site.studio'))
        ->assertSee('Your website clone is in progress');

    app(\App\Services\SiteClone\SiteCloneJobs::class)->cancel($business->id, SiteCloneJob::first()->id);

    $this->get(route('site.studio'))
        ->assertDontSee('Your website clone is in progress');
});

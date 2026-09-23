<?php

declare(strict_types=1);

use App\Enums\AutopilotActionType;
use App\Enums\UserRole;
use App\Livewire\Account\Locations;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\User;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

uses(RefreshesTenantDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->owner = User::factory()->create(['role' => UserRole::Owner]);
    $this->biz = TestCase::provisionTenant(['owner_user_id' => $this->owner->id]);
    $this->actingAs($this->owner);
    Tenancy::setUser($this->owner->id);
    Tenancy::set((int) $this->biz->id);

    Mail::fake();
    Notification::fake();
});

it('shows the locations console', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create(['name' => 'Acme Test Location 239']);

    $this->get(route('account.locations'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertSee('Acme Test Location 239');
});

it('refuses staff on get and mount', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    $this->get(route('account.locations'))->assertForbidden();

    Livewire::actingAs($staff)->test(Locations::class)->assertForbidden();
});

it('refuses another tenants location with 404', function () {
    $otherBiz = TestCase::provisionTenant();
    Tenancy::set((int) $otherBiz->id);
    $otherLocation = Location::factory()->create();
    Tenancy::set((int) $this->biz->id);

    Livewire::test(Locations::class)->call('edit', $otherLocation->id)->assertNotFound();
    Livewire::test(Locations::class)->call('editDetails', $otherLocation->id)->assertNotFound();
    Livewire::test(Locations::class)->call('editAbout', $otherLocation->id)->assertNotFound();
    Livewire::test(Locations::class)->call('showSign', $otherLocation->id)->assertNotFound();
});

it('updates contact details', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();

    Livewire::test(Locations::class)
        ->call('editDetails', $location->id)
        ->set('statedPhone', '212-555-1234')
        ->set('statedAddress', '123 Test St')
        ->call('saveDetails');

    $location->refresh();
    expect($location->primary_phone)->toBe('212-555-1234')
        ->and($location->address)->toBe('123 Test St')
        ->and($location->primary_phone_confirmed_at)->not->toBeNull()
        ->and($location->address_confirmed_at)->not->toBeNull();

    $this->get(route('account.locations'))
        ->assertSee('212-555-1234')
        ->assertSee('123 Test St');
});

it('updates the about page address', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    Livewire::test(Locations::class)
        ->call('editAbout', $location->id)
        ->set('pastedAboutUrl', 'https://example.com/about-us')
        ->call('saveAbout');

    $location->refresh();
    expect($location->about_url)->toBe('https://example.com/about-us')
        ->and($location->about_url_confirmed_at)->not->toBeNull();
});

it('confirms the website address', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();

    Livewire::test(Locations::class)
        ->call('edit', $location->id)
        ->set('pastedUrl', 'https://example.com')
        ->call('review')
        ->assertHasNoErrors()
        ->call('confirmWebsite');

    $location->refresh();
    expect($location->website_url)->toBe('https://example.com')
        ->and($location->website_confirmed_at)->not->toBeNull();
});

it('generates a review sign code', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create();

    FeedbackPage::create([
        'business_id' => $this->biz->id,
        'location_id' => $location->id,
        'slug' => 'test-slug',
    ]);

    Livewire::test(Locations::class)
        ->call('showSign', $location->id);

    $this->assertDatabaseHas('activity_feed', [
        'business_id' => $this->biz->id,
        'location_id' => $location->id,
        'action_type' => AutopilotActionType::QrGenerated->value,
    ]);
});

it('connects and disconnects wordpress', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    Http::fake([
        'example.com/wp-json/' => Http::response(['namespaces' => ['wp/v2']]),
        'example.com/?rest_route=/' => Http::response(['namespaces' => ['wp/v2']]),
        'example.com/wp-json/wp/v2/users/me*' => Http::response([
            'id' => 1,
            'roles' => ['editor'],
            'capabilities' => [
                'edit_posts' => true,
                'edit_others_posts' => true,
                'edit_published_posts' => true,
                'edit_pages' => true,
                'edit_others_pages' => true,
                'edit_published_pages' => true,
            ],
        ]),
        'example.com/wp-json/wp/v2/plugins*' => Http::response([], 401),
        'example.com/wp-json/wp/v2/users/me/application-passwords/introspect' => Http::response([
            'uuid' => 'test-uuid',
        ]),
        'example.com/wp-json/wp/v2/users/me/application-passwords/test-uuid' => Http::response([]),
        'example.com*' => Http::response([]),
    ]);

    Livewire::test(Locations::class)
        ->call('beginConnect', $location->id)
        ->set('wpUsername', 'admin')
        ->set('wpPassword', '1234 5678 9012 3456 7890 1234')
        ->call('connectWordPress')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('wordpress_credentials', [
        'location_id' => $location->id,
        'username' => 'admin',
    ]);

    Livewire::test(Locations::class)
        ->call('disconnectWordPress', $location->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('wordpress_credentials', [
        'location_id' => $location->id,
    ]);
});

it('handles failing wordpress connect', function () {
    $location = Location::factory()->forBusiness($this->biz->id)->create([
        'website_url' => 'https://example.com',
        'website_confirmed_at' => now(),
    ]);

    Http::fake([
        'example.com/wp-json/' => Http::response(['namespaces' => ['wp/v2']]),
        'example.com/wp-json/wp/v2/users/me*' => Http::response([], 401),
        'example.com*' => Http::response([]),
    ]);

    Livewire::test(Locations::class)
        ->call('beginConnect', $location->id)
        ->set('wpUsername', 'admin')
        ->set('wpPassword', '1234 5678 9012 3456 7890 1234')
        ->call('connectWordPress')
        ->assertHasErrors(['wpUsername']);

    $this->assertDatabaseMissing('wordpress_credentials', [
        'location_id' => $location->id,
    ]);
});

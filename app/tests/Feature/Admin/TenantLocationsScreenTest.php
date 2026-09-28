<?php

declare(strict_types=1);

use App\Enums\PaymentGateway;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Livewire\Admin\TenantLocations;
use App\Models\AuditLogEntry;
use App\Models\Business;
use App\Models\Location;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Subscriptions;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    $this->admin = User::factory()->withSecondFactor()->create(['role' => UserRole::SuperAdmin]);
    $this->owner = User::factory()->create();
    $this->business = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'First Diner',
    ]);

    Tenancy::actingAs($this->business->id, function () {
        $this->location = Location::factory()->create(['name' => 'Downtown Location']);
    });
});

test('a real GET resolves the screen and the admin shell', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.tenant-locations'))
        ->assertOk()
        ->assertSee('Internal Platform Console');
});

test('an Owner gets the measured status', function (): void {
    $this->actingAs($this->owner)
        ->get(route('admin.tenant-locations'))
        ->assertForbidden();
});

test('one tenant location renders its distinctive name, but the tenant business name is missing (FINDING)', function (): void {
    $html = Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class, ['businessId' => $this->business->id])
        ->set('lookup', (string) $this->business->id)
        ->call('lookUp')
        ->html();

    expect($html)->toContain($this->location->name)
        ->and($html)->not->toContain($this->business->name);
});

test('extending the trial moves the no-card trial end for that account only', function (): void {
    $businessA = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'Business 4939',
    ]);
    $businessB = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'Business 4940',
    ]);

    Business::query()->whereKey($businessA->id)->update(['created_at' => now()->subDays(30)]);
    Business::query()->whereKey($businessB->id)->update(['created_at' => now()->subDays(30)]);
    $businessA->created_at = now()->subDays(30);
    $businessB->created_at = now()->subDays(30);

    Tenancy::actingAs($businessA->id, fn () => (new Subscriptions)->openPendingSignup($businessA));
    Tenancy::actingAs($businessB->id, fn () => (new Subscriptions)->openPendingSignup($businessB));

    $subs = new Subscriptions;

    Tenancy::actingAs($businessA->id, fn () => expect($subs->isEntitled($businessA))->toBeFalse());

    Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class)
        ->set('lookup', (string) $businessA->id)
        ->call('lookUp')
        ->set('trialUntil', now()->addDays(30)->toDateString())
        ->call('extendTrial')
        ->assertHasNoErrors();

    Tenancy::actingAs($businessA->id, function () use ($subs, $businessA) {
        expect($subs->isEntitled($businessA))->toBeTrue()
            ->and($subs->noCardTrialEndsAt($businessA, $subs->for($businessA))->toDateString())->toBe(now()->addDays(30)->toDateString());
    });

    Tenancy::actingAs($businessB->id, fn () => expect($subs->isEntitled($businessB))->toBeFalse());

    Tenancy::actingAs($businessA->id, function () {
        expect(AuditLogEntry::query()->where('action', 'subscription.no_card_trial_extended')->exists())->toBeTrue();
    });
});

test('a past date is refused and writes nothing', function (): void {
    $businessA = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'Business 4939 Past',
    ]);
    Tenancy::actingAs($businessA->id, fn () => (new Subscriptions)->openPendingSignup($businessA));

    Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class)
        ->set('lookup', (string) $businessA->id)
        ->call('lookUp')
        ->set('trialUntil', now()->subDay()->toDateString())
        ->call('extendTrial')
        ->assertHasErrors('trialUntil');

    $sub = Tenancy::actingAs($businessA->id, fn () => Subscription::first());
    expect($sub->no_card_trial_extended_until)->toBeNull();
});

test('an account that is not on a no-card trial is refused', function (): void {
    $businessA = Business::provision([
        'owner_user_id' => $this->owner->id,
        'name' => 'Business 4940 Active',
    ]);
    Tenancy::actingAs($businessA->id, fn () => (new Subscriptions)->openPendingSignup($businessA));

    Tenancy::actingAs($businessA->id, function () {
        DB::table('subscriptions')->update([
            'status' => SubscriptionStatus::Active,
            'gateway' => PaymentGateway::Stripe,
            'stripe_subscription_id' => 'sub_123',
            'stripe_customer_id' => 'cus_123',
        ]);
    });

    Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class)
        ->set('lookup', (string) $businessA->id)
        ->call('lookUp')
        ->set('trialUntil', now()->addDays(30)->toDateString())
        ->call('extendTrial')
        ->assertHasErrors(['trialUntil' => 'This account is not on a no-card trial, so there is no trial to extend.']);
});

test('looking up by the owner\'s email opens that business', function (): void {
    $owner = User::factory()->create(['email' => 'owner4941@example.test']);
    $business = Business::provision([
        'owner_user_id' => $owner->id,
        'name' => 'Distinctive Biz 4941',
    ]);
    Tenancy::actingAs($business->id, fn () => Location::factory()->create(['name' => 'Location 4941']));

    $html = Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class)
        ->set('lookup', 'owner4941@example.test')
        ->call('lookUp')
        ->assertSet('businessId', $business->id)
        ->html();

    expect($html)->toContain('Location 4941');
});

test('an email that owns two businesses lists both and choose opens one', function (): void {
    $owner = User::factory()->create(['email' => 'owner4942@example.test']);
    $businessA = Business::provision([
        'owner_user_id' => $owner->id,
        'name' => 'Distinctive Biz A 4942',
    ]);
    $businessB = Business::provision([
        'owner_user_id' => $owner->id,
        'name' => 'Distinctive Biz B 4942',
    ]);

    $component = Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class)
        ->set('lookup', 'owner4942@example.test')
        ->call('lookUp')
        ->assertSet('businessId', null);

    $html = $component->html();
    expect($html)->toContain('Distinctive Biz A 4942')
        ->and($html)->toContain('Distinctive Biz B 4942');

    $component->call('choose', $businessB->id)
        ->assertSet('businessId', $businessB->id);
});

test('an email nobody owns a business with is refused and shows nothing', function (): void {
    $owner = User::factory()->create(['email' => 'nobody4943@example.test']);
    $otherOwner = User::factory()->create(['email' => 'other@example.test']);
    $otherBusiness = Business::provision([
        'owner_user_id' => $otherOwner->id,
        'name' => 'Distinctive Biz Other 4943',
    ]);

    $component = Livewire\Livewire::actingAs($this->admin)
        ->test(TenantLocations::class)
        ->set('lookup', 'nobody4943@example.test')
        ->call('lookUp')
        ->assertSet('businessId', null)
        ->assertSet('ownedChoices', []);

    $html = $component->html();
    expect($html)->not->toContain('Distinctive Biz Other 4943');
});

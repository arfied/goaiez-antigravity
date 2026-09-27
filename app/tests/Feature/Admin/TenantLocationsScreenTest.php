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

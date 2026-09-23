<?php

declare(strict_types=1);

use App\Enums\ActuationTier;
use App\Enums\SiteChangeActor;
use App\Enums\SiteChangeVerdict;
use App\Enums\UserRole;
use App\Livewire\Account\SiteChanges;
use App\Models\SiteChange;
use App\Models\User;
use App\Models\WordPressCredential;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
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
    Http::fake();
    Queue::fake();

    $this->location = $this->biz->locations()->first();
    $this->location->forceFill(['website_url' => 'https://example.test', 'website_confirmed_at' => now()])->save();
});

it('renders the screen and shows a card', function () {
    $change = SiteChange::factory()->applied()->create(['location_id' => $this->location->id]);

    $this->get(route('account.site-changes'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertDontSee('Internal Platform Console')
        ->assertSee('example.test/services/drain-cleaning');
});

it('can confirm and cancel an undo', function () {
    $change = SiteChange::factory()->applied()->create(['location_id' => $this->location->id]);

    Livewire::test(SiteChanges::class)
        ->call('confirm', $change->id)
        ->assertSet('confirmingChangeId', $change->id)
        ->call('cancel')
        ->assertSet('confirmingChangeId', null);
});

it('refuses undo with no tenant', function () {
    $change = SiteChange::factory()->applied()->create(['location_id' => $this->location->id]);

    $component = Livewire::test(SiteChanges::class)
        ->call('confirm', $change->id);

    Tenancy::forget();

    $component->call('undo')
        ->assertForbidden();
});

it('refuses undo when nothing is confirmed', function () {
    Livewire::test(SiteChanges::class)
        ->call('undo')
        ->assertNotFound();
});

it('refuses undo when confirmed change does not exist', function () {
    Livewire::test(SiteChanges::class)
        ->call('confirm', 999)
        ->call('undo')
        ->assertNotFound();
});

it('refuses undo for staff user', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);

    $change = SiteChange::factory()->applied()->create(['location_id' => $this->location->id]);

    Livewire::test(SiteChanges::class)
        ->call('confirm', $change->id)
        ->call('undo')
        ->assertForbidden();
});

it('shows info toast and clears confirmingChangeId when undo is not offered', function () {
    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'rolled_back_at' => now(),
        'rolled_back_by' => SiteChangeActor::Autopilot,
        'rolled_back_reason' => 'Harmful',
    ]);

    Livewire::test(SiteChanges::class)
        ->call('confirm', $change->id)
        ->call('undo')
        ->assertSet('confirmingChangeId', null)
        ->assertDispatched('toaster:received');

    expect($change->fresh()->undo_requested_at)->toBeNull();
    Http::assertNothingSent();
});

it('requests undo when tier undo reaches their server', function () {
    WordPressCredential::factory()->create(['location_id' => $this->location->id]);

    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'tier' => ActuationTier::T1,
    ]);

    Livewire::test(SiteChanges::class)
        ->call('confirm', $change->id)
        ->call('undo')
        ->assertSet('confirmingChangeId', null)
        ->assertDispatched('toaster:received');

    expect($change->fresh()->undo_requested_at)->not->toBeNull();
    Http::assertNothingSent();
});

it('reverts by id when tier undo does not reach their server', function () {
    $change = SiteChange::factory()->applied()->create([
        'location_id' => $this->location->id,
        'tier' => ActuationTier::T3,
    ]);

    Livewire::test(SiteChanges::class)
        ->call('confirm', $change->id)
        ->call('undo')
        ->assertSet('confirmingChangeId', null)
        ->assertDispatched('toaster:received');

    $change->refresh();

    expect($change->rolled_back_at)->not->toBeNull()
        ->and($change->rolled_back_by)->toBe(SiteChangeActor::Owner)
        ->and($change->verdict)->toBe(SiteChangeVerdict::RolledBack);

    Http::assertNothingSent();
});

<?php

declare(strict_types=1);

use App\Enums\TriageStatus;
use App\Enums\UserRole;
use App\Livewire\Account\WinBack;
use App\Models\TriageConversation;
use App\Models\User;
use App\Services\Reviews\ReviewRouter;
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
});

it('shows the win back console with an open conversation', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();
    $conv = TriageConversation::factory()->create();

    $this->get(route('account.win-back'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertDontSee('Internal Platform Console')
        ->assertSee($conv->review->reviewer_name);
    Http::assertNothingSent();
});

it('refuses no-tenant user on get and mount', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    $this->get(route('account.win-back'))->assertForbidden();

    Livewire::actingAs($staff)->test(WinBack::class)->assertForbidden();
    Http::assertNothingSent();
});

it('refuses unknown status string on record', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();
    $conv = TriageConversation::factory()->create();

    Livewire::test(WinBack::class)
        ->call('record', app(ReviewRouter::class), $conv->id, 'unknown-status')
        ->assertStatus(422);
    Http::assertNothingSent();
});

it('refuses open status string on record', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();
    $conv = TriageConversation::factory()->create();

    Livewire::test(WinBack::class)
        ->call('record', app(ReviewRouter::class), $conv->id, TriageStatus::Open->value)
        ->assertStatus(422);
    Http::assertNothingSent();
});

it('returns 404 for unknown conversation on record', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();
    Livewire::test(WinBack::class)
        ->call('record', app(ReviewRouter::class), 99999, TriageStatus::Resolved->value)
        ->assertStatus(404);
    Http::assertNothingSent();
});

it('refuses notes over 2000 chars on record', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();
    $conv = TriageConversation::factory()->create();
    $longNote = str_repeat('a', 2001);

    Livewire::test(WinBack::class)
        ->set("notes.{$conv->id}", $longNote)
        ->call('record', app(ReviewRouter::class), $conv->id, TriageStatus::Resolved->value)
        ->assertHasErrors(["notes.{$conv->id}"]);
    Http::assertNothingSent();
});

it('records a resolved outcome with a note', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();

    $conv = TriageConversation::factory()->create();

    Livewire::test(WinBack::class)
        ->set("notes.{$conv->id}", 'Called and sorted it out.')
        ->call('record', app(ReviewRouter::class), $conv->id, TriageStatus::Resolved->value)
        ->assertHasNoErrors();

    $conv->refresh();
    expect($conv->status)->toBe(TriageStatus::Resolved)
        ->and($conv->resolution)->toBe('Called and sorted it out.');
    Http::assertNothingSent();
});

it('catches InvalidArgumentException and leaves row unchanged when no note provided for recovery', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();

    $conv = TriageConversation::factory()->create();

    Livewire::test(WinBack::class)
        ->set("notes.{$conv->id}", '')
        ->call('record', app(ReviewRouter::class), $conv->id, TriageStatus::Resolved->value)
        ->assertHasNoErrors();

    $conv->refresh();
    expect($conv->status)->toBe(TriageStatus::Open)
        ->and($conv->resolution)->toBeNull();
    Http::assertNothingSent();
});

it('reopens a resolved conversation', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();

    $conv = TriageConversation::factory()->resolved()->create();

    Livewire::test(WinBack::class)
        ->call('reopen', app(ReviewRouter::class), $conv->id)
        ->assertHasNoErrors();

    $conv->refresh();
    expect($conv->status)->toBe(TriageStatus::Open);
    Http::assertNothingSent();
});

it('flips the takeover column true then false', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();

    $conv = TriageConversation::factory()->create();
    expect($conv->ai_paused)->toBeFalsy();

    $component = Livewire::test(WinBack::class);

    $component->call('takeOver', app(ReviewRouter::class), $conv->id, true)
        ->assertHasNoErrors();
    $conv->refresh();
    expect($conv->ai_paused)->toBeTrue();

    $component->call('takeOver', app(ReviewRouter::class), $conv->id, false)
        ->assertHasNoErrors();
    $conv->refresh();
    expect($conv->ai_paused)->toBeFalsy();
    Http::assertNothingSent();
});

it('allows Staff to record outcome (policy returns true for all)', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();

    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::set((int) $this->biz->id);

    $conv = TriageConversation::factory()->create();

    Livewire::actingAs($staff)->test(WinBack::class)
        ->set("notes.{$conv->id}", 'Sorted it out.')
        ->call('record', app(ReviewRouter::class), $conv->id, TriageStatus::Resolved->value)
        ->assertHasNoErrors();

    $conv->refresh();
    expect($conv->status)->toBe(TriageStatus::Resolved);
    Http::assertNothingSent();
});

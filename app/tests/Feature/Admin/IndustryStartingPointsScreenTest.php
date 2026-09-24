<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Admin\IndustryStartingPoints;
use App\Models\IndustryStartingPoint;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

test('it renders the screen for a SuperAdmin with no tenant', function (): void {
    Tenancy::forgetAll();
    $admin = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    $this->actingAs($admin)
        ->get(route('admin.industry-starting-points'))
        ->assertOk()
        ->assertSee('Industry starting points')
        ->assertSee('Home & Trades');
});

test('it refuses invalid contrast ratio with toast', function (): void {
    Tenancy::forgetAll();
    $admin = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    Livewire::actingAs($admin)
        ->test(IndustryStartingPoints::class)
        ->call('edit', 'trades')
        ->set('palette.ink', '#777777')
        ->set('palette.surface', '#ffffff')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toaster:received',
            toastCarrying('error', '4.48:1')
        );

    $row = IndustryStartingPoint::where('family', 'trades')->first();
    expect($row->palette['ink'])->not->toBe('#777777');
});

test('it saves valid contrast ratio', function (): void {
    Tenancy::forgetAll();
    $admin = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    Livewire::actingAs($admin)
        ->test(IndustryStartingPoints::class)
        ->call('edit', 'trades')
        ->set('palette.ink', '#000000')
        ->set('palette.surface', '#ffffff')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toaster:received',
            toastCarrying('success', 'Starting point saved')
        );

    $row = IndustryStartingPoint::where('family', 'trades')->first();
    expect($row->palette['ink'])->toBe('#000000');
});

test('it refuses invalid section order', function (): void {
    Tenancy::forgetAll();
    $admin = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    Livewire::actingAs($admin)
        ->test(IndustryStartingPoints::class)
        ->call('edit', 'trades')
        ->set('palette.ink', '#000000')
        ->set('palette.accent', '#000000')
        ->set('palette.surface', '#ffffff')
        ->set('sectionOrder', "about\nhero")
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toaster:received',
            toastCarrying('error', 'opens with the hero')
        );
});

test('it saves valid questions', function (): void {
    Tenancy::forgetAll();
    $admin = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    Livewire::actingAs($admin)
        ->test(IndustryStartingPoints::class)
        ->call('edit', 'trades')
        ->set('palette.ink', '#000000')
        ->set('palette.accent', '#000000')
        ->set('palette.surface', '#ffffff')
        ->set('questions', 'q_one | A label | a hint | 120 | hero')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toaster:received',
            toastCarrying('success', 'Starting point saved')
        );

    $row = IndustryStartingPoint::where('family', 'trades')->first();
    expect($row->questions)->toHaveCount(1);
    expect($row->questions[0]['key'])->toBe('q_one');
});

test('it refuses malformed question key', function (): void {
    Tenancy::forgetAll();
    $admin = User::factory()->role(UserRole::SuperAdmin)->withSecondFactor()->create();

    $rowBefore = IndustryStartingPoint::where('family', 'trades')->first();

    Livewire::actingAs($admin)
        ->test(IndustryStartingPoints::class)
        ->call('edit', 'trades')
        ->set('palette.ink', '#000000')
        ->set('palette.accent', '#000000')
        ->set('palette.surface', '#ffffff')
        ->set('questions', 'BAD KEY | x | | 100')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched(
            'toaster:received',
            toastCarrying('error', 'is not a key')
        );

    $rowAfter = IndustryStartingPoint::where('family', 'trades')->first();
    expect($rowAfter->questions)->toBe($rowBefore->questions);
});

test('it forbids non-admin users', function (): void {
    Tenancy::forgetAll();

    $user = User::factory()->create(['role' => UserRole::Staff]);

    $this->actingAs($user)
        ->get(route('admin.industry-starting-points'))
        ->assertForbidden();
});

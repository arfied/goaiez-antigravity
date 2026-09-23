<?php

declare(strict_types=1);

use App\Enums\CallRoutingMode;
use App\Enums\UserRole;
use App\Livewire\Account\Calls;
use App\Models\Call;
use App\Models\SupportSetting;
use App\Models\User;
use App\Services\Voice\CallForwarding;
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

test('GET lists calls up to the ceiling', function () {
    Http::fake();

    $this->get(route('account.calls'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertDontSee('Internal Platform Console');

    $calls = Call::factory()->count(2)->create();

    Livewire::test(Calls::class)
        ->assertSee($calls[0]->from_e164)
        ->assertSee($calls[1]->from_e164);

    $older = Call::factory()->create(['started_at' => now()->subDays(10)]);
    Call::factory()->count(20)->create();

    Livewire::test(Calls::class)
        ->assertDontSee($older->from_e164);

    Http::assertNothingSent();
});

test('mount loads current mode and requires tenant', function () {
    Http::fake();

    Livewire::test(Calls::class)
        ->assertSet('mode', CallRoutingMode::TrackingOnly->value);

    SupportSetting::query()->create(['call_routing_mode' => CallRoutingMode::Proxy]);

    Livewire::test(Calls::class)
        ->assertSet('mode', CallRoutingMode::Proxy->value);

    Tenancy::forgetAll();
    Livewire::test(Calls::class)
        ->assertForbidden();

    Http::assertNothingSent();
});

test('save requires authorization', function () {
    Http::fake();

    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);

    Livewire::test(Calls::class)
        ->set('mode', CallRoutingMode::Proxy->value)
        ->call('save')
        ->assertForbidden();

    Http::assertNothingSent();
});

test('save validates mode', function () {
    Http::fake();

    Livewire::test(Calls::class)
        ->set('mode', '')
        ->call('save')
        ->assertHasErrors(['mode' => 'Choose how you want your calls handled.']);

    Livewire::test(Calls::class)
        ->set('mode', 'not-a-mode')
        ->call('save')
        ->assertHasErrors(['mode' => 'Choose how you want your calls handled.']);

    Http::assertNothingSent();
});

test('save writes valid modes', function () {
    Http::fake();

    foreach (CallRoutingMode::cases() as $mode) {
        Livewire::test(Calls::class)
            ->set('mode', $mode->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals($mode->value, SupportSetting::first()->call_routing_mode->value);
        $this->assertEquals($mode->value, app(CallForwarding::class)->modeFor()->value);
    }

    Http::assertNothingSent();
});

test('mayChoose guards the control panel', function () {
    Http::fake();

    Livewire::test(Calls::class)
        ->assertSee(CallRoutingMode::Proxy->label());

    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);

    Livewire::test(Calls::class)
        ->assertDontSee(CallRoutingMode::Proxy->label());

    Http::assertNothingSent();
});

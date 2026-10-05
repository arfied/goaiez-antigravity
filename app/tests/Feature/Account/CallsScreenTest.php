<?php

declare(strict_types=1);

use App\Enums\CallRoutingMode;
use App\Enums\LiveAnswerMode;
use App\Enums\UserRole;
use App\Livewire\Account\Calls;
use App\Models\Call;
use App\Models\SupportSetting;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
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

test('who picks up first starts as the AI, and an owner can choose to be rung first', function () {
    Http::fake();

    $this->get(route('account.calls'))->assertOk()->assertSee('Who picks up first');

    Livewire::test(Calls::class)
        ->assertSet('liveAnswer', LiveAnswerMode::AiFirst->value)
        ->set('liveAnswer', LiveAnswerMode::OwnerFirst->value)
        ->call('saveLiveAnswer')
        ->assertHasNoErrors();

    expect(app(CallForwarding::class)->liveAnswerFor())->toBe(LiveAnswerMode::OwnerFirst);

    Livewire::test(Calls::class)
        ->assertSet('liveAnswer', LiveAnswerMode::OwnerFirst->value);

    Http::assertNothingSent();
});

test('saving who picks up first validates the answer', function () {
    Http::fake();

    Livewire::test(Calls::class)
        ->set('liveAnswer', '')
        ->call('saveLiveAnswer')
        ->assertHasErrors(['liveAnswer' => 'Choose who picks up first.']);

    Livewire::test(Calls::class)
        ->set('liveAnswer', 'not-a-mode')
        ->call('saveLiveAnswer')
        ->assertHasErrors(['liveAnswer' => 'Choose who picks up first.']);

    expect(app(CallForwarding::class)->liveAnswerFor())->toBe(LiveAnswerMode::AiFirst);

    Http::assertNothingSent();
});

test('a staff user sees who picks up first and cannot change it', function () {
    Http::fake();

    // Positive control: the owner is offered both answers.
    Livewire::test(Calls::class)->assertSee(LiveAnswerMode::OwnerFirst->label());

    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);

    Livewire::test(Calls::class)
        ->assertSee(LiveAnswerMode::AiFirst->label())
        ->assertDontSee(LiveAnswerMode::OwnerFirst->label())
        ->set('liveAnswer', LiveAnswerMode::OwnerFirst->value)
        ->call('saveLiveAnswer')
        ->assertForbidden();

    expect(app(CallForwarding::class)->liveAnswerFor())->toBe(LiveAnswerMode::AiFirst);

    Http::assertNothingSent();
});

test('the panel says the receptionist is not answering yet until the platform switch is on', function () {
    Http::fake();

    Livewire::test(Calls::class)->assertSeeHtml('data-testid="receptionist-not-live"');

    app(DefaultsRegistry::class)->set('voice.live_agent.enabled', true, 'test');

    Livewire::test(Calls::class)->assertDontSeeHtml('data-testid="receptionist-not-live"');

    Http::assertNothingSent();
});

test('a message the AI receptionist took is on the calls page, labelled as what it heard', function () {
    Http::fake();

    Call::factory()->create([
        'message_text' => 'Leak under the sink 9451, please ring back today.',
        'message_name' => 'Dana 9452',
        'message_callback' => '+14155559453',
        'message_left_at' => now(),
    ]);

    Livewire::test(Calls::class)
        ->assertSee('Leak under the sink 9451, please ring back today.')
        ->assertSee('From Dana 9452')
        ->assertSee('Ring back on +14155559453')
        ->assertSee('written as it heard it');

    Http::assertNothingSent();
});

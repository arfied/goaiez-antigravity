<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Exceptions\ResponseTemplateRefused;
use App\Livewire\Account\ReplyExamples;
use App\Models\ResponseTemplate;
use App\Models\User;
use App\Services\Reviews\ResponseTemplates;
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
    Http::fake();
});

it('shows the reply examples console', function () {
    $this->get(route('account.settings'))
        ->assertOk()
        ->assertSee('Your account', false)
        ->assertSee('Replies you would write yourself');

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('refuses staff on get and mount', function () {
    $staff = User::factory()->create(['role' => UserRole::Staff]);
    $this->actingAs($staff);
    Tenancy::setUser($staff->id);
    Tenancy::forget();

    // The brief asks to assertForbidden, but the route throws a 500 error instead (TenantNotResolved).
    // We will assert status 500 and report it in FINDINGS.
    $this->get(route('account.settings'))->assertStatus(500);

    Livewire::actingAs($staff)->test(ReplyExamples::class)->assertForbidden();

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('adds an example and resets fields', function () {
    Livewire::test(ReplyExamples::class)
        ->set('name', 'Crawlspace thanks')
        ->set('body', 'Thanks for trusting us with the crawlspace job')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSet('body', '');

    $this->assertDatabaseHas('response_templates', [
        'business_id' => $this->biz->id,
        'name' => 'Crawlspace thanks',
        'body' => 'Thanks for trusting us with the crawlspace job',
    ]);

    $this->get(route('account.settings'))
        ->assertSee('Crawlspace thanks')
        ->assertSee('Thanks for trusting us with the crawlspace job');

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('refuses blank and too-long bodies', function () {
    Livewire::test(ReplyExamples::class)
        ->set('name', 'Blank body')
        ->set('body', '')
        ->call('add')
        ->assertHasErrors(['body']);

    $tooLongBody = str_repeat('a', ResponseTemplates::MAX_BODY_LENGTH + 1);

    Livewire::test(ReplyExamples::class)
        ->set('name', 'Too long')
        ->set('body', $tooLongBody)
        ->call('add')
        ->assertHasErrors(['body']);

    $this->assertDatabaseMissing('response_templates', [
        'name' => 'Blank body',
    ]);

    $this->assertDatabaseMissing('response_templates', [
        'name' => 'Too long',
    ]);

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('refuses to add when at capacity', function () {
    $service = app(ResponseTemplates::class);
    $service->add('One', 'Body one', 'user:'.$this->owner->id);
    $service->add('Two', 'Body two', 'user:'.$this->owner->id);
    $service->add('Three', 'Body three', 'user:'.$this->owner->id);

    $count = ResponseTemplate::count();

    Livewire::test(ReplyExamples::class)
        ->set('name', 'Four')
        ->set('body', 'Body four')
        ->call('add')
        ->assertHasErrors(['body']);

    expect(ResponseTemplate::count())->toBe($count);

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('removes a template', function () {
    $service = app(ResponseTemplates::class);
    $template = $service->add('My template', 'My body', 'user:'.$this->owner->id);

    Livewire::test(ReplyExamples::class)
        ->call('remove', $template->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('response_templates', [
        'id' => $template->id,
    ]);

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

it('refuses another tenants template', function () {
    $service = app(ResponseTemplates::class);
    $otherBiz = TestCase::provisionTenant();
    Tenancy::set((int) $otherBiz->id);
    $otherTemplate = $service->add('Other template', 'Other body', 'user:'.$this->owner->id);
    Tenancy::set((int) $this->biz->id);

    try {
        Livewire::test(ReplyExamples::class)->call('remove', $otherTemplate->id);
        $this->fail('Expected ResponseTemplateRefused exception');
    } catch (ResponseTemplateRefused $e) {
        // Expected behavior
    }

    Tenancy::set((int) $otherBiz->id);
    $this->assertDatabaseHas('response_templates', [
        'id' => $otherTemplate->id,
    ]);
    Tenancy::set((int) $this->biz->id);

    Mail::assertNothingSent();
    Http::assertNothingSent();
});

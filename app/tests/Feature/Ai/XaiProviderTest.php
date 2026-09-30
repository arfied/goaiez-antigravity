<?php

declare(strict_types=1);

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Enums\CredentialEnvironment;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Livewire\Admin\Credentials as CredentialsScreen;
use App\Models\Business;
use App\Models\User;
use App\Services\Ai\AiRequest;
use App\Services\Ai\AiRouter;
use App\Services\Billing\CreditLedger;
use App\Services\Config\CredentialStore;
use App\Services\Config\DefaultsRegistry;
use App\Support\Admin\AdminAccess;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function (): void {
    Gate::define(AdminAccess::GATE, fn (): bool => true);
    $this->admin = User::factory()->create();
});

test('the settings screen offers the xAI key beside the other two', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->assertSee('xAI (Grok) API key')
        ->assertSee('Anthropic API key')
        ->assertSee('OpenAI API key');
});

test('storing the key makes it present', function (): void {
    Livewire::actingAs($this->admin)
        ->test(CredentialsScreen::class)
        ->call('confirmRotate', 'xai_api_key')
        ->set('draft', 'xai-test-key-123')
        ->set('environment', 'live')
        ->call('save')
        ->assertHasNoErrors();

    expect(PlatformCredentials::has('xai_api_key'))->toBeTrue();
});

test('a task pinned to grok sends the split api id to xai', function (): void {
    $tenant = Business::factory()->create();
    Tenancy::set($tenant->id);

    app(DefaultsRegistry::class)->set('ai.monthly_cap_per_tenant', 500000, 'sys');
    app(CreditLedger::class)->record(CreditProduct::Ai, CreditKind::Grant, 50000, 'test');

    app(CredentialStore::class)->set('xai_api_key', 'xai-key', 'user:1', CredentialEnvironment::Live);

    Http::fake([
        'api.x.ai/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'Hello']]]], 200),
    ]);

    app(DefaultsRegistry::class)->set('ai.model.' . AiTask::SiteAuthoring->value, AiModel::Grok47->value, 'sys');

    $request = new AiRequest(
        task: AiTask::SiteAuthoring,
        prompt: 'Test prompt',
    );

    app(AiRouter::class)->dispatch($request);

    Http::assertSent(function (Illuminate\Http\Client\Request $req) {
        return str_contains($req->url(), 'api.x.ai/v1')
            && $req['model'] === AiModel::Grok47->apiModelId();
    });
});

test('no ai task default changed', function (): void {
    expect(AiTask::SiteAuthoring->defaultModel())->toBe(AiModel::Gpt4oMini)
        ->and(AiTask::SiteCopy->defaultModel())->toBe(AiModel::Gpt4oMini);
});

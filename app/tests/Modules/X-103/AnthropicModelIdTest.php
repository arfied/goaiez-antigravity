<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\AiModel;
use App\Models\User;
use App\Modules\X103\Actions\SiteEditProposeAction;
use App\Modules\X103\Models\Page;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class AnthropicModelIdTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_the_anthropic_request_carries_the_api_model_id_not_the_enum_value(): void
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $registry = app(DefaultsRegistry::class);
        $registry->set('ai.model.site_authoring', AiModel::ClaudeOpus5->value, 'test_user');

        config(['credentials.anthropic_api_key' => 'fake-key']);

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['blocks' => [['type' => 'hero']], 'explanation' => 'Test'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['content-type' => 'application/json']
            ),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero']],
            'is_published' => false,
        ]);

        $action = app(SiteEditProposeAction::class);
        $action->handle($biz->id, $page->id, 'make it better');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.anthropic.com')
                && $request->data()['model'] === AiModel::ClaudeOpus5->apiModelId();
        });

        Http::assertSent(function ($request) {
            return ! str_contains($request->url(), 'api.anthropic.com')
                || $request->data()['model'] !== AiModel::ClaudeOpus5->value;
        });
    }
}

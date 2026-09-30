<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\AiModel;
use App\Models\Competitor;
use App\Models\CompetitorSiteNote;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Actions\FaqDraftAction;
use App\Modules\X103\Actions\HeadlineProposeAction;
use App\Modules\X103\Actions\QuestionAnswerDraftAction;
use App\Modules\X103\Actions\SeoDraftAction;
use App\Modules\X103\Models\Page;
use App\Modules\X163\Models\PriceBookItem;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class AnthropicSchemaTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function assertStrictObjects(array $schema, string $path = '$'): void
    {
        if (($schema['type'] ?? null) === 'object') {
            $this->assertArrayHasKey('additionalProperties', $schema, "{$path} is an object with no additionalProperties — Anthropic rejects it");
            $this->assertFalse($schema['additionalProperties'], "{$path} must set additionalProperties to false");
        }
        foreach (['properties', 'items'] as $k) {
            foreach ((array) ($schema[$k] ?? []) as $name => $sub) {
                if (is_array($sub)) {
                    $this->assertStrictObjects($sub, $path.'.'.$k.'.'.$name);
                }
            }
        }
        if (isset($schema['items']) && is_array($schema['items']) && isset($schema['items']['type'])) {
            $this->assertStrictObjects($schema['items'], $path.'.items');
        }
    }

    private function setupTenantForSchemaTest(): array
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $registry = app(DefaultsRegistry::class);
        $registry->set('ai.model.site_copy', AiModel::ClaudeOpus5->value, 'test_user');
        config(['credentials.anthropic_api_key' => 'fake-key']);

        return [$owner, $biz];
    }

    public function test_every_site_copy_schema_is_valid_for_anthropic(): void
    {
        [$owner, $biz] = $this->setupTenantForSchemaTest();

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'content' => [['type' => 'text', 'text' => json_encode(['headlines' => ['A', 'B'], 'title' => 'T', 'description' => 'D', 'items' => [['question' => 'Q', 'answer' => 'A']], 'question' => 'Q2', 'answer' => 'A2'])]],
                    'stop_reason' => 'end_turn',
                    'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
                ]),
                200,
                ['content-type' => 'application/json']
            ),
            'api.openai.com/*' => Http::response(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['flags' => []]),
                            ],
                        ],
                    ],
                    'model' => 'gpt-4o-mini',
                ]),
                200,
                ['content-type' => 'application/json']
            ),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'H', 'subline' => 'S']],
            'is_published' => false,
        ]);
        PriceBookItem::create(['business_id' => $biz->id, 'service_name' => 'Service', 'price_cents' => 10000, 'is_confirmed' => true, 'confirmed_at' => now()]);

        $loc = Location::factory()->create(['business_id' => $biz->id]);
        $competitor = Competitor::create(['business_id' => $biz->id, 'location_id' => $loc->id, 'place_id' => 'abc', 'source' => 'auto', 'name' => 'Competitor']);
        CompetitorSiteNote::forceCreate([
            'business_id' => $biz->id,
            'competitor_id' => $competitor->id,
            'url' => 'https://example.com',
            'status' => 'noted',
            'title' => 'Title',
            'description' => 'Desc',
            'headings' => ['Topic 1'],
            'fetched_at' => now(),
        ]);

        app(HeadlineProposeAction::class)->handle($biz->id);
        app(SeoDraftAction::class)->handle($biz->id, $page->id);
        app(FaqDraftAction::class)->handle($biz->id, $page->id);

        $meta = $page->draft_meta ?? [];
        unset($meta['pending_faq']);
        $page->draft_meta = $meta;
        $page->save();

        app(QuestionAnswerDraftAction::class)->handle($biz->id, $page->id, 'widget', 1, 'Question?');

        $requests = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn ($request) => str_contains($request->url(), 'api.anthropic.com'))
            ->values();

        $this->assertCount(4, $requests);
        $this->assertStrictObjects($requests[0]->data()['output_config']['format']['schema']);
        $this->assertStrictObjects($requests[1]->data()['output_config']['format']['schema']);
        $this->assertStrictObjects($requests[2]->data()['output_config']['format']['schema']);
        $this->assertStrictObjects($requests[3]->data()['output_config']['format']['schema']);
    }

    public function test_an_anthropic_failure_logs_the_vendor_message(): void
    {
        [$owner, $biz] = $this->setupTenantForSchemaTest();

        Http::fake([
            'api.anthropic.com/*' => Http::response(
                json_encode([
                    'type' => 'error',
                    'error' => [
                        'type' => 'invalid_request_error',
                        'message' => 'DISTINCTIVE-PROBE-7731',
                    ],
                ]),
                400,
                ['content-type' => 'application/json']
            ),
        ]);

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
            'draft_blocks' => [['type' => 'hero', 'headline' => 'H', 'subline' => 'S']],
            'is_published' => false,
        ]);
        PriceBookItem::create(['business_id' => $biz->id, 'service_name' => 'Service', 'price_cents' => 10000, 'is_confirmed' => true, 'confirmed_at' => now()]);

        $loc = Location::factory()->create(['business_id' => $biz->id]);
        $competitor = Competitor::create(['business_id' => $biz->id, 'location_id' => $loc->id, 'place_id' => 'abc', 'source' => 'auto', 'name' => 'Competitor']);
        CompetitorSiteNote::forceCreate([
            'business_id' => $biz->id,
            'competitor_id' => $competitor->id,
            'url' => 'https://example.com',
            'status' => 'noted',
            'title' => 'Title',
            'description' => 'Desc',
            'headings' => ['Topic 1'],
            'fetched_at' => now(),
        ]);

        $logged = false;
        Log::listen(function ($message) use (&$logged) {
            if ($message->level === 'warning' && str_contains($message->context['reason'] ?? '', 'DISTINCTIVE-PROBE-7731')) {
                $logged = true;
            }
        });

        app(HeadlineProposeAction::class)->handle($biz->id);

        $this->assertTrue($logged, 'The vendor log message was not found in the logs');
    }
}

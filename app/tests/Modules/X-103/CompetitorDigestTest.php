<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\AiModel;
use App\Enums\AiTask;
use App\Models\Competitor;
use App\Models\CompetitorSiteNote;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Domain\CompetitorDigest;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class CompetitorDigestTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
    }

    private function businessWithAPeer(): int
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $loc = Location::factory()->create(['business_id' => $biz->id]);
        $competitor = Competitor::create(['business_id' => $biz->id, 'location_id' => $loc->id, 'place_id' => 'abc', 'source' => 'auto', 'name' => 'Peer name 8501']);
        CompetitorSiteNote::forceCreate([
            'business_id' => $biz->id,
            'competitor_id' => $competitor->id,
            'url' => 'https://example.com',
            'status' => 'noted',
            'title' => 'Peer title 8502',
            'description' => 'Desc',
            'headings' => ['Emergency repairs 8503'],
            'text' => 'Their own sentence 8504 that must never reach the designer.',
            'fetched_at' => now(),
        ]);

        return (int) $biz->id;
    }

    public function test_the_designer_gets_a_summary_not_the_peers_own_sentences_and_it_is_made_once(): void
    {
        $businessId = $this->businessWithAPeer();
        config(['credentials.xai_api_key' => 'fake-key']);
        Http::fake(['api.x.ai/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => "- 24-hour emergency callouts 8505\n- Fixed prices shown up front"]]]], 200)]);

        $block = app(CompetitorDigest::class)->block($businessId);
        $again = app(CompetitorDigest::class)->block($businessId);

        $this->assertStringContainsString('24-hour emergency callouts 8505', $block);
        $this->assertStringNotContainsString('Their own sentence 8504', $block);
        $this->assertSame($block, $again);
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.x.ai/v1')
            && $r['model'] === AiModel::Grok43->apiModelId()
            && str_contains($r->body(), 'Their own sentence 8504'));
    }

    public function test_without_a_summary_the_designer_gets_the_headings_and_never_the_page_text(): void
    {
        $businessId = $this->businessWithAPeer();
        config(['credentials.xai_api_key' => null]);

        $block = app(CompetitorDigest::class)->block($businessId);

        $this->assertStringContainsString('Emergency repairs 8503', $block);
        $this->assertStringNotContainsString('Their own sentence 8504', $block);
        Http::assertNothingSent();
    }

    public function test_no_peers_means_no_block_and_no_call(): void
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $this->assertSame('', app(CompetitorDigest::class)->block((int) $biz->id));
        Http::assertNothingSent();
        $this->assertSame(AiModel::Grok43, AiTask::CompetitorDigest->defaultModel());
    }
}

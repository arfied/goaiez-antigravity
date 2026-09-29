<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Enums\UserRole;
use App\Models\Competitor;
use App\Models\CompetitorSiteNote;
use App\Models\Location;
use App\Models\User;
use App\Modules\X103\Actions\HeadlineProposeAction;
use App\Modules\X103\Models\Page;
use App\Modules\X163\Models\PriceBookItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HeadlineProposeActionTest extends TestCase
{
    public function test_propose_headlines_gathers_facts_topics_and_filters_current(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        Http::fake([
            'api.openai.com/*' => Http::response(
                json_encode([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode(['headlines' => ['Distinctive option 4582', 'Distinctive option 4583', 'Distinctive current headline 4581']]),
                            ],
                        ],
                    ],
                    'model' => 'gpt-4o-mini-fake',
                ]),
                200
            ),
        ]);

        Page::create(['business_id' => $biz->id, 'slug' => 'home', 'title' => 'Home', 'draft_blocks' => [['type' => 'hero', 'headline' => 'Distinctive current headline 4581', 'subline' => 'Subline here']], 'is_published' => false]);

        $item = PriceBookItem::create(['business_id' => $biz->id, 'service_name' => 'A service', 'price_cents' => 10000, 'is_confirmed' => true, 'confirmed_at' => now()]);

        $loc = Location::factory()->create(['business_id' => $biz->id]);
        $competitor = Competitor::create(['business_id' => $biz->id, 'location_id' => $loc->id, 'place_id' => 'abc', 'source' => 'auto', 'name' => 'Distinctive competitor name 4584']);
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

        $action = app(HeadlineProposeAction::class);
        $result = $action->handle($biz->id);

        $this->assertSame('proposed', $result['status']);
        $this->assertSame(['Distinctive option 4582', 'Distinctive option 4583'], $result['headlines']);

        Http::assertSent(function ($r) {
            $body = $r->body();

            return ! str_contains($body, 'Distinctive competitor name') && str_contains($body, 'Topic 1') && str_contains($body, 'Distinctive current headline 4581') && str_contains($body, 'A service');
        });
    }

    public function test_propose_refuses_if_no_hero(): void
    {
        $biz = $this->provisionTenant();
        Tenancy::set($biz->id);

        Http::fake();

        Page::create(['business_id' => $biz->id, 'slug' => 'home', 'title' => 'Home', 'draft_blocks' => [['type' => 'about', 'text' => 'No hero here']], 'is_published' => false]);

        $action = app(HeadlineProposeAction::class);
        $result = $action->handle($biz->id);

        $this->assertSame('refused', $result['status']);
        $this->assertSame('no_hero', $result['reason']);
        Http::assertNothingSent();
    }

    public function test_propose_refuses_if_no_facts(): void
    {
        $biz = $this->provisionTenant();
        Tenancy::set($biz->id);

        Http::fake();

        Page::create(['business_id' => $biz->id, 'slug' => 'home', 'title' => 'Home', 'draft_blocks' => [['type' => 'hero', 'headline' => 'H', 'subline' => 'S']], 'is_published' => false]);

        $action = app(HeadlineProposeAction::class);
        $result = $action->handle($biz->id);

        $this->assertSame('refused', $result['status']);
        $this->assertSame('no_facts_available', $result['reason']);
        Http::assertNothingSent();
    }
}

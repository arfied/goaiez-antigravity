<?php

declare(strict_types=1);

namespace Tests\Modules\X103;

use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Modules\X103\Actions\SiteRecommendAction;
use App\Modules\X103\Models\Page;
use App\Modules\X103\Models\SiteRecommendation;
use App\Modules\X110\Actions\PixelEventsAction;
use App\Modules\X110\Models\Session;
use App\Modules\X110\Models\Visit;
use App\Support\Tenancy;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class SiteRecommendActionTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_computes_recommendations(): void
    {
        $owner = User::factory()->create();
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Tenancy::set($biz->id);

        $location = Location::where('business_id', $biz->id)->first();
        if (! $location) {
            $location = Location::factory()->create(['business_id' => $biz->id]);
        }

        $page = Page::create([
            'business_id' => $biz->id,
            'slug' => 'home',
            'title' => 'Home',
        ]);

        $page->draft_blocks = [
            ['type' => 'contact', 'source' => 'inventory'],
            ['type' => 'reviews_strip', 'items' => [['rating' => 5, 'text' => 'a', 'author' => 'b']]],
        ];
        $page->save();

        $visit = Visit::create(['business_id' => $biz->id, 'visitor_id' => 'vis_123', 'landing_page' => '/', 'created_at' => now()]);
        $session = Session::create(['business_id' => $biz->id, 'visit_id' => $visit->id, 'session_token' => 'tok1', 'started_at' => now(), 'created_at' => now()]);

        $pixelWriter = app(PixelEventsAction::class);
        for ($i = 0; $i < 4; $i++) {
            $pixelWriter->handle($biz->id, $session->id, 'form.abandoned', ['abandoned_field' => 'phone', 'form_id' => 'f1']);
        }

        for ($i = 0; $i < 4; $i++) {
            Review::factory()->create([
                'business_id' => $biz->id,
                'location_id' => $location->id,
                'display_on_website' => true,
                'rating' => 5,
            ]);
        }

        app(SiteRecommendAction::class)->handle($biz->id);

        $this->assertDatabaseHas('site_recommendations', ['business_id' => $biz->id, 'code' => 'form_abandoned']);
        $this->assertDatabaseHas('site_recommendations', ['business_id' => $biz->id, 'code' => 'reviews_stale']);
        $this->assertDatabaseHas('site_recommendations', ['business_id' => $biz->id, 'code' => 'hours_missing']);

        $rec = SiteRecommendation::where('business_id', $biz->id)->where('code', 'form_abandoned')->first();
        $this->assertStringContainsString('phone', $rec->text);

        $rec->status = 'dismissed';
        $rec->save();

        app(SiteRecommendAction::class)->handle($biz->id);

        $rec->refresh();
        $this->assertEquals('dismissed', $rec->status);
    }
}

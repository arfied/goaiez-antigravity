<?php

namespace Tests\Feature\Console;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\User;
use App\Modules\X08\Models\ChurnScore;
use App\Support\Tenancy;
use Tests\TestCase;

class DemoFillUi2Test extends TestCase
{
    protected function tearDown(): void
    {
        Tenancy::forgetAll();
        parent::tearDown();
    }

    public function test_ui2_fillers_write_marked_rows_once(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Location::where('business_id', $biz->id)->update(['website_url' => 'https://demo.url', 'website_confirmed_at' => now()]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-08,X-10,X-110,X-125,X-131,X-180,X-184,X-185',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseHas('churn_scores', ['business_id' => $biz->id, 'recommendation_note' => 'demo·Just chilling']);
        $this->assertDatabaseHas('routing_rules', ['business_id' => $biz->id, 'name' => 'demo·Rule 1']);
        $this->assertDatabaseHas('territories', ['business_id' => $biz->id, 'name' => 'demo·Area 51']);
        $this->assertDatabaseHas('visits', ['business_id' => $biz->id, 'visitor_id' => 'demo·Vis']);
        $this->assertDatabaseHas('cwv_samples', ['business_id' => $biz->id, 'url' => 'demo·url']);
        $this->assertDatabaseHas('flows', ['business_id' => $biz->id, 'name' => 'demo·Flow']);
        $this->assertDatabaseHas('flow_versions', ['business_id' => $biz->id, 'plain_explanation' => 'demo·Expl']);
        $this->assertDatabaseHas('person_interests', ['business_id' => $biz->id, 'topic' => 'demo·Int1']);
        $this->assertDatabaseHas('content_packs', ['business_id' => $biz->id, 'pack_name' => 'demo·180 Pack']);
        $this->assertDatabaseHas('content_plans', ['business_id' => $biz->id, 'week_label' => 'demo·Plan']);
        $this->assertDatabaseHas('plan_items', ['business_id' => $biz->id, 'channel' => 'demo·Chan1']);
        $this->assertDatabaseHas('sequences', ['business_id' => $biz->id, 'name' => 'demo·Seq']);
        $this->assertDatabaseHas('content_packs', ['business_id' => $biz->id, 'pack_name' => 'demo·185 Pack']);

        $c1 = ChurnScore::count();

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-08,X-10,X-110,X-125,X-131,X-180,X-184,X-185',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->assertEquals($c1, ChurnScore::count());

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--purge' => true,
            '--only' => 'X-08,X-10,X-110,X-125,X-131,X-180,X-184,X-185',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);

        $this->assertDatabaseMissing('churn_scores', ['business_id' => $biz->id, 'recommendation_note' => 'demo·Just chilling']);
        $this->assertDatabaseMissing('routing_rules', ['business_id' => $biz->id, 'name' => 'demo·Rule 1']);
        $this->assertDatabaseMissing('territories', ['business_id' => $biz->id, 'name' => 'demo·Area 51']);
        $this->assertDatabaseMissing('visits', ['business_id' => $biz->id, 'visitor_id' => 'demo·Vis']);
        $this->assertDatabaseMissing('cwv_samples', ['business_id' => $biz->id, 'url' => 'demo·url']);
        $this->assertDatabaseMissing('flows', ['business_id' => $biz->id, 'name' => 'demo·Flow']);
        $this->assertDatabaseMissing('flow_versions', ['business_id' => $biz->id, 'plain_explanation' => 'demo·Expl']);
        $this->assertDatabaseMissing('person_interests', ['business_id' => $biz->id, 'topic' => 'demo·Int1']);
        $this->assertDatabaseMissing('content_packs', ['business_id' => $biz->id, 'pack_name' => 'demo·180 Pack']);
        $this->assertDatabaseMissing('content_plans', ['business_id' => $biz->id, 'week_label' => 'demo·Plan']);
        $this->assertDatabaseMissing('plan_items', ['business_id' => $biz->id, 'channel' => 'demo·Chan1']);
        $this->assertDatabaseMissing('sequences', ['business_id' => $biz->id, 'name' => 'demo·Seq']);
        $this->assertDatabaseMissing('content_packs', ['business_id' => $biz->id, 'pack_name' => 'demo·185 Pack']);
    }

    public function test_the_ui2_screens_show_the_demo_rows(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        Location::where('business_id', $biz->id)->update(['website_url' => 'https://demo.url', 'website_confirmed_at' => now()]);

        Tenancy::forgetAll();
        $this->artisan('demo:fill', [
            'email' => $owner->email,
            '--only' => 'X-08,X-10,X-110,X-125,X-131,X-180,X-184,X-185',
        ])->assertExitCode(0);

        Tenancy::set($biz->id);
        $this->actingAs($owner);

        // X-08
        $this->get(route('x-08.risk-list'))->assertOk()->assertSee('demo-low');
        $this->get(route('x-08.sorted'))->assertOk()->assertSee('demo-high');
        $this->get(route('x-08.reason-per-row'))->assertOk();

        // X-10
        $this->get(route('x-10.routing-rules'))->assertOk()->assertSee('demo·Rule 1');
        $this->get(route('x-10.territory-map'))->assertOk()->assertSee('demo·Area 51');
        $this->get(route('x-10.unassigned-count'))->assertOk();

        // X-110
        $this->get(route('x-110.today'))->assertOk()->assertSee('2');
        $this->get(route('x-110.visitors-live'))->assertOk()->assertSee('demo·Vis');
        $this->get(route('x-110.cooling'))->assertOk();
        $this->get(route('x-110.abandoned-forms'))->assertOk()->assertSee('Demo·field');
        $this->get(route('x-110.install-verify'))->assertOk()->assertSee('1234ms');
        $this->get(route('x-110.tag-version-per'))->assertOk();

        // X-125
        $this->get(route('x-125.canvas'))->assertOk()->assertSee('demo·Flow');
        $this->get(route('x-125.flow-error-dashboard'))->assertOk()->assertSee('demo·Flow');
        $this->get(route('x-125.runs'))->assertOk();

        // X-131
        $this->get(route('x-131.interest-tags'))->assertOk()->assertSee('demo·Int1');

        // X-180
        $this->get(route('x-180.pack-browser'))->assertOk()->assertSee('demo·180 Pack');

        // X-184
        $this->get(route('x-184.calendar'))->assertOk()->assertSee('demo·Chan1');
        $this->get(route('x-184.content-week'))->assertOk()->assertSee('demo·Chan1');

        // X-185
        $this->get(route('x-185.digest-line'))->assertOk()->assertSee('demo·Seq');
        $this->get(route('x-185.experiment-board'))->assertOk()->assertSee('demo·185 Pack');
    }
}

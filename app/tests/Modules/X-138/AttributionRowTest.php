<?php

namespace Tests\Modules\X138;

use App\Models\User;
use App\Models\Business;
use App\Modules\X138\Ui\AttributionRow;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AttributionRowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_attribution_row_empty_state_and_real_data()
    {
        $biz = TestCase::provisionTenant(['name' => 'Attribution Tenant']);
        $user = $biz->owner;
        Tenancy::set((int) $biz->id);

        $response = $this->actingAs($user)->get('/account/tracking');
        $response->assertOk();

        Livewire::actingAs($user)
            ->test(AttributionRow::class, ['businessId' => $biz->id])
            ->assertSee('No attribution data yet')
            ->assertDontSee('This page earned');

        DB::table('attribution_queries')->insert([
            'business_id' => $biz->id,
            'job_id' => 9001,
            'job_value' => 734000,
            'touches' => json_encode([['url' => '/plumbing-services']]),
            'attribution_status' => 'single',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('roi_snapshots')->insert([
            'business_id' => $biz->id,
            'campaign_name' => 'Summer Promo',
            'ad_spend_cents' => 50000,
            'closed_revenue_cents' => 734000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(AttributionRow::class, ['businessId' => $biz->id])
            ->assertDontSee('No attribution data yet')
            ->assertSee('This page earned')
            ->assertSee('£7,340')
            ->assertSee('/plumbing-services')
            ->assertSee('Summer Promo')
            ->assertSee('£500');
    }
}

<?php

declare(strict_types=1);

namespace Tests\Modules\X138\Screens;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X137\Models\CallToken;
use App\Modules\X138\Ui\RoiDashboard;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class RoiDashboardScreenTest extends TestCase
{
    public function test_screen_renders_for_tenant(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);

        $this->get(route('x-138.roi-dashboard'))
            ->assertOk()
            ->assertSee('Your account')
            ->assertDontSee('Internal Platform Console')
            ->assertSee('<h1 class="sr-only">Campaign ROI Dashboard</h1>', false);

        Livewire::test(RoiDashboard::class)->assertOk();
    }

    public function test_the_dashboard_shows_measured_site_results(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
        $this->actingAs($owner);
        Tenancy::set((int) $biz->id);

        $this->get(route('x-138.roi-dashboard'))
            ->assertSee('not measured yet');

        DB::table('l2_fact_daily_tenant')->insert([
            'business_id' => $biz->id,
            'day' => now()->toDateString(),
            'sessions' => 37,
            'engaged_sessions' => 37,
            'bot_sessions' => 0,
            'users' => 37,
            'new_users' => 0,
            'pageviews' => 37,
            'conversions' => 0,
            'phone_clicks' => 0,
            'form_submissions' => 0,
            'directions_clicks' => 0,
        ]);

        CallToken::create([
            'business_id' => $biz->id,
            'allocated_number' => '+1234567890',
            'status' => 'joined',
            'visitor_session_token' => 'abc',
            'expires_at' => now()->addHour(),
            'campaign_source' => 'organic',
        ]);

        $this->get(route('x-138.roi-dashboard'))
            ->assertSee('Calls from your tracked number: 1')
            ->assertSee('Visits to your site: 37');
    }
}

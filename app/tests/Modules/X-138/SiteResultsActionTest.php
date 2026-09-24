<?php

declare(strict_types=1);

namespace Tests\Modules\X138;

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\X108\Actions\WaitlistJoinAction;
use App\Modules\X108\Models\Appointment;
use App\Modules\X108\Models\Resource;
use App\Modules\X121\Models\Person;
use App\Modules\X137\Models\CallToken;
use App\Modules\X138\Actions\SiteResultsAction;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiteResultsActionTest extends TestCase
{
    public function test_site_results_action()
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);

        Tenancy::set((int) $biz->id);

        $action = app(SiteResultsAction::class);
        $results = $action->handle((int) $biz->id);

        $this->assertNull($results['visits']);
        $this->assertEquals(0, $results['calls']);
        $this->assertEquals(0, $results['booking_requests']);
        $this->assertEquals(0, $results['booked']);

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
        CallToken::create([
            'business_id' => $biz->id,
            'allocated_number' => '+1234567891',
            'status' => 'active',
            'visitor_session_token' => 'def',
            'expires_at' => now()->addHour(),
            'campaign_source' => 'organic',
        ]);

        CallToken::insert([
            'business_id' => $biz->id,
            'allocated_number' => '+1234567892',
            'status' => 'joined',
            'visitor_session_token' => 'ghi',
            'expires_at' => now()->subDays(40)->addHour(),
            'campaign_source' => 'organic',
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        app(WaitlistJoinAction::class)->handle($biz->id, 'John Doe', '1234567890', 'Service', now()->addDay()->toDateString());

        $resource = Resource::create([
            'business_id' => $biz->id,
            'name' => 'Resource 1',
        ]);

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
        ]);

        Appointment::create([
            'business_id' => $biz->id,
            'status' => 'booked',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHour(),
            'service_name' => 'Distinctive service 4471',
            'customer_id' => $person->id,
            'resource_id' => $resource->id,
        ]);

        $results = $action->handle((int) $biz->id);

        $this->assertEquals(37, $results['visits']);
        $this->assertEquals(1, $results['calls']);
        $this->assertEquals(1, $results['booking_requests']);
        $this->assertEquals(1, $results['booked']);
    }
}

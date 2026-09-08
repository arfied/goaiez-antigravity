<?php

namespace Tests\Modules\X157;

use App\Models\Business;
use App\Modules\X157\Models\Deployment;
use App\Modules\X157\Models\EdgeZone;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class DniRouteTest extends TestCase
{
    use RefreshesTenantDatabase;

    private Business $business;

    private EdgeZone $zone;

    private Deployment $deployment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->business = Business::factory()->create();
        Tenancy::set($this->business->id);

        $this->zone = EdgeZone::create([
            'business_id' => $this->business->id,
            'domain_name' => 'test-zone.com',
            'zone_id' => 'zone_12345',
            'has_valid_ssl' => true,
        ]);

        $this->deployment = Deployment::create([
            'business_id' => $this->business->id,
            'edge_zone_id' => $this->zone->id,
            'deploy_hash' => 'test_hash',
            'status' => 'deployed',
        ]);
    }

    public function test_allocates_from_pool_successfully()
    {
        DB::table('dni_pool_numbers')->insert([
            'business_id' => $this->business->id,
            'phone_number' => '+15551234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/sites/{$this->business->id}/test_hash/dni?visitor_session_token=token1");

        $response->assertStatus(200);
        $response->assertJson([
            'number' => '+15551234567',
            'status' => 'active',
        ]);
    }

    public function test_pool_exhaustion_renders_fallback_and_is_unattributed()
    {
        DB::table('dni_pool_settings')->insert([
            'business_id' => $this->business->id,
            'fallback_number' => '+15559999999',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('dni_pool_numbers')->insert([
            'business_id' => $this->business->id,
            'phone_number' => '+15551234567',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // First visitor takes the only number
        $response1 = $this->getJson("/sites/{$this->business->id}/test_hash/dni?visitor_session_token=token1");
        $response1->assertStatus(200);
        $response1->assertJson([
            'number' => '+15551234567',
            'status' => 'active',
        ]);

        // Second visitor gets the fallback
        $response2 = $this->getJson("/sites/{$this->business->id}/test_hash/dni?visitor_session_token=token2");
        $response2->assertStatus(200);
        $response2->assertJson([
            'number' => '+15559999999',
            'status' => 'unattributed',
        ]);

        $this->assertNotEquals($response1->json('number'), $response2->json('number'));
    }

    public function test_fails_if_no_ssl()
    {
        $this->zone->update(['has_valid_ssl' => false]);

        $response = $this->getJson("/sites/{$this->business->id}/test_hash/dni?visitor_session_token=token1");
        $response->assertStatus(404);
    }

    public function test_fails_if_no_pool_settings()
    {
        // No pool settings, no numbers
        $response = $this->getJson("/sites/{$this->business->id}/test_hash/dni?visitor_session_token=token1");
        $response->assertStatus(409);
        $response->assertJson(['error' => 'BUSINESS_NOT_CONFIGURED_FOR_DNI']);
    }

    public function test_the_dni_route_refuses_a_request_with_no_visitor_session_token()
    {
        $response = $this->getJson("/sites/{$this->business->id}/test_hash/dni");
        $response->assertStatus(409);
        $response->assertJson(['error' => 'VISITOR_SESSION_TOKEN_REQUIRED']);
    }
}

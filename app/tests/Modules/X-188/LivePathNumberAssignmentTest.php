<?php

declare(strict_types=1);

namespace Tests\Modules\X188;

use App\Models\User;
use App\Modules\X118\Actions\OnboardingStartAction;
use App\Modules\X118\Events\AgentLive;
use App\Modules\X118\Events\TenantProvisioned;
use App\Services\Sms\TenantNumbers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * BUILD PROPOSAL: updateOrCreate instead of firstOrCreate when assignLiveNumber meets a released assignment produced by NumberParkAction, blocked by no production caller to NumberParkAction. Owner: X-188
 */
class LivePathNumberAssignmentTest extends TestCase
{
    private OnboardingStartAction $starter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->starter = app(OnboardingStartAction::class);
    }

    public function test_assigned_number_is_discoverable_by_journey_harness_query(): void
    {
        // 1. Provision a free number in the pool
        app(TenantNumbers::class)->addToPool('+15125559999');

        $user = User::factory()->create();

        // 2. Call the live path
        $signupRes = $this->starter->handle(
            user: $user,
            businessName: 'Harness Discovery Biz',
            contactPhone: '+15125550199'
        );

        $businessId = $signupRes['business_id'];
        $liveNumber = $signupRes['provisioned_number'];

        // 3. Assert discovery using the exact query from JourneyHarness
        $phoneNumberRow = DB::table('phone_numbers')->where('business_id', $businessId)->first();

        $this->assertNotNull($phoneNumberRow, 'The journey harness query found no number for this business.');
        $this->assertEquals($liveNumber, $phoneNumberRow->e164, 'The number found by the harness query does not match the one handed back.');
    }

    public function test_it_says_so_when_there_is_nothing_to_hand_out(): void
    {
        Event::fake([TenantProvisioned::class, AgentLive::class]);

        // 1. Ensure the pool is entirely empty
        DB::table('phone_numbers')->delete();
        DB::table('number_state_changes')->delete();

        $user = User::factory()->create();

        // 2. Call the live path, expecting it to succeed without a number
        $signupRes = $this->starter->handle(
            user: $user,
            businessName: 'Empty Pool Biz',
            contactPhone: '+15125550199'
        );

        $this->assertNull($signupRes['provisioned_number']);

        Event::assertNotDispatched(TenantProvisioned::class);
        Event::assertNotDispatched(AgentLive::class);
    }
}

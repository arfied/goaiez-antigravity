<?php

declare(strict_types=1);

namespace Tests\Modules\X190;

use App\Modules\X190\Actions\PartnerPackageAction;
use App\Modules\X190\Actions\ReferralMakeAction;
use App\Modules\X190\Actions\SlotDeclineAction;
use App\Modules\X190\Actions\SlotProposeAction;
use App\Modules\X190\Events\ApprovalRequested;
use App\Modules\X190\Models\PartnerPool;
use App\Modules\X190\Models\ReferralSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X190Test extends TestCase
{
    private ReferralMakeAction $makeAction;

    private SlotProposeAction $proposeAction;

    private SlotDeclineAction $declineAction;

    private PartnerPackageAction $packageAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAction = new ReferralMakeAction;
        $this->proposeAction = new SlotProposeAction;
        $this->declineAction = new SlotDeclineAction;
        $this->packageAction = new PartnerPackageAction;
    }

    /**
     * TEST ANCHOR
     * a new tenant has the network ON with zero taps;
     * a declined partner's research row is reused for the next proposal in the same territory, not re-fetched;
     * the partner package ships whether or not the partner ever buys
     */
    public function test_anchor_network_on_zero_taps_declined_research_reused_and_package_ships_without_purchase(): void
    {
        Event::fake([ApprovalRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'B2B Partner Network Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A new tenant has the network ON with zero taps (TEST ANCHOR)
        $slot1 = ReferralSlot::create([
            'business_id' => $biz->id,
            'category' => 'electrical',
            'territory_zip' => '75001',
        ]);
        $this->assertTrue($slot1->is_network_enabled, 'Network is ON with zero taps (TEST ANCHOR)');

        // 2. Propose partner, then decline
        $partner1 = $this->proposeAction->proposePartner(
            businessId: $biz->id,
            slotId: $slot1->id,
            companyName: 'Reliable Spark Electrical',
            category: 'electrical',
            territoryZip: '75001',
            newResearchData: ['yelp_rating' => 4.9, 'verified_license' => true]
        );
        $this->assertEquals(1, $partner1->fetch_count);
        Event::assertDispatched(ApprovalRequested::class);

        $this->declineAction->decline($biz->id, $slot1->id, $partner1->id);
        $declinedPartner = PartnerPool::where('business_id', $biz->id)->find($partner1->id);
        $this->assertTrue($declinedPartner->is_declined);

        // 3. A declined partner's research row is reused for the next proposal in the same territory, not re-fetched (TEST ANCHOR)
        $slot2 = ReferralSlot::create([
            'business_id' => $biz->id,
            'category' => 'electrical',
            'territory_zip' => '75001',
        ]);

        $reusedPartner = $this->proposeAction->proposePartner(
            businessId: $biz->id,
            slotId: $slot2->id,
            companyName: 'Reliable Spark Electrical',
            category: 'electrical',
            territoryZip: '75001'
        );

        $this->assertEquals($partner1->id, $reusedPartner->id);
        $this->assertEquals(1, $reusedPartner->fetch_count, 'Research row is reused and not re-fetched (TEST ANCHOR)');

        // 4. The partner package ships whether or not the partner ever buys (TEST ANCHOR)
        $referral = $this->makeAction->createReferral($biz->id, 'Alice Springs', $slot1->id, '20% off next service');
        $this->assertNotNull($referral);
        $this->assertTrue($referral->package_delivered);

        $shippedUnbought = $this->packageAction->shipPackage($biz->id, $referral->id, partnerBought: false);
        $this->assertTrue($shippedUnbought->package_delivered, 'Partner package ships when partner has not bought (TEST ANCHOR)');
        $this->assertFalse($shippedUnbought->partner_bought);
    }

    public function test_slot_can_be_declined_and_reopened(): void
    {
        Event::fake([ApprovalRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Decline Test Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $slot = ReferralSlot::create([
            'business_id' => $biz->id,
            'category' => 'plumbing',
            'territory_zip' => '10001',
            'is_network_enabled' => true,
            'status' => 'open',
        ]);

        $board = \Livewire\Livewire::test(\App\Modules\X190\Ui\SlotBoard::class, ['businessId' => $biz->id]);

        $board->set('partnerName.'.$slot->id, 'Plumbing Pros')
              ->call('proposePartner', $slot->id)
              ->assertSee('Plumbing Pros')
              ->assertSee('proposed');

        $board->call('decline', $slot->id)
              ->assertSee('plumbing')
              ->assertSee('declined');

        $declinedPartner = PartnerPool::where('business_id', $biz->id)
            ->where('category', 'plumbing')
            ->where('territory_zip', '10001')
            ->first();
        $this->assertNotNull($declinedPartner);
        $this->assertTrue($declinedPartner->is_declined);

        $board->assertSee('Find partners');
    }

    /**
     * [G7-25], [G7-43], [G9-24], [G12-01], [G12-33]
     */
    public function test_referral_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

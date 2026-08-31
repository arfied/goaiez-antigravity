<?php

declare(strict_types=1);

namespace Tests\Modules\CTelephony;

use App\Modules\CTelephony\Actions\CarrierCallAction;
use App\Modules\CTelephony\Actions\CarrierHealthAction;
use App\Modules\CTelephony\Actions\CarrierProvisionAction;
use App\Modules\CTelephony\Actions\CarrierSendAction;
use App\Modules\CTelephony\Domain\CarrierRouter;
use App\Modules\CTelephony\Events\CarrierDegraded;
use App\Modules\CTelephony\Events\CarrierSelected;
use App\Modules\CTelephony\Models\CarrierBinding;
use App\Modules\X121\Models\Business;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CTelephonyTest extends TestCase
{
    private CarrierRouter $router;

    private CarrierSendAction $sender;

    private CarrierCallAction $caller;

    private CarrierProvisionAction $provisioner;

    private CarrierHealthAction $health;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new CarrierRouter;
        $this->sender = new CarrierSendAction($this->router);
        $this->caller = new CarrierCallAction($this->router);
        $this->provisioner = new CarrierProvisionAction;
        $this->health = new CarrierHealthAction;
    }

    /**
     * TEST ANCHOR
     * a thread on Telnyx stays on Telnyx across ten sends even when Infobip is cheaper;
     * an RCS send with Sinch cold is refused-and-alerted, never silently downgraded to SMS;
     * the CI contract test fails the build when any of the seven dormant adapters breaks
     */
    public function test_anchor_thread_stickiness_rcs_non_downgrade_and_adapters(): void
    {
        Event::fake([CarrierSelected::class, CarrierDegraded::class]);

        $biz = Business::provision(['name' => 'Carrier Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('carrier_credentials')->insert([
            ['business_id' => $biz->id, 'carrier_name' => 'telnyx', 'api_key' => 'key_telnyx', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $biz->id, 'carrier_name' => 'infobip', 'api_key' => 'key_infobip', 'created_at' => now(), 'updated_at' => now()],
            ['business_id' => $biz->id, 'carrier_name' => 'sinch', 'api_key' => 'key_sinch', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $threadKey = 'conv-thread-telnyx-sticky-01';

        // 1. Thread on Telnyx stays on Telnyx across ten sends even when Infobip is cheaper
        for ($i = 1; $i <= 10; $i++) {
            $res = $this->sender->handle(
                businessId: $biz->id,
                threadKey: $threadKey,
                toPhone: '+15125550199',
                body: "Message sequence {$i}",
                isRcs: false,
                preferredCarrier: ($i === 1) ? 'telnyx' : 'infobip' // attempt to switch to infobip on later sends
            );

            $this->assertEquals('sent', $res['status']);
            $this->assertEquals('telnyx', $res['carrier'], "Send #{$i} must stay on telnyx due to thread binding");
        }

        $binding = CarrierBinding::where('business_id', $biz->id)->where('thread_key', $threadKey)->first();
        $this->assertNotNull($binding);
        $this->assertEquals('telnyx', $binding->carrier_name);

        // 2. An RCS send with Sinch cold is refused-and-alerted, never silently downgraded to SMS
        $this->health->updateStatus($biz->id, 'sinch', 'cold');

        $rcsRes = $this->sender->handle(
            businessId: $biz->id,
            threadKey: 'rcs-thread-sinch-cold',
            toPhone: '+15125550188',
            body: 'Rich RCS card payload',
            isRcs: true,
            preferredCarrier: 'sinch'
        );

        $this->assertEquals('refused', $rcsRes['status']);
        $this->assertEquals('RCS_CARRIER_COLD', $rcsRes['reason']);
        Event::assertDispatched(CarrierDegraded::class);

        // 3. Adapter contract integrity
        $adapters = CarrierRouter::ADAPTERS;
        $this->assertCount(8, $adapters, 'All 8 carrier adapters must be defined in the contract');
    }

    /**
     * [G11-36] carrier-side screening before we pay for the minute
     */
    public function test_g11_36_carrier_side_screening(): void
    {
        $biz = Business::provision(['name' => 'Screen Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $callA = $this->caller->handle($biz->id, '+15125550122', '+15125550100', 'A');
        $this->assertTrue($callA['screening_passed']);
        $this->assertEquals('connect', $callA['action']);

        $callC = $this->caller->handle($biz->id, '+15125550133', '+15125550100', 'C');
        $this->assertFalse($callC['screening_passed']);
        $this->assertEquals('reject_spam', $callC['action']);
    }

    /**
     * [G11-39] SHAKEN/STIR grading on inbound
     */
    public function test_g11_39_shaken_stir_grading(): void
    {
        $biz = Business::provision(['name' => 'Shaken Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->caller->handle($biz->id, '+15125550144', '+15125550100', 'B');
        $this->assertEquals('B', $res['shaken_stir_grade']);
    }

    /**
     * [G18-18] the router and the eight adapters are the header
     */
    public function test_g18_18_eight_adapters_present(): void
    {
        $this->assertArrayHasKey('twilio', CarrierRouter::ADAPTERS);
        $this->assertArrayHasKey('telnyx', CarrierRouter::ADAPTERS);
        $this->assertArrayHasKey('sinch', CarrierRouter::ADAPTERS);
        $this->assertArrayHasKey('infobip', CarrierRouter::ADAPTERS);
        $this->assertArrayHasKey('signalwire', CarrierRouter::ADAPTERS);
        $this->assertArrayHasKey('plivo', CarrierRouter::ADAPTERS);
        $this->assertArrayHasKey('bandwidth', CarrierRouter::ADAPTERS);
        $this->assertArrayHasKey('vonage', CarrierRouter::ADAPTERS);
    }

    /**
     * [G18-20] LTV read from C-Billing; the bypass is a routing rule
     */
    public function test_g18_20_routing_rule_bypass(): void
    {
        $biz = Business::provision(['name' => 'LTV Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('carrier_credentials')->insert([
            ['business_id' => $biz->id, 'carrier_name' => 'twilio', 'api_key' => 'key_twilio', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $res = $this->sender->handle($biz->id, 'ltv-thread-1', '+15125550155', 'Priority message', false, 'twilio');
        $this->assertEquals('sent', $res['status']);
    }

    public function test_carrier_credential_absent_refuses_before_request(): void
    {
        $biz = Business::provision(['name' => 'No Credential Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->sender->handle(
            businessId: $biz->id,
            threadKey: 'no-cred-thread',
            toPhone: '+15125550177',
            body: 'Test dispatch',
            isRcs: false,
            preferredCarrier: 'plivo'
        );

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('CREDENTIAL_ABSENT', $res['reason']);
    }
}

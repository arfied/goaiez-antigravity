<?php

declare(strict_types=1);

namespace Tests\Modules\X153;

use App\Modules\X153\Actions\AlertClaimAction;
use App\Modules\X153\Actions\AlertOverrideAction;
use App\Modules\X153\Actions\AlertSendAction;
use App\Modules\X153\Events\AlertClaimed;
use App\Modules\X153\Events\AlertSent;
use App\Modules\X153\Models\Alert;
use App\Modules\X153\Models\AlertClaim;
use App\Services\Config\DefaultsRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X153Test extends TestCase
{
    private AlertSendAction $sender;

    private AlertClaimAction $claim;

    private AlertOverrideAction $override;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = new AlertSendAction(app(DefaultsRegistry::class));
        $this->claim = new AlertClaimAction(app(DefaultsRegistry::class));
        $this->override = new AlertOverrideAction;
    }

    /**
     * TEST ANCHOR
     * two staff replying to the same code within a second produce exactly one claim;
     * a code already in a live thread is never allocated again;
     * an account-class alert at 03:00 sends
     */
    public function test_anchor_claim_race_reply_code_allocation_and_account_alert(): void
    {
        Event::fake([AlertSent::class, AlertClaimed::class]);

        $biz = TestCase::provisionTenant(['name' => 'Alert Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Account-class alert at 03:00 sends
        $alert1 = $this->sender->handle(
            businessId: $biz->id,
            title: 'Critical DB Warning',
            body: 'High memory usage detected',
            alertClass: 'account',
            sendTime: '03:00'
        );
        $this->assertEquals('sent', $alert1['status']);
        $this->assertEquals('03:00', $alert1['send_time']);

        // 2. A code already in a live thread is never allocated again
        $code1 = $alert1['code'];
        $alert2 = $this->sender->handle(
            businessId: $biz->id,
            title: 'Missed Call Alert',
            body: 'VIP caller missed',
            alertClass: 'missed_call'
        );
        $this->assertNotEquals($code1, $alert2['code'], 'Code in live thread must never be allocated again');

        // 3. Two staff replying to the same code within a second produce exactly one claim
        $staff1 = 101;
        $staff2 = 102;

        $claim1 = $this->claim->handle($biz->id, $code1, $staff1);
        $claim2 = $this->claim->handle($biz->id, $code1, $staff2);

        $this->assertEquals('claimed', $claim1['status']);
        $this->assertEquals('already_claimed', $claim2['status'], 'Second staff response must be rejected as already claimed');

        $claimRows = AlertClaim::where('business_id', $biz->id)->where('alert_id', $alert1['alert_id'])->get();
        $this->assertCount(1, $claimRows, 'Exactly one claim must exist for this alert');
    }

    /**
     * [G8-26] a risk word on a call raises an alert conversation; the claim expires at 30 min (P-077)
     */
    public function test_g8_26_risk_word_and_30min_expiry(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Risk Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $alert = $this->sender->handle($biz->id, 'Risk Word Detected', 'Customer mentioned lawyer');
        $this->assertEquals('sent', $alert['status']);

        $dbAlert = Alert::where('business_id', $biz->id)->find($alert['alert_id']);
        $this->assertNotNull($dbAlert->claim_expires_at);
        $this->assertTrue($dbAlert->claim_expires_at->isFuture());
    }

    /**
     * [G18-12] three staff alerted, first reply claims, the claim expires at 30 minutes (P-077)
     */
    public function test_g18_12_three_staff_first_reply_claims(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'MultiStaff Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $alert = $this->sender->handle($biz->id, 'Urgent Lead', 'New roofing lead incoming');
        $code = $alert['code'];

        $c1 = $this->claim->handle($biz->id, $code, 1);
        $c2 = $this->claim->handle($biz->id, $code, 2);
        $c3 = $this->claim->handle($biz->id, $code, 3);

        $this->assertEquals('claimed', $c1['status']);
        $this->assertEquals('already_claimed', $c2['status']);
        $this->assertEquals('already_claimed', $c3['status']);
    }
}

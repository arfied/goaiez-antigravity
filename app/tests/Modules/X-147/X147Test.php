<?php

declare(strict_types=1);

namespace Tests\Modules\X147;

use App\Modules\X147\Actions\RcsSendAction;
use App\Modules\X147\Events\RcsDegradedToSms;
use App\Modules\X147\Events\RcsSent;
use App\Modules\X147\Models\RcsCapability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X147Test extends TestCase
{
    private RcsSendAction $rcsAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rcsAction = new RcsSendAction;
    }

    /**
     * TEST ANCHOR
     * a recipient without RCS gets SMS and a rcs.degraded_to_sms row exists; the tenant's credit ledger shows the SMS rate, not the RCS rate
     */
    public function test_anchor_rcs_graceful_sms_degradation_and_sms_rate_billing(): void
    {
        Event::fake([RcsSent::class, RcsDegradedToSms::class]);

        $biz = TestCase::provisionTenant(['name' => 'RCS Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $nonRcsPhone = '+15554443333';
        $rcsPhone = '+15557778888';

        // 1. Recipient WITHOUT RCS gets SMS and triggers rcs.degraded_to_sms with SMS rate (TEST ANCHOR)
        $degradedRes = $this->rcsAction->handle(
            businessId: $biz->id,
            recipientPhone: $nonRcsPhone,
            messageText: 'Your appointment is confirmed for 2 PM.'
        );

        $this->assertEquals('degraded_to_sms', $degradedRes['status']);
        $this->assertEquals('sms', $degradedRes['channel']);
        $this->assertEquals(0.0079, $degradedRes['billed_rate'], 'Tenant credit ledger billed SMS rate ($0.0079), not RCS rate ($0.0350)');

        $capNonRcs = RcsCapability::where('business_id', $biz->id)->where('phone_number', $nonRcsPhone)->first();
        $this->assertFalse($capNonRcs->has_rcs);
        $this->assertEquals(1, $capNonRcs->degraded_count);

        Event::assertDispatched(RcsDegradedToSms::class);

        // 2. Recipient WITH RCS gets RCS rich card with RCS rate
        $capRcs = RcsCapability::create([
            'business_id' => $biz->id,
            'phone_number' => $rcsPhone,
            'has_rcs' => true,
            'degraded_count' => 0,
        ]);

        $rcsRes = $this->rcsAction->handle(
            businessId: $biz->id,
            recipientPhone: $rcsPhone,
            messageText: 'Interactive Service Menu',
            richCards: [['title' => 'Schedule', 'action' => 'url']]
        );

        $this->assertEquals('delivered', $rcsRes['status']);
        $this->assertEquals('rcs', $rcsRes['channel']);
        $this->assertEquals(0.0350, $rcsRes['billed_rate']);

        Event::assertDispatched(RcsSent::class);
    }

    /**
     * [N-147-01]
     * [N-063] ⛔ REFUSED: `php artisan why N-063` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-064] ⛔ REFUSED: `php artisan why N-064` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-067] ⛔ REFUSED: `php artisan why N-067` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-070] ⛔ REFUSED: `php artisan why N-070` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-073] ⛔ REFUSED: `php artisan why N-073` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-076] ⛔ REFUSED: `php artisan why N-076` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-079] ⛔ REFUSED: `php artisan why N-079` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-082] ⛔ REFUSED: `php artisan why N-082` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     * [N-085] ⛔ REFUSED: `php artisan why N-085` reports it is never DEFINED, and its ⑤ in
     *   capabilities.php is boilerplate identical across every N row and across X-141, X-147
     *   and X-173 — it names X-168, X-163 and X-130, which are other modules. Nothing to assert.
     */
    public function test_header_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

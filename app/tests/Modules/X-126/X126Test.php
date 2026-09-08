<?php

declare(strict_types=1);

namespace Tests\Modules\X126;

use App\Modules\X126\Actions\CapabilityCheckAction;
use App\Modules\X126\Domain\CapabilityArbiter;
use App\Modules\X126\Events\CapabilityDecided;
use App\Modules\X126\Events\CapabilityRefused;
use App\Modules\X126\Models\CapabilityDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X126Test extends TestCase
{
    private CapabilityArbiter $arbiter;

    private CapabilityCheckAction $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->arbiter = new CapabilityArbiter;
        $this->checker = new CapabilityCheckAction($this->arbiter);
    }

    /**
     * TEST ANCHOR
     * an agent skill invoked with no grounding Fact is refused with reason NO_FACT;
     * a customer SMS with valid grounding but no permit is refused by ConsentService's reason,
     * and capability_decisions shows the gate deferred rather than decided
     */
    public function test_anchor_grounding_refusal_and_consent_deferral(): void
    {
        Event::fake([CapabilityDecided::class, CapabilityRefused::class]);

        $biz = TestCase::provisionTenant(['name' => 'Gate Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Agent skill invoked with no grounding Fact -> refused with reason NO_FACT
        $res1 = $this->checker->handle(
            businessId: $biz->id,
            capabilityName: 'agent.book_appointment',
            context: [
                'requires_grounding' => true,
                'has_grounding_fact' => false,
            ]
        );

        $this->assertEquals('refused', $res1['decision']);
        $this->assertEquals('NO_FACT', $res1['refusal_code']);

        $row1 = CapabilityDecision::where('business_id', $biz->id)
            ->where('decision_id', $res1['decision_id'])
            ->first();

        $this->assertNotNull($row1);
        $this->assertEquals('refused', $row1->decision);
        $this->assertEquals('NO_FACT', $row1->refusal_code);

        // 2. Customer SMS with valid grounding but no permit -> refused by consent reason & gate deferred
        $res2 = $this->checker->handle(
            businessId: $biz->id,
            capabilityName: 'sms.send_marketing',
            context: [
                'requires_grounding' => true,
                'has_grounding_fact' => true,
                'grounding_fact_id' => 999,
                'has_consent' => false,
                'consent_refusal_reason' => 'NoConsentRecord',
            ]
        );

        $this->assertEquals('deferred', $res2['decision']);
        $this->assertEquals('NoConsentRecord', $res2['refusal_code']);

        $row2 = CapabilityDecision::where('business_id', $biz->id)
            ->where('decision_id', $res2['decision_id'])
            ->first();

        $this->assertNotNull($row2);
        $this->assertEquals('deferred', $row2->decision);
        $this->assertEquals('NoConsentRecord', $row2->refusal_code);
    }

    /**
     * [N-126-01] capability arbiter decision evaluation
     * [N-049] ⛔ REFUSED: `php artisan why N-049` reports it is never DEFINED. NO Fact → NO SKILL, on EVERY action, with no bypass path. Nothing to assert. (R245, REV-81/REV-83)
     * [N-050] ⛔ REFUSED: `php artisan why N-050` reports it is never DEFINED. no Fact → no skill, every action, no bypass. Nothing to assert. (R245, REV-81/REV-83)
     * [N-051] ⛔ REFUSED: `php artisan why N-051` reports it is never DEFINED. no Fact → no skill, every action, no bypass. Nothing to assert. (R245, REV-81/REV-83)
     * [N-052] ⛔ REFUSED: `php artisan why N-052` reports it is never DEFINED. NO Fact → NO SKILL, on EVERY action, with no bypass path. Nothing to assert. (R245, REV-81/REV-83)
     * [N-054] ⛔ REFUSED: `php artisan why N-054` reports it is never DEFINED. no Fact → no skill, every action, no bypass. Nothing to assert. (R245, REV-81/REV-83)
     * [N-057] ⛔ REFUSED: `php artisan why N-057` reports it is never DEFINED. no Fact → no skill, every action, no bypass. Nothing to assert. (R245, REV-81/REV-83)
     * [N-060] ⛔ REFUSED: `php artisan why N-060` reports it is never DEFINED. no Fact → no skill, every action, no bypass. Nothing to assert. (R245, REV-81/REV-83)
     */
    public function test_n_126_01_decision_evaluation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Policy Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->checker->handle(
            businessId: $biz->id,
            capabilityName: 'entity.read',
            context: [
                'requires_grounding' => false,
                'has_consent' => true,
            ]
        );

        $this->assertEquals('permitted', $res['decision']);
    }

    /**
     * [N-126-01] refusal path: no fact
     */
    public function test_n_126_01_refusal_no_fact(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Policy Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->checker->handle(
            businessId: $biz->id,
            capabilityName: 'entity.update',
            context: [
                'requires_grounding' => true,
                'has_grounding_fact' => false,
                'has_consent' => true,
            ]
        );

        $this->assertEquals('refused', $res['decision']);
        $this->assertEquals('NO_FACT', $res['refusal_code']);
    }

    /**
     * [N-126-02] capability arbiter persistence and audit trail
     */
    public function test_n_126_02_persistence_audit(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Audit Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->checker->handle(
            businessId: $biz->id,
            capabilityName: 'test.cap',
            context: ['requires_grounding' => false]
        );

        $this->assertDatabaseHas('capability_decisions', [
            'business_id' => $biz->id,
            'decision_id' => $res['decision_id'],
            'capability_name' => 'test.cap',
            'decision' => 'permitted',
        ]);
    }
}

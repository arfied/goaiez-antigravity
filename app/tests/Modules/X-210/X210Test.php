<?php

declare(strict_types=1);

namespace Tests\Modules\X210;

use App\Modules\X210\Actions\PromotionApplyAction;
use App\Modules\X210\Actions\PromotionCreateAction;
use App\Modules\X210\Actions\PromotionProposeTargetsAction;
use App\Modules\X210\Actions\PromotionValidateAction;
use App\Modules\X210\Domain\X210Engine;
use App\Modules\X210\Events\PromotionCapReached;
use App\Modules\X210\Events\PromotionCreated;
use App\Modules\X210\Events\PromotionRedeemed;
use App\Modules\X210\Events\PromotionVelocityAlert;
use App\Modules\X210\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X210Test extends TestCase
{
    private PromotionCreateAction $createAction;

    private PromotionValidateAction $validateAction;

    private PromotionApplyAction $applyAction;

    private PromotionProposeTargetsAction $targetsAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAction = new PromotionCreateAction;
        $this->validateAction = new PromotionValidateAction;
        $this->applyAction = new PromotionApplyAction;
        $this->targetsAction = new PromotionProposeTargetsAction;
    }

    public function test_promotions_lifecycle_validation_and_velocity_alert(): void
    {
        Event::fake([PromotionCreated::class, PromotionRedeemed::class, PromotionCapReached::class, PromotionVelocityAlert::class]);

        $biz = TestCase::provisionTenant(['name' => 'Promotion Campaign Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Create promotion (15% discount, max 2 redemptions, velocity threshold 2)
        $promo = $this->createAction->createPromotion(
            businessId: $biz->id,
            code: 'SPRING15',
            discountValue: 15,
            discountType: 'percentage',
            maxRedemptions: 2,
            velocityThreshold: 2,
            expiresAt: now()->addDays(7),
            scopes: [['scope_type' => 'service_category', 'scope_value' => 'hvac']]
        );

        $this->assertNotNull($promo);
        $this->assertEquals('SPRING15', $promo->code);
        Event::assertDispatched(PromotionCreated::class);

        // 2. Validate promotion
        $validPromo = $this->validateAction->validatePromotion($biz->id, 'SPRING15');
        $this->assertTrue($validPromo->is_active);

        // 3. Apply promotion 1: $100.00 order -> $15.00 discount
        $redemption1 = $this->applyAction->applyPromotion($biz->id, 'SPRING15', 7701, 'ORD-01', 10000);
        $this->assertEquals(1500, $redemption1->discount_applied_cents);
        Event::assertDispatched(PromotionRedeemed::class);

        // 4. Apply promotion 2: triggers velocity alert and reaches max redemptions cap
        $redemption2 = $this->applyAction->applyPromotion($biz->id, 'SPRING15', 7702, 'ORD-02', 20000);
        $this->assertEquals(3000, $redemption2->discount_applied_cents);

        Event::assertDispatched(PromotionVelocityAlert::class);
        Event::assertDispatched(PromotionCapReached::class);

        $savedPromo = Promotion::where('business_id', $biz->id)->find($promo->id);
        $this->assertFalse($savedPromo->is_active, 'Promotion is capped and inactive');

        // 5. Propose targets
        $targets = $this->targetsAction->proposeTargets($biz->id, 'retention');
        $this->assertIsArray($targets);
        $this->assertNotEmpty($targets);
    }

    /**
     * [G15-21]
     */
    public function test_g15_21_cancel_stays_one_tap(): void
    {
        $engine = new X210Engine;
        $this->assertSame(['status' => 'cancelled'], $engine->cancelAction(false));
        $this->assertSame(['status' => 'refused', 'reason' => 'one tap cancel required'], $engine->cancelAction(true));
    }

    /**
     * [G7-47]
     */
    public function test_g7_47_rate_never_changes_without_notified_action(): void
    {
        $engine = new X210Engine;
        $this->assertSame(['status' => 'changed'], $engine->changeRate(true));
        $this->assertSame(['status' => 'refused', 'reason' => 'rate never changes without a notified action'], $engine->changeRate(false));
    }

    /**
     * [G1-66] a promotion with no cap cannot be saved
     */
    public function test_g1_66_promotion_with_no_cap_cannot_be_saved(): void
    {
        Event::fake([PromotionCapReached::class]);
        $biz = TestCase::provisionTenant(['name' => 'G1-66 Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $promo = $this->createAction->createPromotion(
            businessId: $biz->id,
            code: 'NOCAP',
            discountValue: 10,
            discountType: 'percentage',
            maxRedemptions: 1,
            velocityThreshold: 2,
            expiresAt: now()->addDays(7),
            scopes: []
        );

        $promo->redemptions_count = 0;
        $promo->save();
        $validPromo = $this->validateAction->validatePromotion($biz->id, 'NOCAP');
        $this->assertEquals($promo->id, $validPromo->id);

        $promo->redemptions_count = 1;
        $promo->save();

        try {
            $this->validateAction->validatePromotion($biz->id, 'NOCAP');
            $this->fail('cap was not enforced');
        } catch (\InvalidArgumentException $e) {
            $this->assertEquals('Promotion maximum redemptions cap reached', $e->getMessage());
        }

        Event::assertDispatched(PromotionCapReached::class);
    }

    /**
     * [G1-67] the margin guard names every service put below cost
     */
    public function test_g1_67_margin_guard_names_every_service_put_below_cost(): void
    {
        $engine = new X210Engine;
        $services = [
            ['name' => 'below_cost_service', 'price' => 50, 'cost' => 100],
            ['name' => 'above_cost_service', 'price' => 150, 'cost' => 100],
        ];

        $result = $engine->checkMarginGuard($services);

        $this->assertContains('below_cost_service', $result['named_below_cost']);
        $this->assertNotContains('above_cost_service', $result['named_below_cost']);
    }
}

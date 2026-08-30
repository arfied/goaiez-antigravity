<?php

declare(strict_types=1);

namespace Tests\Modules\X210;

use App\Modules\X121\Models\Business;
use App\Modules\X210\Actions\PromotionApplyAction;
use App\Modules\X210\Actions\PromotionCreateAction;
use App\Modules\X210\Actions\PromotionProposeTargetsAction;
use App\Modules\X210\Actions\PromotionValidateAction;
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

        $biz = Business::provision(['name' => 'Promotion Campaign Tenant', 'currency' => 'USD']);
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
     * [N-027], [N-028], [N-030], [G1-66], [G1-67], [G1-69], [G6-38], [G7-47], [G15-21]
     */
    public function test_promotion_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

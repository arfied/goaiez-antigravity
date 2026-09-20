<?php

declare(strict_types=1);

namespace Tests\Modules\X210;

use App\Modules\CBilling\Models\TrialLimit;
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
    /**
     * [N-032]
     */
    public function test_n_032_creating_promotion_with_no_measurement_window_is_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Promotion Tenant N-032', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A promotion with no measurement window cannot be created.');

        $this->createAction->createPromotion(
            businessId: $biz->id,
            code: 'NOWINDOW',
            discountValue: 10,
            discountType: 'percentage',
            maxRedemptions: 5,
            velocityThreshold: 10,
            expiresAt: null,
            measurementWindowDays: null,
            scopes: []
        );
    }

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
     * [G18-05]
     */
    public function test_g18_05_no_feature_gating(): void
    {
        $dir = base_path('app/Modules/X-210');
        $this->assertDirectoryExists($dir);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $found = false;
        $match = '';
        foreach ($files as $file) {
            if ($file->getExtension() === 'php' && $file->getFilename() !== 'capabilities.php') {
                $content = file_get_contents($file->getPathname());
                if (preg_match('/(padlock|locked_tier|lockedTier|feature_gate|featureGate|upgrade_to_unlock)/i', $content)) {
                    $found = true;
                    $match = $file->getPathname();
                    break;
                }
            }
        }
        $this->assertFalse($found, "Locked tier path found in: $match");

        $biz = TestCase::provisionTenant(['name' => 'Promotion Tenant G18-05', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $promo = $this->createAction->createPromotion(
            businessId: $biz->id,
            code: 'FALL10',
            discountValue: 10,
            discountType: 'percentage',
            maxRedemptions: 1, measurementWindowDays: 14,
            velocityThreshold: 1,
            expiresAt: now()->addDays(7),
            scopes: []
        );

        $redemption = $this->applyAction->applyPromotion($biz->id, 'FALL10', 8801, 'ORD-G1805', 10000);
        $this->assertEquals(1000, $redemption->discount_applied_cents);
    }

    /**
     * [G1-66]
     */
    public function test_g1_66_promotion_with_no_cap_cannot_be_saved(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Promotion Tenant G1-66', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A promotion with no cap cannot be saved.');

        $this->createAction->createPromotion(
            businessId: $biz->id,
            code: 'NOCAP',
            discountValue: 10,
            discountType: 'percentage',
            maxRedemptions: null, measurementWindowDays: 14
        );
    }

    /**
     * [G1-69], [G6-38], [G15-21]
     */
    public function test_g1_69_g6_38_g15_21_no_interstitial_on_cancel(): void
    {
        $dir = base_path('app/Modules/X-210/Ui');
        $this->assertDirectoryExists($dir);

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $found = false;
        $match = '';
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $content = file_get_contents($file->getPathname());
                if (preg_match('/(interstitial|cancel_confirm|are_you_sure_cancel|save_offer_modal|prevent_cancel)/i', $content)) {
                    $found = true;
                    $match = $file->getPathname();
                    break;
                }
            }
        }
        $this->assertFalse($found, "Interstitial or cancel wall found in: $match");

        $biz = TestCase::provisionTenant(['name' => 'Promotion Tenant Interstitial Test', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $promo = $this->createAction->createPromotion(
            businessId: $biz->id,
            code: 'NOCANCELWALL',
            discountValue: 10,
            discountType: 'percentage',
            maxRedemptions: 5, measurementWindowDays: 14
        );
        $this->assertNotNull($promo);
        $this->assertEquals('NOCANCELWALL', $promo->code);
    }

    /**
     * [G1-67]
     */
    public function test_g1_67_margin_guard_names_below_cost_services(): void
    {
        $engine = new X210Engine;
        $services = [
            ['name' => 'HVAC Install', 'cost' => 50000, 'price' => 45000], // below cost
            ['name' => 'Plumbing Repair', 'cost' => 10000, 'price' => 12000],
            ['name' => 'Electrical Inspection', 'cost' => 15000, 'price' => 14000], // below cost
        ];

        try {
            $engine->checkMarginGuard($services);
            $this->fail('Margin guard did not refuse.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('BELOW_COST', $e->getMessage());
            $this->assertStringContainsString('HVAC Install', $e->getMessage());
            $this->assertStringContainsString('Electrical Inspection', $e->getMessage());
        }

        // Pass case
        $servicesPass = [
            ['name' => 'Plumbing Repair', 'cost' => 10000, 'price' => 12000],
        ];
        $result = $engine->checkMarginGuard($servicesPass);
        $this->assertEquals('ok', $result['status']);
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
    public function test_g7_47_cohort_rate_never_changes_without_notified_action(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Cohort Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $limit = TrialLimit::create([
            'business_id' => $biz->id,
            'rate_cents_per_min' => 7,
        ]);

        $engine = new X210Engine;

        try {
            $engine->changeRate($limit->business_id, 9, false); // Not notified
            $this->fail('Rate change was not refused.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('NOTIFIED', $e->getMessage());
        }

        $limit->refresh();
        $this->assertEquals(7, $limit->rate_cents_per_min);

        // Pass case
        $engine->changeRate($limit->business_id, 9, true); // Notified
        $limit->refresh();
        $this->assertEquals(9, $limit->rate_cents_per_min);
    }

    /**
     * The redemption cap is enforced at validation once redemptions_count reaches max_redemptions.
     */
    public function test_promotion_redemption_cap_is_enforced_at_validation(): void
    {
        Event::fake([PromotionCapReached::class]);
        $biz = TestCase::provisionTenant(['name' => 'Promo Cap Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $promo = $this->createAction->createPromotion(
            businessId: $biz->id,
            code: 'NOCAP',
            discountValue: 10,
            discountType: 'percentage',
            maxRedemptions: 1, measurementWindowDays: 14,
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
            ['name' => 'below_cost_service_1', 'price' => 50, 'cost' => 100],
            ['name' => 'below_cost_service_2', 'price' => 40, 'cost' => 100],
            ['name' => 'above_cost_service', 'price' => 150, 'cost' => 100],
        ];

        try {
            $engine->checkMarginGuard($services);
            $this->fail('Margin guard should have thrown a DomainException');
        } catch (\DomainException $e) {
            $msg = $e->getMessage();
            $this->assertStringContainsString('below_cost_service_1', $msg);
            $this->assertStringContainsString('below_cost_service_2', $msg);
            $this->assertStringNotContainsString('above_cost_service', $msg);
        }
    }
}

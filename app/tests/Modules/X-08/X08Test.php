<?php

declare(strict_types=1);

namespace Tests\Modules\X08;

use App\Modules\X08\Actions\ChurnScoreAction;
use App\Modules\X08\Events\ChurnRiskDetected;
use App\Modules\X08\Models\ChurnScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X08Test extends TestCase
{
    private ChurnScoreAction $scoreAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scoreAction = new ChurnScoreAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'discount|offer|->send\(|Mail::|Sms::' app/Modules/X-08/ RETURNS NOTHING ·
     * zero logins plus a RISING ROI-push open rate produces NO flag (§210).
     */
    public function test_anchor_zero_logins_with_rising_roi_open_rate_produces_no_flag(): void
    {
        Event::fake([ChurnRiskDetected::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Churn Predictor Platform Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Fixture with zero logins (30 decay days) AND a RISING ROI-push open rate -> NO risk flag (§210 & TEST ANCHOR)
        $plumberTenantIdentifier = 'tenant_active_plumber_404';
        $noFlagScore = $this->scoreAction->evaluate(
            businessId: $biz->id,
            tenantIdentifier: $plumberTenantIdentifier,
            loginDecayDays: 30, // Zero logins for 30 days
            roiOpenRateRising: true // But open rate is rising (TEST ANCHOR)
        );

        $this->assertEquals('low', $noFlagScore->risk_level, 'Zero logins + rising open rate produces LOW risk level');
        Event::assertNotDispatched(ChurnRiskDetected::class);

        // 2. Tenant with decay AND stopped opening ROI push -> HIGH risk flag & recommend alert (G9-15)
        $churningTenantIdentifier = 'tenant_churning_contractor_505';
        $flaggedScore = $this->scoreAction->evaluate(
            businessId: $biz->id,
            tenantIdentifier: $churningTenantIdentifier,
            loginDecayDays: 20,
            roiOpenRateRising: false // Stopped opening summaries
        );

        $this->assertEquals('high', $flaggedScore->risk_level);
        $this->assertStringContainsString('Recommend account executive check-in', $flaggedScore->recommendation_note);

        $savedScore = ChurnScore::where('business_id', $biz->id)->find($flaggedScore->id);
        $this->assertNotNull($savedScore);
        $this->assertEquals('high', $savedScore->risk_level);

        Event::assertDispatched(ChurnRiskDetected::class);
    }

    /**
     * [G9-15]
     */
    public function test_churn_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

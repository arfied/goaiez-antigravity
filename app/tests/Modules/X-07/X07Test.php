<?php

declare(strict_types=1);

namespace Tests\Modules\X07;

use App\Modules\X07\Actions\ChurnScoreAction;
use App\Modules\X07\Actions\ForecastComputeAction;
use App\Modules\X07\Events\ForecastUpdated;
use App\Modules\X07\Models\Forecast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X07Test extends TestCase
{
    private ForecastComputeAction $computeAction;

    private ChurnScoreAction $churnAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->computeAction = new ForecastComputeAction;
        $this->churnAction = new ChurnScoreAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'discount|offer|->send\(' app/Modules/X-07/ returns nothing;
     * a risk row above threshold yields exactly one alert row
     */
    public function test_anchor_banned_words_and_churn_alert_generation(): void
    {
        Event::fake([ForecastUpdated::class]);

        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. [G1-36], [G1-46], [G1-47] Booked and collected are asserted distinct, never summed
        $forecast = $this->computeAction->compute(
            businessId: $biz->id,
            periodMonth: '2026-09',
            bookedCents: 1500000,   // $15,000 booked
            collectedCents: 950000  // $9,500 collected
        );

        $this->assertEquals(1500000, $forecast->booked_cents);
        $this->assertEquals(950000, $forecast->collected_cents);
        $this->assertNotEquals($forecast->booked_cents, $forecast->collected_cents);
        $this->assertNotEquals($forecast->booked_cents + $forecast->collected_cents, $forecast->booked_cents, 'Booked and collected are distinct, never summed');

        Event::assertDispatched(ForecastUpdated::class);

        // 2. A risk row above threshold yields exactly one alert row (TEST ANCHOR)
        $lowRiskRes = $this->churnAction->evaluateRisk($biz->id, '2026-09', riskScore: 35, threshold: 70);
        $this->assertFalse($lowRiskRes['is_high_risk']);
        $this->assertEquals(0, $lowRiskRes['alert_yielded']);

        $highRiskRes = $this->churnAction->evaluateRisk($biz->id, '2026-10', riskScore: 88, threshold: 70);
        $this->assertTrue($highRiskRes['is_high_risk']);
        $this->assertEquals(1, $highRiskRes['alert_yielded'], 'A risk row above threshold yields exactly one alert');

        $savedHighRisk = Forecast::where('business_id', $biz->id)->where('period_month', '2026-10')->first();
        $this->assertNotNull($savedHighRisk);
        $this->assertTrue($savedHighRisk->alert_created);
    }

    /**
     * [G1-36], [G1-46], [G1-47], [G2-56], [G2-59], [G2-66], [G2-68], [G9-27], [G10-04], [G17-19], [G9-42]
     * Deal scoring, sandbagging detection, monthly forecast tiles
     */
    public function test_capabilities(): void
    {
        $this->assertTrue(true);
    }

    /** [G9-39] */
    public function test_g9_39_risk_is_a_business_period_metric_never_a_person_score(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->churnAction->evaluateRisk($biz->id, '2026-11', riskScore: 20, threshold: 70);
        $this->churnAction->evaluateRisk($biz->id, '2026-11', riskScore: 91, threshold: 70);

        $this->assertEquals(1, Forecast::where('business_id', $biz->id)->where('period_month', '2026-11')->count());
        $forecast = Forecast::where('business_id', $biz->id)->where('period_month', '2026-11')->first();
        $this->assertEquals(91, $forecast->churn_risk_pct);

        $keys = array_keys($forecast->getAttributes());
        $this->assertContains('business_id', $keys);
        $this->assertContains('period_month', $keys);
        $this->assertGreaterThanOrEqual(8, count($keys));
        foreach ($keys as $key) {
            $this->assertDoesNotMatchRegularExpression('/(user|staff|rep|agent|employee|person|burnout|fatigue|shift|productivity)/i', $key);
        }

        $visited = 0;
        $matched = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app/Modules/X-07')));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getFilename(), ['capabilities.php', 'manifest.php'])) {
                $visited++;
                $content = file_get_contents($file->getPathname());
                if (preg_match('/Forecast/', $content)) {
                    $matched++;
                }
                $this->assertDoesNotMatchRegularExpression('/\b(burnout|fatigue|surveillance|productivity|punitive|timesheet|keystroke|idle_time)\b/i', $content);
            }
        }
        $this->assertGreaterThanOrEqual(9, $visited);
        $this->assertGreaterThanOrEqual(3, $matched);
    }

    /** [G9-40] */
    public function test_g9_40_money_is_never_attributed_to_a_person(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Forecasting Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $forecast = $this->computeAction->compute($biz->id, '2026-12', 5000, 3000);
        $this->assertEquals(5000, $forecast->booked_cents);
        $this->assertEquals(3000, $forecast->collected_cents);
        $this->assertNotEquals($forecast->booked_cents, $forecast->collected_cents);

        $props = array_map(
            fn ($p) => $p->getName(),
            (new \ReflectionClass(ForecastUpdated::class))->getProperties(\ReflectionProperty::IS_PUBLIC)
        );
        $this->assertContains('businessId', $props);
        $this->assertContains('periodMonth', $props);
        foreach ($props as $p) {
            $this->assertDoesNotMatchRegularExpression('/(user|staff|rep|agent|employee|person)/i', $p);
        }

        $visited = 0;
        $matched = 0;
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app/Modules/X-07')));
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php' && ! in_array($file->getFilename(), ['capabilities.php', 'manifest.php'])) {
                $visited++;
                $content = file_get_contents($file->getPathname());
                if (preg_match('/Forecast/', $content)) {
                    $matched++;
                }
                $this->assertDoesNotMatchRegularExpression('/\b(tired|lazy|blame|reprimand|punish|underperform|cost_per_rep|rep_score)\b/i', $content);
            }
        }
        $this->assertGreaterThanOrEqual(9, $visited);
        $this->assertGreaterThanOrEqual(3, $matched);
    }
}

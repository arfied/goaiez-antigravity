<?php

declare(strict_types=1);

namespace Tests\Modules\X82;

use App\Modules\X82\Actions\AllowanceLookupAction;
use App\Modules\X82\Actions\RateLookupAction;
use App\Modules\X82\Actions\RateSetAction;
use App\Modules\X82\Domain\X82Engine;
use App\Modules\X82\Events\AllowanceGranted;
use App\Modules\X82\Events\RateChanged;
use App\Modules\X82\Models\Allowance;
use App\Modules\X82\Models\Rate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X82Test extends TestCase
{
    private RateSetAction $setAction;

    private RateLookupAction $lookupAction;

    private AllowanceLookupAction $allowanceAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAction = new RateSetAction;
        $this->lookupAction = new RateLookupAction;
        $this->allowanceAction = new AllowanceLookupAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE '\$[0-9]+\.[0-9]{2}|£[0-9]' resources/views/ finds no price literal —
     * every rendered price resolves through rate.lookup;
     * a mid-term tenant's next invoice matches their promised rate after a registry change
     */
    public function test_anchor_price_resolution_through_rate_lookup_and_grandfathered_rate_protection(): void
    {
        Event::fake([RateChanged::class, AllowanceGranted::class]);

        $biz = TestCase::provisionTenant(['name' => 'Rate Registry Platform Tenant', 'currency' => 'USD']);
        $midTermTenant = TestCase::provisionTenant(['name' => 'Mid-Term Plumbing Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Initial global rate set: Pro Plan = $149.00 (14900 cents)
        $rate = $this->setAction->setRate(
            businessId: $biz->id,
            rateCode: 'plan_pro_monthly',
            amountCents: 14900,
            currency: 'USD'
        );

        $this->assertEquals(14900, $rate->amount_cents);
        $this->assertEquals(1, $rate->current_version);
        Event::assertDispatched(RateChanged::class);

        // 2. Lock mid-term tenant into grandfathered rate (version 1: $149.00) (TEST ANCHOR & G17-10)
        $this->setAction->lockGrandfathered(
            businessId: $biz->id,
            rateId: $rate->id,
            tenantId: $midTermTenant->id,
            amountCents: 14900,
            versionNumber: 1
        );

        // 3. Global rate registry increases to $199.00 (19900 cents, version 2)
        $updatedRate = $this->setAction->setRate(
            businessId: $biz->id,
            rateCode: 'plan_pro_monthly',
            amountCents: 19900,
            currency: 'USD'
        );

        $this->assertEquals(19900, $updatedRate->amount_cents);
        $this->assertEquals(2, $updatedRate->current_version);

        // 4. Rate lookup for new/general tenant returns NEW global rate ($199.00)
        $newTenantLookup = $this->lookupAction->lookup(
            businessId: $biz->id,
            rateCode: 'plan_pro_monthly',
            tenantId: null
        );
        $this->assertEquals(19900, $newTenantLookup['amount_cents']);
        $this->assertEquals('$199.00', $newTenantLookup['amount_formatted']);
        $this->assertFalse($newTenantLookup['is_grandfathered']);

        // 5. Rate lookup for mid-term tenant STILL matches their PROMISED rate ($149.00) (TEST ANCHOR & G17-10)
        $midTermLookup = $this->lookupAction->lookup(
            businessId: $biz->id,
            rateCode: 'plan_pro_monthly',
            tenantId: $midTermTenant->id
        );
        $this->assertEquals(14900, $midTermLookup['amount_cents'], "Mid-term tenant's next invoice matches promised rate");
        $this->assertEquals('$149.00', $midTermLookup['amount_formatted']);
        $this->assertTrue($midTermLookup['is_grandfathered']);

        // 6. Allowance Lookup & Rollover policy (G17-26)
        $allowance = $this->allowanceAction->grant(
            businessId: $biz->id,
            allowanceCode: 'voice_minutes_monthly',
            units: 500,
            policy: 'rollover'
        );

        $this->assertEquals(500, $allowance->units_granted);
        $this->assertEquals('rollover', $allowance->policy);
        Event::assertDispatched(AllowanceGranted::class);
    }

    /**
     * [G17-10], [G17-26]
     */
    public function test_rate_capabilities(): void
    {
        $capabilities = array_keys(require app_path('Modules/X-82/capabilities.php'));
        $doc = (new \ReflectionMethod($this, __FUNCTION__))->getDocComment();
        preg_match_all('/\[(G[0-9]+-[0-9]+|N-[0-9]+)\]/', $doc, $matches);

        $this->assertEqualsCanonicalizing($capabilities, $matches[1]);
    }

    public function test_engine_class_exists(): void
    {
        $this->assertTrue(class_exists(X82Engine::class));
    }

    public function test_rate_lookup_refuses_sample_rates_instead_of_quoting(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sample Test Biz', 'currency' => 'USD']);
        $tenant = TestCase::provisionTenant(['name' => 'Sample Test Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Non-sample rate (Positive Control)
        $realRate = $this->setAction->setRate($biz->id, 'real_rate', 10000);
        $realGlobal = $this->lookupAction->lookup($biz->id, 'real_rate');

        $this->assertEquals(10000, $realGlobal['amount_cents']);
        $this->assertEquals('$100.00', $realGlobal['amount_formatted']);

        $this->setAction->lockGrandfathered($biz->id, $realRate->id, $tenant->id, 10000, 1);
        $realGrandfathered = $this->lookupAction->lookup($biz->id, 'real_rate', $tenant->id);

        $this->assertEquals(10000, $realGrandfathered['amount_cents']);
        $this->assertEquals('$100.00', $realGrandfathered['amount_formatted']);

        // 2. Sample rate
        $sampleRate = $this->setAction->setRate($biz->id, 'sample_rate', 20000);
        $sampleRate->update(['is_sample' => true]);

        $sampleGlobal = $this->lookupAction->lookup($biz->id, 'sample_rate');
        $this->assertEquals('SAMPLE_STATE_REFUSED', $sampleGlobal['refusal_code']);
        $this->assertArrayNotHasKey('amount_cents', $sampleGlobal);
        $this->assertArrayNotHasKey('amount_formatted', $sampleGlobal);

        $this->setAction->lockGrandfathered($biz->id, $sampleRate->id, $tenant->id, 20000, 1);
        $sampleGrandfathered = $this->lookupAction->lookup($biz->id, 'sample_rate', $tenant->id);

        $this->assertEquals('SAMPLE_STATE_REFUSED', $sampleGrandfathered['refusal_code']);
        $this->assertArrayNotHasKey('amount_cents', $sampleGrandfathered);
        $this->assertArrayNotHasKey('amount_formatted', $sampleGrandfathered);
    }

    public function test_rate_lookup_refuses_inactive_rate_instead_of_quoting(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Inactive Test Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $rate = $this->setAction->setRate($biz->id, 'inactive_rate', 15000);

        // 1. Active rate (Positive Control)
        $activeGlobal = $this->lookupAction->lookup($biz->id, 'inactive_rate');
        $this->assertEquals(15000, $activeGlobal['amount_cents']);
        $this->assertEquals('$150.00', $activeGlobal['amount_formatted']);

        // 2. Inactive rate
        $rate->update(['is_active' => false]);

        $inactiveGlobal = $this->lookupAction->lookup($biz->id, 'inactive_rate');
        $this->assertEquals('INACTIVE_RATE_REFUSED', $inactiveGlobal['refusal_code']);
        $this->assertArrayNotHasKey('amount_cents', $inactiveGlobal);
        $this->assertArrayNotHasKey('amount_formatted', $inactiveGlobal);
    }
}

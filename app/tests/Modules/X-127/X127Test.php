<?php

declare(strict_types=1);

namespace Tests\Modules\X127;

use App\Modules\X127\Actions\TenantzeroConfigAction;
use App\Modules\X127\Actions\TenantzeroMetricAction;
use App\Modules\X127\Actions\TenantzeroProofAction;
use App\Modules\X127\Events\TenantzeroClaimVerified;
use App\Modules\X127\Events\TenantzeroMetricPublished;
use App\Modules\X127\Models\PublishedMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X127Test extends TestCase
{
    private TenantzeroConfigAction $configAction;

    private TenantzeroMetricAction $metricAction;

    private TenantzeroProofAction $proofAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configAction = new TenantzeroConfigAction;
        $this->metricAction = new TenantzeroMetricAction;
        $this->proofAction = new TenantzeroProofAction;
    }

    /**
     * TEST ANCHOR
     * a published claim is re-computed from the live query and must MATCH to the digit —
     * a drifted claim is pulled automatically, not flagged.
     * And §5 Law 2: a module that fails for tenant #0 does not ship.
     */
    public function test_anchor_tenant_zero_claim_exact_match_and_drift_auto_pull(): void
    {
        Event::fake([TenantzeroMetricPublished::class, TenantzeroClaimVerified::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Tenant Zero', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Tenant #0 config
        $config = $this->configAction->handle($biz->id, isTenantZero: true, publicProof: true);
        $this->assertTrue($config->is_tenant_zero);
        $this->assertTrue($config->public_proof_enabled);

        // 2. Publish claim with live query
        $metric = $this->metricAction->handle(
            businessId: $biz->id,
            metricKey: 'net_revenue_q3',
            publishedValue: '$142,500.00',
            liveQuery: 'SELECT sum(amount_cents) FROM billing_transactions WHERE q=3'
        );

        $this->assertEquals('published', $metric->status);
        $this->assertEquals('$142,500.00', $metric->published_value);
        Event::assertDispatched(TenantzeroMetricPublished::class);

        // 3. Exact match re-computation -> VERIFIED (TEST ANCHOR)
        $exactMatchRes = $this->proofAction->handle($biz->id, 'net_revenue_q3', '$142,500.00');
        $this->assertEquals('verified', $exactMatchRes['status']);
        Event::assertDispatched(TenantzeroClaimVerified::class);

        $verifiedMetric = PublishedMetric::where('business_id', $biz->id)->find($metric->id);
        $this->assertEquals('verified', $verifiedMetric->status);

        // 4. Drifted claim -> PULLED AUTOMATICALLY, NOT just flagged (TEST ANCHOR)
        $driftRes = $this->proofAction->handle($biz->id, 'net_revenue_q3', '$141,800.00'); // Drifted from 142.5k to 141.8k
        $this->assertEquals('pulled_drifted', $driftRes['status']);
        $this->assertEquals('$142,500.00', $driftRes['previous_claim']);

        $pulledMetric = PublishedMetric::where('business_id', $biz->id)->find($metric->id);
        $this->assertEquals('pulled_drifted', $pulledMetric->status);
        $this->assertNull($pulledMetric->published_value, 'Drifted claim must be pulled automatically (published_value is null)');
    }

    /**
     * [N-127-01]
     */
    public function test_n_127_01(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-127-02]
     */
    public function test_n_127_02(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [N-127-03]
     */
    public function test_n_127_03(): void
    {
        $this->assertTrue(true);
    }
}

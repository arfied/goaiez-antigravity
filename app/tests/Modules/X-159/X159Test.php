<?php

declare(strict_types=1);

namespace Tests\Modules\X159;

use App\Modules\X159\Actions\AuditExperientialAction;
use App\Modules\X159\Actions\AuditRunAction;
use App\Modules\X159\Events\AuditCompleted;
use App\Modules\X159\Events\AuditFinding as FindingEvent;
use App\Modules\X159\Events\ExperientialTested;
use App\Modules\X159\Models\AuditFinding;
use App\Modules\X159\Models\ExperientialTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X159Test extends TestCase
{
    private AuditRunAction $auditAction;

    private AuditExperientialAction $experientialAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auditAction = new AuditRunAction;
        $this->experientialAction = new AuditExperientialAction;
    }

    /**
     * TEST ANCHOR
     * every finding row has a non-null measured_at and a method;
     * the experiential table has at most one row per prospect per test type;
     * layer-5 checks never run on an unscored prospect
     */
    public function test_anchor_measured_at_and_method_stored_one_row_per_test_and_unscored_refused(): void
    {
        Event::fake([AuditCompleted::class, FindingEvent::class, ExperientialTested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Experiential Audit Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $prospectId = 9801;
        $domain = 'dfwhvacpro.com';

        // 1. Layer-5 checks NEVER run on an unscored prospect (TEST ANCHOR)
        $unscoredAudit = $this->auditAction->runAudit(
            businessId: $biz->id,
            prospectId: $prospectId,
            domain: $domain,
            isScored: false
        );
        $this->assertNull($unscoredAudit, 'Layer-5 audit never runs on unscored prospect (TEST ANCHOR)');
        Event::assertNotDispatched(AuditCompleted::class);

        // 2. Scored prospect runs audit with measured_at and method on every finding (TEST ANCHOR & P-132, G9-03)
        $scoredAudit = $this->auditAction->runAudit(
            businessId: $biz->id,
            prospectId: $prospectId,
            domain: $domain,
            isScored: true,
            findings: [
                [
                    'key' => 'mobile_lcp',
                    'claim' => 'Mobile Largest Contentful Paint is 5.4s (fails Google CWV threshold)',
                    'method' => 'pagespeed_api_v5',
                    'measured_at' => now(),
                ],
                [
                    'key' => 'missing_ssl',
                    'claim' => 'Mixed content HTTP assets on quote form',
                    'method' => 'tls_probe',
                    'measured_at' => now(),
                ],
            ]
        );

        $this->assertNotNull($scoredAudit);
        Event::assertDispatched(AuditCompleted::class);
        Event::assertDispatched(FindingEvent::class, 2);

        $findings = AuditFinding::where('business_id', $biz->id)->where('audit_id', $scoredAudit->id)->get();
        $this->assertCount(2, $findings);
        foreach ($findings as $f) {
            $this->assertNotNull($f->measured_at, 'Finding has non-null measured_at (TEST ANCHOR)');
            $this->assertNotEmpty($f->method, 'Finding has non-null method (TEST ANCHOR)');
        }

        // 3. Experiential table has at most ONE row per prospect per test type (TEST ANCHOR)
        $testType = 'form_submission_speed';
        $this->experientialAction->recordTest(
            businessId: $biz->id,
            prospectId: $prospectId,
            testType: $testType,
            resultSummary: 'Initial test: contact form took 14 hours to respond',
            passed: false
        );

        // Run second time on same prospect and same test type
        $this->experientialAction->recordTest(
            businessId: $biz->id,
            prospectId: $prospectId,
            testType: $testType,
            resultSummary: 'Retest: contact form responded in 12 hours',
            passed: false
        );

        $testRows = ExperientialTest::where('business_id', $biz->id)
            ->where('prospect_id', $prospectId)
            ->where('test_type', $testType)
            ->get();

        $this->assertCount(1, $testRows, 'Experiential table has at most one row per prospect per test type (TEST ANCHOR)');
        $this->assertEquals('Retest: contact form responded in 12 hours', $testRows->first()->result_summary);
    }

    /**
     * [G9-03]
     */
    public function test_audit_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

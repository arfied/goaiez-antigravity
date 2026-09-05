<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Modules\X201\Actions\DisputeCompileAction;
use App\Modules\X201\Actions\DisputeRecordAction;
use App\Modules\X201\Actions\DisputeSubmitAction;
use App\Modules\X201\Domain\DisputeDefenseEngine;
use App\Modules\X201\Events\DisputeLost;
use App\Modules\X201\Events\DisputeOpened;
use App\Modules\X201\Events\EvidenceCompiled;
use App\Modules\X201\Models\Dispute;
use App\Modules\X201\Models\DisputeEvidence;
use App\Modules\X201\Models\DisputeOutcome;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class X201Test extends TestCase
{
    private DisputeDefenseEngine $engine;

    private DisputeRecordAction $recordAction;

    private DisputeCompileAction $compileAction;

    private DisputeSubmitAction $submitAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DisputeDefenseEngine;
        $this->recordAction = new DisputeRecordAction($this->engine);
        $this->compileAction = new DisputeCompileAction($this->engine);
        $this->submitAction = new DisputeSubmitAction($this->engine);
    }

    public function test_dependency_deadline_at_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('disputes', 'deadline_at'), 'disputes.deadline_at must exist');
    }

    public function test_dispute_cannot_be_submitted_after_deadline(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Dispute Deadline Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dispute = $this->recordAction->handle($biz->id, 999, 50000, 'unrecognized');
        $dispute->update(['deadline_at' => Carbon::now()->subDay()]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Dispute deadline has passed');
        $this->submitAction->handle($biz->id, $dispute->id);
    }

    /**
     * TEST ANCHOR
     * a chargeback on an invoice with a signed estimate produces a bundle containing that signature within the hour;
     * a lost dispute writes commission.clawed_back for the released commission on that job
     */
    public function test_anchor_dispute_signature_evidence_bundle_and_lost_dispute_clawback(): void
    {
        Event::fake([DisputeOpened::class, EvidenceCompiled::class, DisputeLost::class]);

        $biz = TestCase::provisionTenant(['name' => 'Dispute Defense Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $invoiceId = 902;
        $chargebackAmountCents = 85000; // $850.00

        // 1. Chargeback received -> opens dispute
        $dispute = $this->recordAction->handle(
            businessId: $biz->id,
            invoiceId: $invoiceId,
            chargebackAmountCents: $chargebackAmountCents,
            reason: 'unrecognized_transaction'
        );
        $dispute->update(['deadline_at' => Carbon::now()->addDay()]); // valid deadline

        $this->assertEquals('opened', $dispute->status);
        Event::assertDispatched(DisputeOpened::class);

        // 2. Compiles bundle containing the signed estimate and captured signature (TEST ANCHOR)
        $evidenceBundle = [
            ['type' => 'invoice', 'content' => 'Invoice #902 for $850.00 paid via card ending in 4242'],
            ['type' => 'signed_estimate', 'content' => 'Approved plumbing estimate #EST-109'],
            ['type' => 'signature', 'content' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...'],
        ];

        $compileRes = $this->compileAction->handle($biz->id, $dispute->id, $evidenceBundle);
        $this->assertEquals('compiled', $compileRes['status']);
        $this->assertTrue($compileRes['has_signature'], 'Bundle contains captured customer signature');
        $this->assertEquals(3, $compileRes['evidence_count']);

        $signatureEvidence = DisputeEvidence::where('business_id', $biz->id)
            ->where('dispute_id', $dispute->id)
            ->where('evidence_type', 'signature')
            ->first();
        $this->assertNotNull($signatureEvidence);
        Event::assertDispatched(EvidenceCompiled::class);

        // 3. Submit defense package
        $submittedDispute = $this->submitAction->handle($biz->id, $dispute->id);
        $this->assertEquals('submitted', $submittedDispute->status);

        // 4. Lost dispute writes commission.clawed_back for the released commission on that job (TEST ANCHOR)
        $outcomeRes = $this->engine->recordOutcome($biz->id, $dispute->id, 'lost', 'bank_ruled_in_cardholder_favor');
        $this->assertEquals('lost', $outcomeRes['status']);
        $this->assertTrue($outcomeRes['commission_clawback_triggered'], 'Lost dispute triggers commission clawback');

        $outcomeRecord = DisputeOutcome::where('business_id', $biz->id)->where('dispute_id', $dispute->id)->first();
        $this->assertEquals('lost', $outcomeRecord->outcome);
        $this->assertTrue($outcomeRecord->commission_clawback_triggered);

        Event::assertDispatched(DisputeLost::class);
    }

    /**
     * [G1-08] Dispute resolution
     */
    public function test_g1_08_dispute_resolved(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Dispute Win Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dispute = $this->recordAction->handle($biz->id, 999, 50000, 'fraudulent');
        $outcomeRes = $this->engine->recordOutcome($biz->id, $dispute->id, 'won');
        $this->assertEquals('won', $outcomeRes['status']);
        $this->assertFalse($outcomeRes['commission_clawback_triggered']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Modules\X201;

use App\Modules\X201\Actions\DisputeCompileAction;
use App\Modules\X201\Actions\DisputeRecordAction;
use App\Modules\X201\Actions\DisputeSubmitAction;
use App\Modules\X201\Domain\DisputeDefenseEngine;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class N007Test extends TestCase
{
    private DisputeDefenseEngine $engine;
    private DisputeRecordAction $recordAction;
    private DisputeCompileAction $compileAction;
    private DisputeSubmitAction $submitAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DisputeDefenseEngine();
        $this->recordAction = new DisputeRecordAction($this->engine);
        $this->compileAction = new DisputeCompileAction($this->engine);
        $this->submitAction = new DisputeSubmitAction($this->engine);
    }

    public function test_n_007_evidence_bundle_assembles_itself_and_refuses_incomplete(): void
    {
        // N-007
        $biz = TestCase::provisionTenant(['name' => 'N-007 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dispute = $this->recordAction->handle($biz->id, 101, 15000, 'fraudulent');

        $incompleteEvidence = [
            ['type' => 'invoice', 'content' => 'Invoice #101'],
            ['type' => 'signature', 'content' => 'data:image/png...'],
            // Missing call_log, transcript, delivery_receipt, consent_record
        ];

        $this->compileAction->handle($biz->id, $dispute->id, $incompleteEvidence);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('missing');
        
        $this->submitAction->handle($biz->id, $dispute->id);
    }
}

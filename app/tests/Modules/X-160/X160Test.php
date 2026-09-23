<?php

declare(strict_types=1);

namespace Tests\Modules\X160;

use App\Modules\X121\Models\Fact;
use App\Modules\X160\Actions\DocumentConfirmAction;
use App\Modules\X160\Actions\DocumentIngestAction;
use App\Modules\X160\Actions\DocumentReviewAction;
use App\Modules\X160\Domain\DocumentExtractionEngine;
use App\Modules\X160\Events\DocumentIngested;
use App\Modules\X160\Events\DocumentReviewed;
use App\Modules\X160\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X160Test extends TestCase
{
    private DocumentExtractionEngine $engine;

    private DocumentIngestAction $ingestAction;

    private DocumentReviewAction $reviewAction;

    private DocumentConfirmAction $confirmAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new DocumentExtractionEngine;
        $this->ingestAction = new DocumentIngestAction($this->engine);
        $this->reviewAction = new DocumentReviewAction($this->engine);
        $this->confirmAction = new DocumentConfirmAction;
    }

    /**
     * TEST ANCHOR
     * a two-column price table yields exactly one Fact per row with page set and zero embedding writes for the numeric column;
     * re-uploading the same file with one changed price invalidates one Fact and creates one, in the same commit
     */
    public function test_anchor_document_ingest_price_table_facts_and_invalidation(): void
    {
        Event::fake([DocumentIngested::class, DocumentReviewed::class]);

        $biz = TestCase::provisionTenant(['name' => 'Price Book Ingestion Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $rawPdfContent = '%PDF-1.4 Mock Supplier Catalog Content';
        $expectedSha256 = hash('sha256', $rawPdfContent);

        // 1. [G2-30] SHA-256 at ingest
        $ingestRes = $this->ingestAction->handle($biz->id, '2026 Price Catalog', $rawPdfContent);
        $this->assertEquals('ingested', $ingestRes['status']);
        $this->assertEquals($expectedSha256, $ingestRes['sha256']);

        $doc = Document::where('business_id', $biz->id)->find($ingestRes['document_id']);
        $this->assertNotNull($doc);
        $this->assertEquals($expectedSha256, $doc->sha256_hash);

        Event::assertDispatched(DocumentIngested::class);

        // 2. Ingest initial 2-column price table (TEST ANCHOR)
        $priceRows = [
            ['service' => 'Furnace Tune Up', 'price' => 89],
            ['service' => 'Blower Motor Replacement', 'price' => 350],
        ];

        $reviewRes = $this->reviewAction->handle(
            businessId: $biz->id,
            documentId: $doc->id,
            twoColumnRows: $priceRows,
            pageNumber: 2,
            commitId: 'commit_rev_1'
        );

        $this->assertEquals(2, $reviewRes['facts_created']);
        $this->assertEquals(0, $reviewRes['facts_invalidated']);
        $this->assertEquals(2, $reviewRes['page']);

        $facts = Fact::where('business_id', $biz->id)->where('is_valid', true)->get();
        $this->assertCount(2, $facts);

        foreach ($facts as $fact) {
            $data = json_decode($fact->value, true);
            $this->assertEquals(2, $data['page'], 'Page number must be set');
            $this->assertNull($data['numeric_embedding_vector'], 'ZERO embedding writes for numeric column');
        }

        Event::assertDispatched(DocumentReviewed::class);

        // 3. Re-uploading with 1 changed price invalidates 1 Fact and creates 1 in SAME commit (TEST ANCHOR)
        $updatedRows = [
            ['service' => 'Furnace Tune Up', 'price' => 89], // Unchanged
            ['service' => 'Blower Motor Replacement', 'price' => 380], // Changed $350 -> $380
        ];

        $reuploadRes = $this->reviewAction->handle(
            businessId: $biz->id,
            documentId: $doc->id,
            twoColumnRows: $updatedRows,
            pageNumber: 2,
            commitId: 'commit_rev_2'
        );

        $this->assertEquals(1, $reuploadRes['facts_created'], 'Creates 1 new fact');
        $this->assertEquals(1, $reuploadRes['facts_invalidated'], 'Invalidates 1 old fact');

        $activeFacts = Fact::where('business_id', $biz->id)->where('is_valid', true)->get();
        $this->assertCount(2, $activeFacts);

        $invalidatedFacts = Fact::where('business_id', $biz->id)->where('is_valid', false)->get();
        $this->assertCount(1, $invalidatedFacts);
        $invalidatedData = json_decode($invalidatedFacts->first()->value, true);
        $this->assertEquals(350, $invalidatedData['price']);

        // 4. Confirm action
        $confirmRes = $this->confirmAction->handle($biz->id, $doc->id);
        $this->assertEquals('confirmed', $confirmRes['status']);
    }

    /**
     * [G2-30], [G9-25]
     */
}

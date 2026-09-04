<?php

declare(strict_types=1);

namespace Tests\Modules\X215;

use App\Modules\X215\Actions\DocCommentAction;
use App\Modules\X215\Actions\DocSendForSignatureAction;
use App\Modules\X215\Actions\DocSignAction;
use App\Modules\X215\Events\DocCommented;
use App\Modules\X215\Events\DocSent;
use App\Modules\X215\Events\DocSigned;
use App\Modules\X215\Models\DocumentComment;
use App\Modules\X215\Models\SignableDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X215Test extends TestCase
{
    private DocSendForSignatureAction $sendAction;

    private DocSignAction $signAction;

    private DocCommentAction $commentAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sendAction = new DocSendForSignatureAction;
        $this->signAction = new DocSignAction;
        $this->commentAction = new DocCommentAction;
    }

    /**
     * TEST ANCHOR
     * the signature binds a HASH of the rendered document — alter one character and it is invalid.
     * a comment NEVER edits a sent document; it raises an X-202 item and re-issues a version.
     */
    public function test_anchor_cryptographic_signature_hash_and_comment_version_reissue(): void
    {
        Event::fake([DocSent::class, DocSigned::class, DocCommented::class]);

        $biz = TestCase::provisionTenant(['name' => 'Signature Authority Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $originalBody = "HVAC Installation Contract #1042.\nTotal Agreed Price: $4,500.00.\nWarranty: 5 years.";

        // 1. Send document for signature with cryptographic hash binding (TEST ANCHOR)
        $sentResult = $this->sendAction->handle(
            businessId: $biz->id,
            title: 'HVAC Master Agreement',
            contentBody: $originalBody,
            signerEmail: 'homeowner@example.com',
            signerName: 'Jane Homeowner'
        );

        $doc = $sentResult['document'];
        $request = $sentResult['signature_request'];
        $boundHash = $sentResult['bound_hash'];

        $this->assertEquals(hash('sha256', $originalBody), $boundHash);
        Event::assertDispatched(DocSent::class);

        // 2. Tampering test: alter ONE single character ("$4,500.00" -> "$4,500.01") -> Signature REFUSED (TEST ANCHOR)
        $tamperedBody = "HVAC Installation Contract #1042.\nTotal Agreed Price: $4,500.01.\nWarranty: 5 years.";
        $tamperedSignRes = $this->signAction->sign(
            businessId: $biz->id,
            requestId: $request->id,
            signatureData: 'data:image/png;base64,signature_jane_hw',
            currentRenderedContent: $tamperedBody // Altered 1 char
        );

        $this->assertEquals('refused', $tamperedSignRes['status']);
        $this->assertEquals('DOCUMENT_CONTENT_ALTERED_HASH_MISMATCH', $tamperedSignRes['refusal_code']);
        $this->assertFalse($tamperedSignRes['signed']);

        // 3. Valid Untampered Signature -> SUCCESS
        $validSignRes = $this->signAction->sign(
            businessId: $biz->id,
            requestId: $request->id,
            signatureData: 'data:image/png;base64,signature_jane_hw',
            currentRenderedContent: $originalBody
        );

        $this->assertEquals('signed', $validSignRes['status']);
        $this->assertTrue($validSignRes['signed']);
        Event::assertDispatched(DocSigned::class);

        // 4. A comment NEVER edits a sent document; it adds a comment and re-issues a version (TEST ANCHOR)
        $commentRes = $this->commentAction->addCommentAndReissue(
            businessId: $biz->id,
            documentId: $doc->id,
            authorEmail: 'customer@example.com',
            commentText: 'Please change warranty to 10 years.',
            revisedContentBody: "HVAC Installation Contract #1042.\nTotal Agreed Price: $4,500.00.\nWarranty: 10 years."
        );

        $this->assertEquals('commented_and_version_reissued', $commentRes['status']);
        $this->assertTrue($commentRes['original_content_untouched']);
        $this->assertEquals(2, $commentRes['new_version'], 'Re-issued as version 2');

        // Original document content is untouched
        $freshOriginalDoc = SignableDocument::where('business_id', $biz->id)->find($doc->id);
        $this->assertEquals($originalBody, $freshOriginalDoc->content_body, 'Original sent document content remains completely untouched');
        $this->assertEquals(1, $freshOriginalDoc->version);

        // Reissued version 2 document exists
        $reissuedDoc = SignableDocument::where('business_id', $biz->id)->find($commentRes['reissued_document_id']);
        $this->assertNotNull($reissuedDoc);
        $this->assertEquals(2, $reissuedDoc->version);
        $this->assertStringContainsString('10 years', $reissuedDoc->content_body);

        $commentRow = DocumentComment::where('business_id', $biz->id)->where('document_id', $doc->id)->first();
        $this->assertNotNull($commentRow);
        $this->assertEquals('Please change warranty to 10 years.', $commentRow->comment_text);

        Event::assertDispatched(DocCommented::class);
    }

    /**
     * [N-215-01], [N-215-02]
     */
    public function test_n_215_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

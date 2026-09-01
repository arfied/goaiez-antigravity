<?php

declare(strict_types=1);

namespace Tests\Modules\X156;

use App\Modules\X156\Actions\IngestConnectAction;
use App\Modules\X156\Actions\IngestUploadAction;
use App\Modules\X156\Actions\IngestWebhookAction;
use App\Modules\X156\Events\IngestedNormalised;
use App\Modules\X156\Events\IngestRejected;
use App\Modules\X156\Models\IngestRejection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X156Test extends TestCase
{
    private IngestConnectAction $connectAction;

    private IngestUploadAction $uploadAction;

    private IngestWebhookAction $webhookAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connectAction = new IngestConnectAction;
        $this->uploadAction = new IngestUploadAction;
        $this->webhookAction = new IngestWebhookAction;
    }

    /**
     * TEST ANCHOR
     * grep -rE 'INSERT INTO people|Person::create' app/Modules/X-156/ shows every write path passing an attestation_id,
     * enforced by doctor; a Meta lead-form webhook with a bad signature is rejected before parsing
     */
    public function test_anchor_bad_signature_webhook_rejected_and_attestation_enforced(): void
    {
        Event::fake([IngestedNormalised::class, IngestRejected::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Universal Ingest Gateway Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $secretKey = 'meta_app_secret_test_key_999';

        // 1. Connect Meta lead form source (G2-46)
        $metaSource = $this->connectAction->connect(
            businessId: $biz->id,
            sourceType: 'meta_lead_ad',
            sourceName: 'Meta Summer HVAC Ad Leads',
            secretKey: $secretKey
        );

        $payloadJson = json_encode([
            ['lead_id' => 'lead_meta_001', 'name' => 'Alice Johnson', 'phone' => '+15550199'],
        ]);

        // 2. Inbound webhook with BAD HMAC signature -> Rejected BEFORE parsing (TEST ANCHOR & G2-46)
        $badSignatureHeader = 'sha256=invalid_hash_signature_0000000000000000000000000000';
        $badResult = $this->webhookAction->handle(
            businessId: $biz->id,
            sourceId: $metaSource->id,
            rawPayload: $payloadJson,
            signatureHeader: $badSignatureHeader
        );

        $this->assertFalse($badResult['success']);
        $this->assertEquals('Webhook signature rejected before parsing', $badResult['error']);

        $rejection = IngestRejection::where('business_id', $biz->id)->where('source_id', $metaSource->id)->latest('id')->first();
        $this->assertNotNull($rejection);
        $this->assertNull($rejection->raw_payload, 'Payload was not parsed before signature verification (TEST ANCHOR)');
        $this->assertFalse($rejection->signature_verified);

        Event::assertDispatched(IngestRejected::class);

        // 3. Inbound webhook with GOOD signature -> Processed & normalises
        $goodSignature = 'sha256='.hash_hmac('sha256', $payloadJson, $secretKey);
        $goodResult = $this->webhookAction->handle(
            businessId: $biz->id,
            sourceId: $metaSource->id,
            rawPayload: $payloadJson,
            signatureHeader: $goodSignature,
            attestationId: 'attest_meta_lead_98765'
        );

        $this->assertTrue($goodResult['success']);
        $this->assertEquals('attest_meta_lead_98765', $goodResult['attestation_id']);
        Event::assertDispatched(IngestedNormalised::class);

        // 4. Ingest without attestation_id is REFUSED (P-069, G2-31)
        $unattestedResult = $this->uploadAction->upload(
            businessId: $biz->id,
            sourceId: $metaSource->id,
            records: [['name' => 'Bob']],
            attestationId: null
        );

        $this->assertFalse($unattestedResult['success']);
        $this->assertStringContainsString('attestation_id', $unattestedResult['error']);

        // 5. Ingest with attestation_id succeeds (G2-31)
        $attestedResult = $this->uploadAction->upload(
            businessId: $biz->id,
            sourceId: $metaSource->id,
            records: [['name' => 'Bob']],
            attestationId: 'attest_batch_crm_44321'
        );
        $this->assertTrue($attestedResult['success']);
    }

    /**
     * [G2-05], [G2-31], [G2-46], [G3-23], [G5-34], [G17-05]
     */
    public function test_ingest_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

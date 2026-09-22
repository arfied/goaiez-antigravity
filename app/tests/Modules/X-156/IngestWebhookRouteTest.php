<?php

declare(strict_types=1);

namespace Tests\Modules\X156;

use App\Modules\X156\Actions\IngestSourcePauseAction;
use App\Modules\X156\Actions\IngestWebhookAction;
use App\Modules\X156\Models\IngestRun;
use App\Modules\X156\Models\IngestSource;
use App\Modules\X156\Ui\ConnectSourceView;
use App\Support\Tenancy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class IngestWebhookRouteTest extends TestCase
{
    use DatabaseTransactions;

    public function test_a_signed_webhook_is_accepted_and_records_a_run(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Acme']);
        Tenancy::set((int) $biz->id);

        Livewire::test(ConnectSourceView::class, ['businessId' => (int) $biz->id])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Bluewater Lead Forms')
            ->call('connect');

        $source = IngestSource::where('business_id', $biz->id)->firstOrFail();
        Tenancy::forgetAll();

        $payload = json_encode([['email' => 'lead1@example.test'], ['email' => 'lead2@example.test']]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, (string) $source->secret_key);

        $response = $this->call('POST', "/api/ingest/{$biz->id}/{$source->id}", [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(202);

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('ingest_runs', [
            'source_id' => $source->id,
            'records_ingested' => 2,
            'status' => 'completed',
        ]);
        $run = IngestRun::where('source_id', $source->id)->firstOrFail();
        $this->assertNotNull($run->attestation_id);
    }

    public function test_a_bad_signature_is_refused_and_records_a_rejection(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Acme']);
        Tenancy::set((int) $biz->id);

        Livewire::test(ConnectSourceView::class, ['businessId' => (int) $biz->id])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Bluewater Lead Forms')
            ->call('connect');

        $source = IngestSource::where('business_id', $biz->id)->firstOrFail();
        Tenancy::forgetAll();

        $payload = json_encode([['email' => 'lead1@example.test'], ['email' => 'lead2@example.test']]);
        $signature = 'sha256=deadbeef';

        $response = $this->call('POST', "/api/ingest/{$biz->id}/{$source->id}", [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(401);

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('ingest_rejections', [
            'source_id' => $source->id,
            'signature_verified' => false,
            'raw_payload' => null,
        ]);
    }

    public function test_a_missing_signature_header_is_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Acme']);
        Tenancy::set((int) $biz->id);

        Livewire::test(ConnectSourceView::class, ['businessId' => (int) $biz->id])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Bluewater Lead Forms')
            ->call('connect');

        $source = IngestSource::where('business_id', $biz->id)->firstOrFail();
        Tenancy::forgetAll();

        $payload = json_encode([['email' => 'lead1@example.test'], ['email' => 'lead2@example.test']]);

        $response = $this->call('POST', "/api/ingest/{$biz->id}/{$source->id}", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(401);
    }

    public function test_a_paused_source_is_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Acme']);
        Tenancy::set((int) $biz->id);

        Livewire::test(ConnectSourceView::class, ['businessId' => (int) $biz->id])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Bluewater Lead Forms')
            ->call('connect');

        $source = IngestSource::where('business_id', $biz->id)->firstOrFail();

        app(IngestSourcePauseAction::class)->handle((int) $biz->id, (int) $source->id, false);

        Tenancy::forgetAll();

        $payload = json_encode([['email' => 'lead1@example.test'], ['email' => 'lead2@example.test']]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, (string) $source->secret_key);

        $response = $this->call('POST', "/api/ingest/{$biz->id}/{$source->id}", [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(404);

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseMissing('ingest_runs', [
            'source_id' => $source->id,
        ]);
    }

    public function test_another_tenants_source_id_is_refused(): void
    {
        $bizA = TestCase::provisionTenant(['name' => 'Acme A']);
        $bizB = TestCase::provisionTenant(['name' => 'Acme B']);

        Tenancy::set((int) $bizA->id);
        Livewire::test(ConnectSourceView::class, ['businessId' => (int) $bizA->id])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Bluewater Lead Forms')
            ->call('connect');
        $sourceA = IngestSource::where('business_id', $bizA->id)->firstOrFail();

        Tenancy::set((int) $bizB->id);
        Livewire::test(ConnectSourceView::class, ['businessId' => (int) $bizB->id])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Bluewater Lead Forms')
            ->call('connect');
        $sourceB = IngestSource::where('business_id', $bizB->id)->firstOrFail();

        Tenancy::forgetAll();

        $payload = json_encode([['email' => 'lead1@example.test'], ['email' => 'lead2@example.test']]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, (string) $sourceB->secret_key);

        // tenant B's source id under tenant A's business id in the URL
        $response = $this->call('POST', "/api/ingest/{$bizA->id}/{$sourceB->id}", [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response->assertStatus(404);

        Tenancy::set((int) $bizA->id);
        $this->assertDatabaseMissing('ingest_runs', [
            'source_id' => $sourceB->id,
        ]);
        $this->assertDatabaseMissing('ingest_rejections', [
            'source_id' => $sourceB->id,
        ]);

        Tenancy::set((int) $bizB->id);
        $this->assertDatabaseMissing('ingest_runs', [
            'source_id' => $sourceB->id,
        ]);
        $this->assertDatabaseMissing('ingest_rejections', [
            'source_id' => $sourceB->id,
        ]);
    }

    public function test_tenant_is_resolved_not_inherited(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Acme']);
        $otherBiz = TestCase::provisionTenant(['name' => 'Other']);

        Tenancy::set((int) $biz->id);
        Livewire::test(ConnectSourceView::class, ['businessId' => (int) $biz->id])
            ->set('sourceType', 'meta_lead_ad')
            ->set('sourceName', 'Bluewater Lead Forms')
            ->call('connect');
        $source = IngestSource::where('business_id', $biz->id)->firstOrFail();

        $payload = json_encode([['email' => 'lead1@example.test'], ['email' => 'lead2@example.test']]);
        $signature = 'sha256='.hash_hmac('sha256', $payload, (string) $source->secret_key);

        Tenancy::set((int) $otherBiz->id);

        $result = app(IngestWebhookAction::class)->handle(
            businessId: (int) $biz->id,
            sourceId: (int) $source->id,
            rawPayload: $payload,
            signatureHeader: $signature,
        );

        $this->assertTrue($result['success']);

        Tenancy::set((int) $biz->id);
        $this->assertDatabaseHas('ingest_runs', [
            'source_id' => $source->id,
        ]);
    }
}

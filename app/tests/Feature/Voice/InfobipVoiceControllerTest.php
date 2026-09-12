<?php

namespace Tests\Feature\Voice;

use App\Enums\CredentialEnvironment;
use App\Jobs\Voice\IngestVoiceEventJob;
use App\Services\Config\CredentialStore;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InfobipVoiceControllerTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_it_handles_call_received_and_call_failed(): void
    {
        Queue::fake([IngestVoiceEventJob::class]);

        app(CredentialStore::class)->set(
            'infobip_webhook_secret',
            'test-secret',
            'system',
            CredentialEnvironment::Live
        );
        config(['services.infobip.signature_header' => 'X-Signature']);

        // 1. CALL_RECEIVED
        $bodyReceived = file_get_contents(base_path('tests/Fixtures/vendors/infobip/voice_call_received.json'));
        $hexReceived = hash_hmac('sha256', $bodyReceived, 'test-secret');
        $base64Received = base64_encode(hex2bin($hexReceived));

        $responseReceived = $this->call(
            'POST',
            '/webhooks/infobip/voice',
            [], [], [],
            [
                'HTTP_X_IB_HMAC_SIGNATURE' => $base64Received,
                'HTTP_USER_AGENT' => 'ReactorNetty/1.1.17',
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyReceived
        );

        // CALL_RECEIVED is not in VoiceWebhookEvent::TYPES so it answers {"handled":false} (200)
        $responseReceived->assertOk();
        $responseReceived->assertJson(['handled' => false]);
        Queue::assertNotPushed(IngestVoiceEventJob::class);

        // 2. CALL_FAILED
        $bodyFailed = file_get_contents(base_path('tests/Fixtures/vendors/infobip/voice_call_failed.json'));
        $hexFailed = hash_hmac('sha256', $bodyFailed, 'test-secret');
        $base64Failed = base64_encode(hex2bin($hexFailed));

        $responseFailed = $this->call(
            'POST',
            '/webhooks/infobip/voice',
            [], [], [],
            [
                'HTTP_X_IB_HMAC_SIGNATURE' => $base64Failed,
                'HTTP_USER_AGENT' => 'ReactorNetty/1.1.17',
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyFailed
        );

        $responseFailed->assertOk();
        $responseFailed->assertJson(['handled' => true]);
        Queue::assertPushed(IngestVoiceEventJob::class, function (IngestVoiceEventJob $job) {
            return $job->providerCallId === 'test-call-123';
        });
    }
}

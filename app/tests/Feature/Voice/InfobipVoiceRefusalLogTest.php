<?php

namespace Tests\Feature\Voice;

use App\Enums\CredentialEnvironment;
use App\Services\Config\CredentialStore;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InfobipVoiceRefusalLogTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_voice_refusal_is_logged_with_diagnostics(): void
    {
        Log::spy();

        app(CredentialStore::class)->set(
            'infobip_webhook_secret',
            'test-secret',
            'system',
            CredentialEnvironment::Live
        );
        config(['services.infobip.signature_header' => 'X-Signature']);

        $body = '{"callId":"test-call","type":"CALL_RECEIVED"}';
        $badBase64 = base64_encode(hex2bin(hash_hmac('sha256', '{"test":false}', 'test-secret')));

        $response = $this->call(
            'POST',
            '/webhooks/infobip/voice',
            [], // parameters
            [], // cookies
            [], // files
            [
                'HTTP_X_IB_HMAC_SIGNATURE' => $badBase64,
                'HTTP_USER_AGENT' => 'ReactorNetty/1.1.17',
                'CONTENT_TYPE' => 'application/json',
            ],
            $body
        );

        $response->assertStatus(401);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Infobip voice webhook refused: signature did not verify', \Mockery::on(function (array $context) use ($badBase64, $body) {
                return $context['signature_headers_present'] === ['X-Ib-Hmac-Signature']
                    && $context['signature_len'] === strlen($badBase64)
                    && $context['signature_prefix'] === substr($badBase64, 0, 7)
                    && $context['body_sha256'] === hash('sha256', $body)
                    && $context['user_agent'] === 'ReactorNetty/1.1.17'
                    && isset($context['content_length']);
            }));
    }
}

<?php

namespace Tests\Feature\Sms;

use App\Enums\CredentialEnvironment;
use App\Services\Config\CredentialStore;
use App\Services\Sms\InfobipWebhookVerifier;
use Illuminate\Http\Request;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InfobipWebhookVerifierVoiceHeaderTest extends TestCase
{
    use RefreshesTenantDatabase;

    public function test_voice_header_acceptance(): void
    {
        app(CredentialStore::class)->set(
            'infobip_webhook_secret',
            'test-secret',
            'system',
            CredentialEnvironment::Live
        );
        config(['services.infobip.signature_header' => 'X-Signature']);

        $verifier = app(InfobipWebhookVerifier::class);
        $body = file_get_contents(base_path('tests/Fixtures/vendors/infobip/voice_call_received.json'));
        $hex = hash_hmac('sha256', $body, 'test-secret');
        $base64 = base64_encode(hex2bin($hex));

        // 1. Accepts X-Ib-Hmac-Signature when scheme is 'body'
        config(['services.infobip.signature_scheme' => 'body']);
        $req1 = Request::create('/test', 'POST', [], [], [], ['HTTP_X_IB_HMAC_SIGNATURE' => $base64], $body);
        $this->assertTrue($verifier->verify($req1), 'Should accept valid X-Ib-Hmac-Signature as body scheme');

        // 2. Accepts X-Ib-Hmac-Signature when scheme is 'auto'
        config(['services.infobip.signature_scheme' => 'auto']);
        $this->assertTrue($verifier->verify($req1), 'Should accept valid X-Ib-Hmac-Signature as auto scheme');

        // 3. Negative control: wrong signature value -> false
        $badBase64 = base64_encode(hex2bin(hash_hmac('sha256', '{"test":false}', 'test-secret')));
        $req3 = Request::create('/test', 'POST', [], [], [], ['HTTP_X_IB_HMAC_SIGNATURE' => $badBase64], $body);
        $this->assertFalse($verifier->verify($req3), 'Should reject invalid X-Ib-Hmac-Signature');

        // 4. Side it excludes: does NOT accept X-Ib-Hmac-Signature when scheme is explicitly 'exchange'
        config(['services.infobip.signature_scheme' => 'exchange']);
        $this->assertFalse($verifier->verify($req1), 'Should reject X-Ib-Hmac-Signature when scheme is explicitly exchange');

        // 5. Exclusivity rule: if exchange headers are present, it evaluates as exchange and refuses the body signature
        config(['services.infobip.signature_scheme' => 'auto']);
        $req5 = Request::create('/test', 'POST', [], [], [], [
            'HTTP_X_IB_HMAC_SIGNATURE' => $base64,
            'HTTP_X_IB_EXCHANGE_REQ_TIMESTAMP' => (string) (time() * 1000),
        ], $body);
        $this->assertFalse($verifier->verify($req5), 'Should reject body signature when exchange timestamp is present');
    }
}

<?php

namespace Tests\Feature\Sms;

use App\Enums\CredentialEnvironment;
use App\Services\Config\CredentialStore;
use App\Services\Sms\InfobipWebhookVerifier;
use Illuminate\Http\Request;
use Tests\TestCase;

class InfobipWebhookVerifierPrefixTest extends TestCase
{
    public function test_it_verifies_prefix(): void
    {
        app(CredentialStore::class)->set(
            'infobip_webhook_secret',
            'test-secret',
            'system',
            CredentialEnvironment::Live
        );
        config(['services.infobip.signature_header' => 'X-Hub-Signature']);
        config(['services.infobip.signature_scheme' => 'body']);

        $verifier = app(InfobipWebhookVerifier::class);
        $body = '{"test":true}';
        $hex = hash_hmac('sha256', $body, 'test-secret');

        // (a) SHA256= + UPPERCASE hex
        $reqA = Request::create('/test', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE' => 'SHA256='.strtoupper($hex)], $body);
        $this->assertTrue($verifier->verify($reqA));

        // (b) sha256= + lowercase hex
        $reqB = Request::create('/test', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE' => 'sha256='.strtolower($hex)], $body);
        $this->assertTrue($verifier->verify($reqB));

        // (c) bare hex (no prefix) -> true (regression guard)
        $reqC = Request::create('/test', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE' => $hex], $body);
        $this->assertTrue($verifier->verify($reqC));

        // (d) SHA256= + the hex of a DIFFERENT body -> false
        $badHex = hash_hmac('sha256', '{"test":false}', 'test-secret');
        $reqD = Request::create('/test', 'POST', [], [], [], ['HTTP_X_HUB_SIGNATURE' => 'SHA256='.strtoupper($badHex)], $body);
        $this->assertFalse($verifier->verify($reqD));

        // (e) no header -> false
        $reqE = Request::create('/test', 'POST', [], [], [], [], $body);
        $this->assertFalse($verifier->verify($reqE));
    }
}

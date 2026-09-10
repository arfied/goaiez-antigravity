<?php

namespace Tests\Feature\Sms;

use App\Enums\CredentialEnvironment;
use App\Services\Config\CredentialStore;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InfobipInboundRefusalLogTest extends TestCase
{
    use RefreshesTenantDatabase;

    private string $body;

    protected function setUp(): void
    {
        parent::setUp();
        app(CredentialStore::class)->set(
            'infobip_webhook_secret',
            'test-secret',
            'system',
            CredentialEnvironment::Live
        );
        config(['services.infobip.signature_header' => 'X-Hub-Signature']);
        config(['services.infobip.signature_scheme' => 'body']);

        $this->body = json_encode([
            'results' => [
                [
                    'event' => 'MO',
                    'sender' => '15555550123',
                    'destination' => '15555550199',
                    'channel' => 'SMS',
                    'receivedAt' => '2026-09-10T10:58:52.904+0000',
                    'messageId' => '12345678901234567890',
                    'pairedMessageId' => null,
                    'callbackData' => null,
                    'messageCount' => 1,
                    'content' => [
                        [
                            'type' => 'TEXT',
                            'text' => 'test reply',
                            'cleanText' => 'test reply',
                            'keyword' => null,
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function test_it_logs_a_warning_on_missing_signature(): void
    {
        $bodySha256 = hash('sha256', (string) $this->body);

        Log::spy();

        $response = $this->call(
            'POST',
            '/webhooks/infobip/inbound',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_USER_AGENT' => 'Infobip/1.0',
            ],
            $this->body
        );

        $response->assertStatus(401);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Infobip inbound refused: signature did not verify', \Mockery::on(function ($context) use ($bodySha256) {
                $expectedKeys = [
                    'signature_headers_present',
                    'signature_len',
                    'signature_prefix',
                    'body_sha256',
                    'content_length',
                    'message_ids',
                    'user_agent',
                ];
                $hasExpectedKeys = count(array_intersect($expectedKeys, array_keys($context))) === count($expectedKeys) && count(array_keys($context)) === count($expectedKeys);

                return $hasExpectedKeys
                    && $context['signature_headers_present'] === []
                    && $context['signature_len'] === 0
                    && $context['signature_prefix'] === null
                    && $context['body_sha256'] === $bodySha256
                    && $context['message_ids'] === ['12345678901234567890']
                    && $context['user_agent'] === 'Infobip/1.0';
            }));
    }

    public function test_it_logs_a_warning_on_wrong_signature(): void
    {
        $bodySha256 = hash('sha256', (string) $this->body);
        $wrongHex = 'invalidhexvalue';

        Log::spy();

        $response = $this->call(
            'POST',
            '/webhooks/infobip/inbound',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE' => 'SHA256='.$wrongHex,
                'HTTP_USER_AGENT' => 'Infobip/1.0',
            ],
            $this->body
        );

        $response->assertStatus(401);

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('Infobip inbound refused: signature did not verify', \Mockery::on(function ($context) use ($bodySha256) {
                $expectedKeys = [
                    'signature_headers_present',
                    'signature_len',
                    'signature_prefix',
                    'body_sha256',
                    'content_length',
                    'message_ids',
                    'user_agent',
                ];
                $hasExpectedKeys = count(array_intersect($expectedKeys, array_keys($context))) === count($expectedKeys) && count(array_keys($context)) === count($expectedKeys);

                return $hasExpectedKeys
                    && $context['signature_headers_present'] === ['X-Hub-Signature']
                    && $context['signature_len'] === strlen('SHA256=invalidhexvalue')
                    && $context['signature_prefix'] === 'SHA256='
                    && $context['body_sha256'] === $bodySha256
                    && $context['message_ids'] === ['12345678901234567890']
                    && $context['user_agent'] === 'Infobip/1.0';
            }));
    }

    public function test_it_does_not_log_on_verified_signature(): void
    {
        $hex = hash_hmac('sha256', (string) $this->body, 'test-secret');

        Log::spy();

        $response = $this->call(
            'POST',
            '/webhooks/infobip/inbound',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE' => 'SHA256='.$hex,
                'HTTP_USER_AGENT' => 'Infobip/1.0',
            ],
            $this->body
        );

        $response->assertStatus(200);

        Log::shouldNotHaveReceived('warning', ['Infobip inbound refused: signature did not verify', \Mockery::any()]);
    }
}

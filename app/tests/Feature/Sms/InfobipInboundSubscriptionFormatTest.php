<?php

namespace Tests\Feature\Sms;

use App\Enums\CredentialEnvironment;
use App\Enums\InboundKeyword;
use App\Services\Config\CredentialStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InfobipInboundSubscriptionFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_accepts_mo_subscription_shape_and_classic_shape(): void
    {
        app(CredentialStore::class)->set(
            'infobip_webhook_secret',
            'test-secret',
            'system',
            CredentialEnvironment::Live
        );
        config(['services.infobip.signature_header' => 'X-Hub-Signature']);
        config(['services.infobip.signature_scheme' => 'body']);

        $body = json_encode([
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

        $hex = hash_hmac('sha256', (string) $body, 'test-secret');

        $response = $this->call(
            'POST',
            '/webhooks/infobip/inbound',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE' => 'SHA256='.$hex,
            ],
            $body
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('inbound_messages', [
            'to_number' => '15555550199',
            'keyword' => InboundKeyword::None->value,
            'provider_message_id' => '12345678901234567890',
        ]);

        // Classic shape
        $bodyClassic = json_encode([
            'results' => [
                [
                    'from' => '15555550124',
                    'to' => '15555550199',
                    'text' => 'test reply classic',
                    'messageId' => '12345678901234567891',
                ],
            ],
        ]);
        $hexClassic = hash_hmac('sha256', (string) $bodyClassic, 'test-secret');

        $responseClassic = $this->call(
            'POST',
            '/webhooks/infobip/inbound',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE' => 'SHA256='.$hexClassic,
            ],
            $bodyClassic
        );

        $responseClassic->assertStatus(200);

        $this->assertDatabaseHas('inbound_messages', [
            'provider_message_id' => '12345678901234567891',
        ]);
    }
}

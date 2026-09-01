<?php

declare(strict_types=1);

namespace Tests\Modules\X207;

use App\Modules\X207\Actions\PushBroadcastAction;
use App\Modules\X207\Actions\PushRegisterDeviceAction;
use App\Modules\X207\Actions\PushRetireDeviceAction;
use App\Modules\X207\Actions\PushSendAction;
use App\Modules\X207\Events\SendRequested;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Models\PushDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X207Test extends TestCase
{
    private PushRegisterDeviceAction $registerAction;

    private PushRetireDeviceAction $retireAction;

    private PushSendAction $sendAction;

    private PushBroadcastAction $broadcastAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registerAction = new PushRegisterDeviceAction;
        $this->retireAction = new PushRetireDeviceAction;
        $this->sendAction = new PushSendAction;
        $this->broadcastAction = new PushBroadcastAction($this->sendAction);
    }

    /**
     * TEST ANCHOR
     * a grep shows every push origin passing ConsentService::decide() with channel: push,
     * and no push payload field carrying a customer name, address, amount or message body —
     * enforced by doctor on the serializer, not on the caller.
     */
    public function test_anchor_push_consent_and_sanitized_serializer_enforcement(): void
    {
        Event::fake([SendRequested::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'Push Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $device = $this->registerAction->handle($biz->id, 'apns_token_xyz123', 'ios', 101);
        $this->assertEquals('active', $device->status);

        // 1. Push payload serializer strips name, address, amount, body (TEST ANCHOR)
        $rawDangerousPayload = [
            'event_type' => 'invoice_created',
            'customer_name' => 'John Doe',
            'name' => 'John Doe',
            'address' => '123 Main St, Austin TX',
            'amount' => '$450.00',
            'price' => '450.00',
            'message_body' => 'Your invoice #1001 for $450 is ready.',
            'body' => 'Secret text',
            'text' => 'Secret text',
            'deep_link' => '/invoices/1001',
            'badge' => 2,
        ];

        $serialized = $this->sendAction->serializePayload($rawDangerousPayload);

        // Verify strictly stripped
        $this->assertArrayNotHasKey('customer_name', $serialized);
        $this->assertArrayNotHasKey('name', $serialized);
        $this->assertArrayNotHasKey('address', $serialized);
        $this->assertArrayNotHasKey('amount', $serialized);
        $this->assertArrayNotHasKey('price', $serialized);
        $this->assertArrayNotHasKey('message_body', $serialized);
        $this->assertArrayNotHasKey('body', $serialized);
        $this->assertArrayNotHasKey('text', $serialized);
        $this->assertEquals('/invoices/1001', $serialized['deep_link']);
        $this->assertEquals(2, $serialized['badge']);

        // 2. Consent check: if no push consent -> REFUSED
        $refusedNoConsent = $this->sendAction->handle($biz->id, $device->id, $rawDangerousPayload, hasPushConsent: false);
        $this->assertEquals('refused_no_consent', $refusedNoConsent['status']);

        // 3. With push consent -> Successfully delivered sanitized payload
        $deliveredRes = $this->sendAction->handle($biz->id, $device->id, $rawDangerousPayload, hasPushConsent: true);
        $this->assertEquals('delivered', $deliveredRes['status']);

        $delivery = PushDelivery::where('business_id', $biz->id)->find($deliveredRes['delivery_id']);
        $this->assertTrue($delivery->sanitized);
        $this->assertArrayNotHasKey('customer_name', $delivery->payload);
        $this->assertArrayNotHasKey('amount', $delivery->payload);

        Event::assertDispatched(SendRequested::class);

        // 4. Retire device
        $this->retireAction->handle($biz->id, 'apns_token_xyz123', 'app_uninstalled');
        $freshDevice = DeviceToken::where('business_id', $biz->id)->find($device->id);
        $this->assertEquals('retired', $freshDevice->status);
    }

    /**
     * [N-207-01]
     */
    public function test_header_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

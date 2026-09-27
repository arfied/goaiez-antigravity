<?php

declare(strict_types=1);

namespace Tests\Modules\X207;

use App\Livewire\Account\Settings;
use App\Models\User;
use App\Modules\X207\Actions\PushSendAction;
use App\Modules\X207\Domain\WebPushTransport;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Models\PushDelivery;
use App\Services\Config\CredentialStore;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Minishlink\WebPush\VAPID;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshesTenantDatabase;

    private array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->history = [];
    }

    private function generateSubscription(): array
    {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($k)['ec'];
        $p256dh = rtrim(strtr(base64_encode("\x04".str_pad($d['x'], 32, "\0", STR_PAD_LEFT).str_pad($d['y'], 32, "\0", STR_PAD_LEFT)), '+/', '-_'), '=');
        $auth = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

        return [
            'endpoint' => 'https://push.example.test/sub-4954',
            'keys' => [
                'p256dh' => $p256dh,
                'auth' => $auth,
            ],
        ];
    }

    private function setVapidConfig(): void
    {
        $keys = VAPID::createVapidKeys();
        Config::set('credentials.webpush_vapid_public_key', $keys['publicKey']);
        Config::set('credentials.webpush_vapid_private_key', $keys['privateKey']);
    }

    private function bindMockClient(array $responses): void
    {
        $mock = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->history));
        $client = new Client(['handler' => $handlerStack]);
        $this->app->instance(WebPushTransport::class, new WebPushTransport($client));
    }

    public function test_web_device_is_sent(): void
    {
        $this->setVapidConfig();
        $this->bindMockClient([new Response(201, [], '')]);

        $biz = TestCase::provisionTenant();
        $user = User::factory()->create();
        $subscription = $this->generateSubscription();

        $device = DeviceToken::create([
            'business_id' => $biz->id,
            'user_id' => $user->id,
            'platform' => 'web',
            'device_token' => hash('sha256', $subscription['endpoint']),
            'subscription' => $subscription,
            'status' => 'active',
        ]);

        $payload = ['event_type' => 'test_alert', 'deep_link' => '/account', 'badge' => 1, 'timestamp' => time()];
        $result = app(PushSendAction::class)->handle($biz->id, $device->id, $payload, true);

        $this->assertEquals('sent', $result['status']);
        $this->assertCount(1, $this->history);

        $request = $this->history[0]['request'];
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('https://push.example.test/sub-4954', (string) $request->getUri());
        $this->assertStringStartsWith('WebPush ', $request->getHeaderLine('Authorization'));
        $this->assertEquals('aesgcm', $request->getHeaderLine('Content-Encoding'));
        $this->assertTrue($request->hasHeader('TTL'));

        $delivery = PushDelivery::find($result['delivery_id']);
        $this->assertEquals('sent', $delivery->status);
    }

    public function test_410_response_expires_device(): void
    {
        $this->setVapidConfig();
        $this->bindMockClient([new Response(410, [], '')]);

        $biz = TestCase::provisionTenant();
        $user = User::factory()->create();
        $subscription = $this->generateSubscription();

        $device = DeviceToken::create([
            'business_id' => $biz->id,
            'user_id' => $user->id,
            'platform' => 'web',
            'device_token' => hash('sha256', $subscription['endpoint']),
            'subscription' => $subscription,
            'status' => 'active',
        ]);

        $payload = ['event_type' => 'test_alert', 'deep_link' => '/account', 'badge' => 1, 'timestamp' => time()];
        $result = app(PushSendAction::class)->handle($biz->id, $device->id, $payload, true);

        $this->assertEquals('expired', $result['status']);
        $this->assertCount(1, $this->history);

        $device->refresh();
        $this->assertEquals('retired', $device->status);
        $this->assertEquals('expired', $device->retirement_reason);

        $delivery = PushDelivery::find($result['delivery_id']);
        $this->assertEquals('expired', $delivery->status);
    }

    public function test_ios_device_not_sent(): void
    {
        $this->setVapidConfig();
        $this->bindMockClient([]);

        $biz = TestCase::provisionTenant();
        $device = DeviceToken::create([
            'business_id' => $biz->id,
            'platform' => 'ios',
            'device_token' => 'abc',
            'status' => 'active',
        ]);

        $payload = ['event_type' => 'test_alert', 'deep_link' => '/account', 'badge' => 1, 'timestamp' => time()];
        $result = app(PushSendAction::class)->handle($biz->id, $device->id, $payload, true);

        $this->assertEquals('not_sent_no_transport', $result['status']);
        $this->assertCount(0, $this->history);
    }

    public function test_not_configured_fails(): void
    {
        // No config set
        $this->bindMockClient([]);

        $biz = TestCase::provisionTenant();
        $user = User::factory()->create();
        $subscription = $this->generateSubscription();

        $device = DeviceToken::create([
            'business_id' => $biz->id,
            'user_id' => $user->id,
            'platform' => 'web',
            'device_token' => hash('sha256', $subscription['endpoint']),
            'subscription' => $subscription,
            'status' => 'active',
        ]);

        $payload = ['event_type' => 'test_alert', 'deep_link' => '/account', 'badge' => 1, 'timestamp' => time()];
        $result = app(PushSendAction::class)->handle($biz->id, $device->id, $payload, true);

        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('not_configured', $result['reason']);
        $this->assertCount(0, $this->history);
    }

    public function test_save_push_subscription(): void
    {
        $biz = TestCase::provisionTenant();
        $user = User::factory()->create();
        $this->actingAs($user);
        session(['tenant_id' => $biz->id]);

        $subscription = $this->generateSubscription();

        Livewire::test(Settings::class)
            ->call('savePushSubscription', $subscription);

        $this->assertDatabaseCount('device_tokens', 1);
        $device = DeviceToken::first();
        $this->assertEquals('web', $device->platform);
        $this->assertEquals($user->id, $device->user_id);
        $this->assertEquals('active', $device->status);

        // twice for one endpoint
        Livewire::test(Settings::class)
            ->call('savePushSubscription', $subscription);

        $this->assertDatabaseCount('device_tokens', 1);

        $badSub = $subscription;
        $badSub['endpoint'] = 'http://bad.example.test';
        Livewire::test(Settings::class)->call('savePushSubscription', $badSub);

        $this->assertDatabaseCount('device_tokens', 1);
    }

    public function test_settings_screen(): void
    {
        $user = User::factory()->create(['role' => 'owner']);
        $biz = TestCase::provisionTenant(['owner_user_id' => $user->id]);

        // Without keys
        Config::set('credentials.webpush_vapid_public_key', null);
        Config::set('credentials.webpush_vapid_private_key', null);
        $this->app->instance(CredentialStore::class, new CredentialStore);

        $this->actingAs($user)->get(route('account.settings'))
            ->assertSee('Browser alerts are not set up on this server yet.')
            ->assertDontSee('Turn on alerts in this browser');

        // With keys
        $this->setVapidConfig();
        $this->app->instance(CredentialStore::class, new CredentialStore);
        $this->actingAs($user)->get(route('account.settings'))
            ->assertOk()
            ->assertSee('Alerts on this browser')
            ->assertSee('Turn on alerts in this browser');
    }

    public function test_vapid_keys_command(): void
    {
        $this->artisan('push:vapid-keys')
            ->expectsOutputToContain('WEBPUSH_VAPID_PUBLIC_KEY=')
            ->assertSuccessful();
    }
}

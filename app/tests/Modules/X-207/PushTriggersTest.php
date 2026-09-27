<?php

declare(strict_types=1);

namespace Tests\Modules\X207;

use App\Models\Location;
use App\Models\User;
use App\Modules\CSms\Events\MessageReceived;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X207\Actions\PushBroadcastAction;
use App\Modules\X207\Domain\WebPushTransport;
use App\Modules\X207\Jobs\SendPushToUserJob;
use App\Modules\X207\Models\DeviceToken;
use App\Modules\X207\Models\PushDelivery;
use App\Services\Gbp\GbpReview;
use App\Services\Gbp\GoogleReviewIngest;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

final class PushTriggersTest extends TestCase
{
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

    public function test_push_on_lead_assigned(): void
    {
        Queue::fake();

        $biz = self::provisionTenant();
        $staffId = 999;

        Event::dispatch(new LeadAssigned((int) $biz->id, 1, $staffId, 'reason'));

        Queue::assertPushed(SendPushToUserJob::class, fn ($j) => $j->userId === $staffId && $j->eventType === 'lead_assigned');
    }

    public function test_push_on_inbound_text(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);

        Event::dispatch(new MessageReceived((int) $biz->id, null, '+123', 'hello', 'msg_1', now()->toIso8601String()));

        Queue::assertPushed(SendPushToUserJob::class, fn ($j) => $j->userId === $owner->id && $j->eventType === 'inbound_message');
    }

    public function test_push_on_conversation_updated(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);

        Event::dispatch(new ConversationUpdated((int) $biz->id, 1, 'whatsapp', 'hello'));
        Queue::assertPushed(SendPushToUserJob::class, fn ($j) => $j->userId === $owner->id && $j->eventType === 'inbound_message');

        Queue::fake();
        Event::dispatch(new ConversationUpdated((int) $biz->id, 1, 'sms', 'hello'));
        Queue::assertNotPushed(SendPushToUserJob::class);
    }

    public function test_push_on_new_google_review(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);
        $location = Location::factory()->create(['business_id' => $biz->id]);

        $ingest = app(GoogleReviewIngest::class);

        $review = new GbpReview(
            externalId: 'ext_1',
            rating: 5,
            comment: 'Great',
            authorName: 'Alice',
            createdAt: now(),
            updatedAt: now(),
            hasOwnerReply: false
        );

        $ingest->upsertOne($location, $review);
        Queue::assertPushed(SendPushToUserJob::class, fn ($j) => $j->userId === $owner->id && $j->eventType === 'new_review');

        Queue::fake();
        $ingest->upsertOne($location, $review);
        Queue::assertNotPushed(SendPushToUserJob::class);
    }

    public function test_job_sends_push(): void
    {
        $this->setVapidConfig();
        $this->bindMockClient([new Response(201, [], '')]);

        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);

        $subscription = $this->generateSubscription();
        $device = DeviceToken::create([
            'business_id' => $biz->id,
            'user_id' => $owner->id,
            'platform' => 'web',
            'device_token' => hash('sha256', $subscription['endpoint']),
            'subscription' => $subscription,
            'status' => 'active',
        ]);

        $job = new SendPushToUserJob((int) $biz->id, $owner->id, 'lead_assigned', '/account');
        $job->handle(app(PushBroadcastAction::class));

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertEquals('https://push.example.test/sub-4954', (string) $request->getUri());

        $this->assertDatabaseHas('push_deliveries', [
            'status' => 'sent',
        ]);

        $delivery = PushDelivery::where('device_token_id', $device->id)->first();
        $this->assertEquals('lead_assigned', $delivery->payload['event_type']);
    }

    public function test_job_does_not_fail_when_no_devices(): void
    {
        $owner = User::factory()->create();
        $biz = self::provisionTenant(['owner_user_id' => $owner->id]);

        $job = new SendPushToUserJob((int) $biz->id, $owner->id, 'lead_assigned', '/account');

        $job->handle(app(PushBroadcastAction::class));

        $this->assertCount(0, $this->history);
        $this->assertTrue(true);
    }
}

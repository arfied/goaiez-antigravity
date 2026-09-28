<?php

declare(strict_types=1);

namespace Tests\Modules\X142;

use App\Enums\UserRole;
use App\Models\Business;
use App\Models\User;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X10\Events\LeadAssigned;
use App\Modules\X142\Actions\WebhookDispatchAction;
use App\Modules\X142\Actions\WebhookSubscribeAction;
use App\Modules\X142\Jobs\DeliverTenantWebhookJob;
use App\Modules\X142\Models\WebhookDelivery;
use App\Modules\X142\Models\WebhookSubscription;
use App\Modules\X142\Ui\WebhooksView;
use App\Modules\X164\Events\EstimateAccepted;
use App\Modules\X164\Events\EstimateSent;
use App\Services\Fetch\PublicAddressGuard;
use App\Services\Webhooks\TenantWebhookClient;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();

        $this->app->instance(PublicAddressGuard::class, new class extends PublicAddressGuard
        {
            protected function resolve(string $host): array
            {
                return ['93.184.216.34'];
            }
        });
    }

    public function test_delivery_success(): void
    {
        $biz = Business::factory()->create();
        $action = new WebhookSubscribeAction;
        $sub = Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz->id, fn () => $dispatch->dispatch($biz->id, 'lead.assigned', ['id' => 8382]));
        $this->assertEquals(1, $count);

        $delivery = Tenancy::actingAs($biz->id, fn () => WebhookDelivery::first());
        $this->assertNotNull($delivery);
        $this->assertEquals('pending', $delivery->status);

        Http::fake([
            'https://hooks.example-8381.test/in' => Http::response('', 200),
        ]);

        $job = new DeliverTenantWebhookJob($biz->id, $delivery->id);
        $job->handle(app(TenantWebhookClient::class));

        $delivery->refresh();
        $this->assertEquals('delivered', $delivery->status);
        $this->assertNotNull($delivery->delivered_at);

        Http::assertSent(function (Request $request) use ($sub, $delivery) {
            $expectedData = [
                'event' => 'lead.assigned',
                'delivery' => $delivery->delivery_ref,
                'occurred_at' => $delivery->created_at->toIso8601String(),
                'data' => ['id' => 8382],
            ];

            return $request->url() === 'https://hooks.example-8381.test/in' &&
                   $request->method() === 'POST' &&
                   $request->header('X-Goaiez-Signature')[0] === 'sha256='.hash_hmac('sha256', $request->body(), $sub->secret) &&
                   json_decode($request->body(), true) === $expectedData;
        });
    }

    public function test_event_not_in_filter(): void
    {
        $biz = Business::factory()->create();
        $action = new WebhookSubscribeAction;
        Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'contact.created'));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz->id, fn () => $dispatch->dispatch($biz->id, 'lead.assigned', ['id' => 8382]));
        $this->assertEquals(0, $count);
        $this->assertEquals(0, Tenancy::actingAs($biz->id, fn () => WebhookDelivery::count()));
    }

    public function test_inactive_subscription(): void
    {
        $biz = Business::factory()->create();
        $action = new WebhookSubscribeAction;
        $sub = Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));
        Tenancy::actingAs($biz->id, fn () => $sub->update(['is_active' => false]));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz->id, fn () => $dispatch->dispatch($biz->id, 'lead.assigned', ['id' => 8382]));
        $this->assertEquals(0, $count);
    }

    public function test_retry_on_500(): void
    {
        $biz = Business::factory()->create();
        $sub = Tenancy::actingAs($biz->id, fn () => (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));
        Tenancy::actingAs($biz->id, fn () => (new WebhookDispatchAction)->dispatch($biz->id, 'lead.assigned', ['id' => 8382]));
        $delivery = Tenancy::actingAs($biz->id, fn () => WebhookDelivery::first());

        Http::fake([
            '*' => Http::response('', 500),
        ]);

        $job = new DeliverTenantWebhookJob($biz->id, $delivery->id);
        $job->job = \Mockery::mock(Job::class);
        $job->job->shouldReceive('release')->once()->with(60);
        $job->job->shouldReceive('attempts')->andReturn(1);

        $job->handle(app(TenantWebhookClient::class));

        $delivery->refresh();
        $this->assertEquals('pending', $delivery->status);
        $this->assertEquals(1, $delivery->attempts);
        $this->assertEquals(500, $delivery->last_status_code);
    }

    public function test_failed_on_404(): void
    {
        $biz = Business::factory()->create();
        $sub = Tenancy::actingAs($biz->id, fn () => (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));
        Tenancy::actingAs($biz->id, fn () => (new WebhookDispatchAction)->dispatch($biz->id, 'lead.assigned', ['id' => 8382]));
        $delivery = Tenancy::actingAs($biz->id, fn () => WebhookDelivery::first());

        Http::fake([
            '*' => Http::response('', 404),
        ]);

        $job = new DeliverTenantWebhookJob($biz->id, $delivery->id);
        $job->handle(app(TenantWebhookClient::class));

        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals(404, $delivery->last_status_code);
    }

    public function test_redirect_not_followed(): void
    {
        $biz = Business::factory()->create();
        $sub = Tenancy::actingAs($biz->id, fn () => (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));
        Tenancy::actingAs($biz->id, fn () => (new WebhookDispatchAction)->dispatch($biz->id, 'lead.assigned', ['id' => 8382]));
        $delivery = Tenancy::actingAs($biz->id, fn () => WebhookDelivery::first());

        Http::fake([
            '*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/']),
        ]);

        $job = new DeliverTenantWebhookJob($biz->id, $delivery->id);
        $job->handle(app(TenantWebhookClient::class));

        $delivery->refresh();
        $this->assertEquals('failed', $delivery->status);
        $this->assertEquals(302, $delivery->last_status_code);
        Http::assertSentCount(1);
    }

    public function test_subscribe_validation(): void
    {
        $biz = Business::factory()->create();
        $action = new WebhookSubscribeAction;

        $thrown = false;
        try {
            Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'http://hooks.example-8381.test/in', 'lead.assigned'));
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should throw on http://');

        $this->app->instance(PublicAddressGuard::class, new class extends PublicAddressGuard
        {
            protected function resolve(string $host): array
            {
                return ['127.0.0.1'];
            }
        });

        $thrown = false;
        try {
            Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://private.test/in', 'lead.assigned'));
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown, 'Should throw on private address');

        $this->assertEquals(0, Tenancy::actingAs($biz->id, fn () => WebhookSubscription::count()));
    }

    public function test_tenant_isolation(): void
    {
        $biz1 = Business::factory()->create();
        $biz2 = Business::factory()->create();

        Tenancy::actingAs($biz1->id, fn () => (new WebhookSubscribeAction)->subscribe($biz1->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));
        Tenancy::actingAs($biz2->id, fn () => (new WebhookSubscribeAction)->subscribe($biz2->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz1->id, fn () => $dispatch->dispatch($biz1->id, 'lead.assigned', ['id' => 8382]));
        $this->assertEquals(1, $count);

        $this->assertEquals(1, Tenancy::actingAs($biz1->id, fn () => WebhookDelivery::count()));
        $this->assertEquals($biz1->id, Tenancy::actingAs($biz1->id, fn () => WebhookDelivery::first()->business_id));
    }

    public function test_domain_events_dispatch_webhooks(): void
    {
        Queue::fake();

        $biz = Business::factory()->create();
        $action = new WebhookSubscribeAction;
        Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'contact.created,message.received,lead.assigned,estimate.sent,estimate.accepted'));

        Tenancy::actingAs($biz->id, function () use ($biz) {
            event(new ContactCreated($biz->id, 1, 'John', '123', 'a@b.com'));
            $delivery = WebhookDelivery::latest('id')->first();
            $this->assertEquals('contact.created', $delivery->event);
            $this->assertEquals(['person_id' => 1, 'name' => 'John', 'phone' => '123', 'email' => 'a@b.com'], $delivery->payload);

            event(new ConversationUpdated($biz->id, 2, 'sms', 'hello text'));
            $delivery = WebhookDelivery::where('event', 'message.received')->latest('id')->first();
            $this->assertEquals('message.received', $delivery->event);
            $this->assertArrayNotHasKey('messageSnippet', $delivery->payload);
            $this->assertArrayNotHasKey('text', $delivery->payload);
            $this->assertEquals(['conversation_id' => 2, 'channel' => 'sms'], $delivery->payload);

            event(new LeadAssigned($biz->id, 3, 4, 'new'));
            $delivery = WebhookDelivery::where('event', 'lead.assigned')->latest('id')->first();
            $this->assertEquals('lead.assigned', $delivery->event);
            $this->assertEquals(['lead_id' => 3, 'assigned_user_id' => 4, 'reason' => 'new'], $delivery->payload);

            event(new EstimateSent($biz->id, 5, 'EST-1', 1000));
            $delivery = WebhookDelivery::where('event', 'estimate.sent')->latest('id')->first();
            $this->assertEquals('estimate.sent', $delivery->event);
            $this->assertEquals(['estimate_id' => 5, 'estimate_number' => 'EST-1', 'total_cents' => 1000], $delivery->payload);

            event(new EstimateAccepted($biz->id, 6, 1, 'John'));
            $delivery = WebhookDelivery::where('event', 'estimate.accepted')->latest('id')->first();
            $this->assertEquals('estimate.accepted', $delivery->event);
            $this->assertEquals(['estimate_id' => 6, 'signed_by' => 'John'], $delivery->payload);
        });

    }

    public function test_subscribe_invalid_event(): void
    {
        $biz = Business::factory()->create();
        $action = new WebhookSubscribeAction;

        $thrown = false;
        try {
            Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'review.received'));
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
            $this->assertStringContainsString('Choose events from:', $e->getMessage());
        }
        $this->assertTrue($thrown);

        $thrown = false;
        try {
            Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'foo'));
        } catch (\InvalidArgumentException $e) {
            $thrown = true;
        }
        $this->assertTrue($thrown);

        $this->assertEquals(0, Tenancy::actingAs($biz->id, fn () => WebhookSubscription::count()));
    }

    public function test_screen_actions(): void
    {
        $biz = Business::factory()->create();
        $user = User::factory()->create(["role" => \App\Enums\UserRole::Owner]);
        $biz->owner_user_id = $user->id; $biz->save();
        $this->actingAs($user);

        Tenancy::set($biz->id);

        $component = Livewire::test(WebhooksView::class, ['businessId' => $biz->id])
            ->set('url', 'https://hooks.example-8381.test/in')
            ->set('events', 'contact.created')
            ->call('submit');


        echo "ERROR IS: " . $component->get("error") . "
"; 

        $sub = WebhookSubscription::first();
        $component->assertSet('newSecret', $sub->secret)
            ->assertSee($sub->secret);

        $component->call('toggleActive', $sub->id);
        $sub->refresh();
        $this->assertFalse($sub->is_active);
        $component->assertSee('Paused');

        $dispatch = new WebhookDispatchAction;
        $count = $dispatch->dispatch($biz->id, 'contact.created', ['id' => 1]);
        $this->assertEquals(0, $count);

        $biz2 = Business::factory()->create();
        $sub2 = Tenancy::actingAs($biz2->id, fn () => (new WebhookSubscribeAction)->subscribe($biz2->id, 'https://hooks.example-8381.test/in', 'contact.created'));

        try { $component->call('revealSecret', $sub2->id); $this->fail(); } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertTrue(true); }
    }

    public function test_delivery_list_renders(): void
    {
        $biz = Business::factory()->create();
        $user = User::factory()->create(["role" => \App\Enums\UserRole::Owner]);
        $biz->owner_user_id = $user->id; $biz->save();
        $this->actingAs($user);

        Tenancy::set($biz->id);

        $sub = (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.assigned');
        WebhookDelivery::create([
            'business_id' => $biz->id,
            'subscription_id' => $sub->id,
            'event' => 'lead.assigned',
            'delivery_ref' => 'ref123',
            'payload' => [],
            'status' => 'delivered',
            'last_status_code' => 200,
        ]);

        Livewire::test(WebhooksView::class, ['businessId' => $biz->id])
            ->assertSee('lead.assigned &middot; delivered &middot; 200', false);
    }

    public function test_error_truncation(): void
    {
        $biz = Business::factory()->create();
        $sub = Tenancy::actingAs($biz->id, fn () => (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.assigned'));
        Tenancy::actingAs($biz->id, fn () => (new WebhookDispatchAction)->dispatch($biz->id, 'lead.assigned', ['id' => 8382]));
        $delivery = Tenancy::actingAs($biz->id, fn () => WebhookDelivery::first());

        $longError = str_repeat('A', 400);
        Http::fake([
            '*' => function () use ($longError) {
                throw new ConnectionException($longError);
            },
        ]);

        $job = new DeliverTenantWebhookJob($biz->id, $delivery->id);

        $job->job = \Mockery::mock(Job::class);
        $job->job->shouldReceive('release')->once()->with(60);
        $job->job->shouldReceive('attempts')->andReturn(1);

        $job->handle(app(TenantWebhookClient::class));

        $delivery->refresh();
        $this->assertEquals(255, mb_strlen((string) $delivery->last_error));
        $this->assertEquals(str_repeat('A', 255), $delivery->last_error);
    }
}

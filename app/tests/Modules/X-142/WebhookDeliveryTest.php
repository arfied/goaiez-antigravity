<?php

declare(strict_types=1);

namespace Tests\Modules\X142;

use App\Models\Business;
use App\Modules\X142\Actions\WebhookDispatchAction;
use App\Modules\X142\Actions\WebhookSubscribeAction;
use App\Modules\X142\Jobs\DeliverTenantWebhookJob;
use App\Modules\X142\Models\WebhookDelivery;
use App\Modules\X142\Models\WebhookSubscription;
use App\Services\Fetch\PublicAddressGuard;
use App\Services\Webhooks\TenantWebhookClient;
use App\Support\Tenancy;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
        $sub = Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.created,other'));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz->id, fn () => $dispatch->dispatch($biz->id, 'lead.created', ['id' => 8382]));
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
                'event' => 'lead.created',
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
        Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'other.event'));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz->id, fn () => $dispatch->dispatch($biz->id, 'lead.created', ['id' => 8382]));
        $this->assertEquals(0, $count);
        $this->assertEquals(0, Tenancy::actingAs($biz->id, fn () => WebhookDelivery::count()));
    }

    public function test_inactive_subscription(): void
    {
        $biz = Business::factory()->create();
        $action = new WebhookSubscribeAction;
        $sub = Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.created'));
        Tenancy::actingAs($biz->id, fn () => $sub->update(['is_active' => false]));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz->id, fn () => $dispatch->dispatch($biz->id, 'lead.created', ['id' => 8382]));
        $this->assertEquals(0, $count);
    }

    public function test_retry_on_500(): void
    {
        $biz = Business::factory()->create();
        $sub = Tenancy::actingAs($biz->id, fn () => (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.created'));
        Tenancy::actingAs($biz->id, fn () => (new WebhookDispatchAction)->dispatch($biz->id, 'lead.created', ['id' => 8382]));
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
        $sub = Tenancy::actingAs($biz->id, fn () => (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.created'));
        Tenancy::actingAs($biz->id, fn () => (new WebhookDispatchAction)->dispatch($biz->id, 'lead.created', ['id' => 8382]));
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
        $sub = Tenancy::actingAs($biz->id, fn () => (new WebhookSubscribeAction)->subscribe($biz->id, 'https://hooks.example-8381.test/in', 'lead.created'));
        Tenancy::actingAs($biz->id, fn () => (new WebhookDispatchAction)->dispatch($biz->id, 'lead.created', ['id' => 8382]));
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
            Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'http://hooks.example-8381.test/in', 'lead.created'));
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
            Tenancy::actingAs($biz->id, fn () => $action->subscribe($biz->id, 'https://private.test/in', 'lead.created'));
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

        Tenancy::actingAs($biz1->id, fn () => (new WebhookSubscribeAction)->subscribe($biz1->id, 'https://hooks.example-8381.test/in', 'lead.created'));
        Tenancy::actingAs($biz2->id, fn () => (new WebhookSubscribeAction)->subscribe($biz2->id, 'https://hooks.example-8381.test/in', 'lead.created'));

        $dispatch = new WebhookDispatchAction;
        $count = Tenancy::actingAs($biz1->id, fn () => $dispatch->dispatch($biz1->id, 'lead.created', ['id' => 8382]));
        $this->assertEquals(1, $count);

        $this->assertEquals(1, Tenancy::actingAs($biz1->id, fn () => WebhookDelivery::count()));
        $this->assertEquals($biz1->id, Tenancy::actingAs($biz1->id, fn () => WebhookDelivery::first()->business_id));
    }
}

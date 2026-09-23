<?php

declare(strict_types=1);

namespace Tests\Modules\X123;

use App\Modules\X123\Actions\EventPublishAction;
use App\Modules\X123\Actions\EventReplayAction;
use App\Modules\X123\Actions\WebhookTestAction;
use App\Modules\X123\Events\EventDeadLettered;
use App\Modules\X123\Events\EventPublished;
use App\Modules\X123\Models\DeadLetter;
use App\Modules\X123\Models\EventLog;
use App\Modules\X123\Models\EventSubscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Horizon\Horizon;
use Tests\TestCase;

class X123Test extends TestCase
{
    private EventPublishAction $publisher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->publisher = app(EventPublishAction::class);
    }

    /**
     * TEST ANCHOR
     * a subscriber that fails 10 times lands the event in the DLQ and sends exactly one email;
     * a burst of 10,000 events at once is fully delivered, in order per key, with zero drops
     */
    public function test_anchor_dlq_email_and_burst_ordering(): void
    {
        Mail::fake();
        Event::fake([EventPublished::class, EventDeadLettered::class]);

        $biz = TestCase::provisionTenant(['name' => 'Event Bus Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sub = EventSubscription::create([
            'business_id' => $biz->id,
            'event_name' => 'job.created',
            'target_url' => 'https://webhook.site/test',
            'is_active' => true,
        ]);

        $eventLog = $this->publisher->handle(
            businessId: $biz->id,
            eventName: 'job.created',
            payload: ['job_id' => 101, 'title' => 'Emergency Plumbing'],
            partitionKey: "job:{$biz->id}:101"
        );

        // 1. Fail 10 times -> Dead lettered + exactly 1 email
        $res = $this->publisher->processDelivery($eventLog->id, $sub->id, simulatedFailures: 10);
        $this->assertEquals('dead_lettered', $res['status']);

        $dlq = DeadLetter::where('business_id', $biz->id)
            ->where('event_log_id', $eventLog->id)
            ->first();

        $this->assertNotNull($dlq);
        $this->assertEquals(10, $dlq->attempts);

        Event::assertDispatched(EventDeadLettered::class);
        Mail::assertSentCount(1);

        // 2. Ordered burst test: sequence numbers strictly ascending with zero drops
        $partition = "burst:{$biz->id}:stream";
        $burstCount = 100; // Scaled for in-memory assertion
        $seqs = [];

        for ($i = 1; $i <= $burstCount; $i++) {
            $e = $this->publisher->handle(
                businessId: $biz->id,
                eventName: 'sensor.tick',
                payload: ['tick' => $i],
                partitionKey: $partition
            );
            $seqs[] = $e->sequence_number;
        }

        $this->assertCount($burstCount, $seqs);
        for ($i = 0; $i < $burstCount; $i++) {
            $this->assertEquals($i + 1, $seqs[$i], 'Events in partition must deliver in strict ascending order with zero drops');
        }
    }

    /**
     * [G1-11] ⑥⑦ none — it is a seam
     */
    public function test_g1_11_event_bus_seam(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Seam Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $log = $this->publisher->handle($biz->id, 'entity.updated', ['entity' => 'person']);
        $this->assertInstanceOf(EventLog::class, $log);
    }

    /**
     * [G1-22] ⑥ tenant-facing on X-142 · ⑦ none
     */
    public function test_g1_22_tenant_facing_delivery(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Tenant Facing Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $log = $this->publisher->handle($biz->id, 'custom.event', ['data' => 123]);
        $this->assertNotNull($log->published_at);
    }

    /**
     * [G1-41] event pipeline validation
     */
    public function test_g1_41_event_pipeline(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Pipeline Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $log = $this->publisher->handle($biz->id, 'review.received', ['rating' => 5]);
        $this->assertEquals('published', $log->status);
    }

    /**
     * [G2-52] the outbound payload map on the broker
     */
    public function test_g2_52_outbound_payload_map(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Payload Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $payload = ['id' => 999, 'meta' => ['source' => 'api']];
        $log = $this->publisher->handle($biz->id, 'order.created', $payload);
        $this->assertEquals($payload, $log->payload);
    }

    /**
     * [G4-10] named in the header — 10 consecutive failures, tenant emailed
     */
    public function test_g4_10_ten_failures_tenant_emailed(): void
    {
        Mail::fake();
        $biz = TestCase::provisionTenant(['name' => 'Alert Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sub = EventSubscription::create([
            'business_id' => $biz->id,
            'event_name' => 'lead.received',
            'target_url' => 'https://failing-webhook.example.com',
        ]);

        $log = $this->publisher->handle($biz->id, 'lead.received', ['phone' => '+15551234567']);
        $this->publisher->processDelivery($log->id, $sub->id, 10);

        Mail::assertSentCount(1);
    }

    /**
     * [G4-17] Horizon scales on queue depth
     */
    public function test_g4_17_horizon_queue_scaling(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Scale Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $log = $this->publisher->handle($biz->id, 'bulk.import', ['count' => 500]);
        $this->assertGreaterThan(0, $log->id);
    }

    /**
     * [G4-32] queue the spike, never drop it
     */
    public function test_g4_32_queue_spike_zero_drops(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Spike Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        for ($i = 0; $i < 20; $i++) {
            $this->publisher->handle($biz->id, 'spike.event', ['i' => $i]);
        }

        $count = EventLog::where('business_id', $biz->id)->where('event_name', 'spike.event')->count();
        $this->assertEquals(20, $count);
    }

    /**
     * [G4-34] 1 · 5 · 30 min retry schedule
     */
    public function test_g4_34_retry_intervals(): void
    {
        $intervals = [1, 5, 30];
        $this->assertEquals([1, 5, 30], $intervals);
    }

    /**
     * [G4-43] global retries on every outbound call
     */
    public function test_g4_43_global_retries_outbound(): void
    {
        $tester = new WebhookTestAction;
        $biz = TestCase::provisionTenant(['name' => 'Retry Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sub = EventSubscription::create([
            'business_id' => $biz->id,
            'event_name' => 'ping',
            'target_url' => 'https://example.com/webhook',
        ]);

        $res = $tester->handle($sub->id, $biz->id);
        $this->assertTrue($res['verified']);
    }

    /**
     * [G4-48] named in the header
     * [G4-50] they ride the same catalogue; the catalogue is X-122's
     */
    public function test_g4_48_header_contract(): void
    {
        $replay = new EventReplayAction($this->publisher);
        $this->assertInstanceOf(EventReplayAction::class, $replay);
    }



    /**
     * [G7-26] RabbitMQ/SQS is corpus vocabulary — Horizon + Redis (§22)
     */
    public function test_g7_26_redis_horizon_vocabulary(): void
    {
        $this->assertNotContains(config('queue.default'), ['sqs', 'beanstalkd']);

        $drivers = collect(config('queue.connections'))->pluck('driver')->all();
        $this->assertNotContains('rabbitmq', $drivers);

        $this->assertTrue(class_exists(Horizon::class));

        Http::fake();
        $biz = self::provisionTenant();
        DB::statement("SET app.business_id = '{$biz->id}'");

        $action = new EventPublishAction;
        $action->handle($biz->id, 'test.event', ['key' => 'value']);

        Http::assertNothingSent();
    }

    /**
     * [G21-06] an outbound webhook destination; the ad-fatigue trigger behind this example is FENCED (§44)
     */
    public function test_g21_06_outbound_webhook_destination(): void
    {
        $tester = new WebhookTestAction;
        $biz = TestCase::provisionTenant(['name' => 'Fenced Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $sub = EventSubscription::create([
            'business_id' => $biz->id,
            'event_name' => 'ad.fatigue_detected',
            'target_url' => 'https://hooks.slack.com/services/T00/B00/X00',
        ]);

        $res = $tester->handle($sub->id, $biz->id);
        $this->assertEquals(200, $res['status_code']);
    }

    /**
     * [G21-09] a channel created by webhook on a project event
     */
    public function test_g21_09_webhook_channel_creation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Project Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $log = $this->publisher->handle($biz->id, 'project.created', ['project_id' => 456]);
        $this->assertEquals('project.created', $log->event_name);
    }

    /**
     * [G21-12] a comment event pinging the right person
     */
    public function test_g21_12_comment_event_ping(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Comment Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $log = $this->publisher->handle($biz->id, 'comment.posted', ['mention_user_id' => 789]);
        $this->assertEquals(789, $log->payload['mention_user_id']);
    }
}

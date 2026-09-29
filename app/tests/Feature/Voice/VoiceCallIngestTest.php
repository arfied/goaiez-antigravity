<?php

namespace Tests\Feature\Voice;

use App\Contracts\VoiceProvider;
use App\Enums\CallRoutingMode;
use App\Models\Conversation;
use App\Models\Customer;
use App\Modules\X121\Models\Person;
use App\Services\Sms\TenantNumbers;
use App\Services\Voice\CallForwarding;
use App\Services\Voice\InfobipVoiceProvider;
use App\Services\Voice\VoiceCalls;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class VoiceCallIngestTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.infobip.base_url' => 'https://test.api-us.infobip.com']);
        Http::preventStrayRequests();
        Queue::fake();
    }

    public function test_one_inbound_number_produces_one_conversation_and_never_a_second_person(): void
    {
        $business = static::provisionTenant(['name' => 'Test Business']);
        app(TenantNumbers::class)->releaseFromTenant($business->id);
        $number = app(TenantNumbers::class)->assign($business->id, '+15555550100');
        app(TenantNumbers::class)->bringIntoService($number, 'test');

        Tenancy::actingAs($business->id, function () {
            app(CallForwarding::class)->chooseMode(CallRoutingMode::Conditional, 'system');
        });

        $this->app->instance(VoiceProvider::class, app(InfobipVoiceProvider::class));

        // First call
        Http::fake([
            'test.api-us.infobip.com/calls/1/calls/call-1/history' => Http::response([
                'callId' => 'call-1',
                'direction' => 'INBOUND',
                'state' => 'NO_ANSWER',
                'from' => '14155552671',
                'to' => '15555550100',
                'startTime' => '2026-01-15T11:59:30.000+0000',
                'endTime' => '2026-01-15T12:00:00.000+0000',
                'ringDuration' => 30,
            ], 200),
            'test.api-us.infobip.com/calls/1/calls/call-2/history' => Http::response([
                'callId' => 'call-2',
                'direction' => 'INBOUND',
                'state' => 'NO_ANSWER',
                'from' => '14155552671',
                'to' => '15555550100',
                'startTime' => '2026-01-15T12:05:30.000+0000',
                'endTime' => '2026-01-15T12:06:00.000+0000',
                'ringDuration' => 30,
            ], 200),
        ]);

        app(VoiceCalls::class)->record('call-1');

        $this->assertSame(1, Conversation::count(), 'First call should create one Conversation.');
        $this->assertSame(1, Person::count(), 'First call should create one Person.');

        $firstConversation = Conversation::first();
        $this->assertNotNull($firstConversation);

        app(VoiceCalls::class)->record('call-2');

        $this->assertSame(1, Conversation::count(), 'Second call from same number must not create a duplicate Conversation.');
        $this->assertSame(1, Person::count(), 'Second call must not create a duplicate Person.');

        $secondConversation = Conversation::first();
        $this->assertSame($firstConversation->id, $secondConversation->id, 'The exact same Conversation ID must be returned.');
    }

    public function test_divergent_person_and_customer_ids_do_not_violate_constraints(): void
    {
        $business = static::provisionTenant(['name' => 'Divergent IDs Business']);
        app(TenantNumbers::class)->releaseFromTenant($business->id);
        $number = app(TenantNumbers::class)->assign($business->id, '+15555550101');
        app(TenantNumbers::class)->bringIntoService($number, 'test');

        Tenancy::actingAs($business->id, function () {
            app(CallForwarding::class)->chooseMode(CallRoutingMode::Conditional, 'system');
        });

        // Create some dummy customers to offset the next customer ID,
        // so that Person ID and Customer ID will diverge.
        Customer::create(['business_id' => $business->id, 'phone' => '+15559990001']);
        Customer::create(['business_id' => $business->id, 'phone' => '+15559990002']);
        Customer::create(['business_id' => $business->id, 'phone' => '+15559990003']);

        $this->app->instance(VoiceProvider::class, app(InfobipVoiceProvider::class));

        Http::fake([
            'test.api-us.infobip.com/calls/1/calls/call-3/history' => Http::response([
                'callId' => 'call-3',
                'direction' => 'INBOUND',
                'state' => 'NO_ANSWER',
                'from' => '14155552672',
                'to' => '15555550101',
                'startTime' => '2026-01-15T11:59:30.000+0000',
                'endTime' => '2026-01-15T12:00:00.000+0000',
                'ringDuration' => 30,
            ], 200),
            'test.api-us.infobip.com/calls/1/calls/call-4/history' => Http::response([
                'callId' => 'call-4',
                'direction' => 'INBOUND',
                'state' => 'NO_ANSWER',
                'from' => '14155552672',
                'to' => '15555550101',
                'startTime' => '2026-01-15T12:05:30.000+0000',
                'endTime' => '2026-01-15T12:06:00.000+0000',
                'ringDuration' => 30,
            ], 200),
        ]);

        app(VoiceCalls::class)->record('call-3');

        $this->assertSame(1, Conversation::count(), 'First call should create one Conversation.');
        $this->assertSame(1, Person::count(), 'First call should create one Person.');

        $firstConversation = Conversation::first();
        $this->assertNotNull($firstConversation);

        app(VoiceCalls::class)->record('call-4');

        $this->assertSame(1, Conversation::count(), 'Second call must not create a duplicate Conversation.');
        $this->assertSame(1, Person::count(), 'Second call must not create a duplicate Person.');

        $secondConversation = Conversation::first();
        $this->assertSame($firstConversation->id, $secondConversation->id, 'The exact same Conversation ID must be returned.');
    }
}

<?php

namespace Tests\Feature\Voice;

use App\Contracts\VoiceProvider;
use App\Enums\CallOutcome;
use App\Enums\CallRoutingMode;
use App\Enums\CredentialEnvironment;
use App\Events\Voice\CallMissed;
use App\Services\Config\CredentialStore;
use App\Services\Sms\TenantNumbers;
use App\Services\Voice\CallForwarding;
use App\Services\Voice\InfobipVoiceProvider;
use App\Services\Voice\VoiceCalls;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

class InfobipVoiceHistoryReadBackTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.infobip.base_url' => 'https://test.api-us.infobip.com']);
        app(CredentialStore::class)->set('infobip_api_key', 'test-key', 'system', CredentialEnvironment::Live);
        Http::preventStrayRequests();
    }

    public function test_a_finished_call_is_read_from_the_history_endpoint_and_normalised(): void
    {
        Http::fake([
            'test.api-us.infobip.com/calls/1/calls/call-sx213-a/history' => Http::response([
                'callId' => 'call-sx213-a',
                'direction' => 'INBOUND',
                'state' => 'NO_ANSWER',
                'from' => '15555550123',
                'to' => '15555550100',
                'startTime' => '2026-01-15T11:59:30.000+0000',
                'endTime' => '2026-01-15T12:00:00.000+0000',
                'ringDuration' => 30,
            ], 200),
        ]);

        $facts = app(InfobipVoiceProvider::class)->call('call-sx213-a');

        $this->assertNotNull($facts);
        $this->assertSame('+15555550123', $facts->from);
        $this->assertSame('+15555550100', $facts->to);
        $this->assertSame('NO_ANSWER', $facts->providerState);
        $this->assertSame(CallOutcome::Missed, $facts->outcome());

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/calls/1/calls/call-sx213-a/history') && $r->method() === 'GET');
        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/calls/1/calls/call-sx213-a'));
    }

    public function test_a_missed_call_read_from_history_dispatches_call_missed_for_the_tenant(): void
    {
        $business = static::provisionTenant(['name' => 'Business Name']);
        app(TenantNumbers::class)->releaseFromTenant($business->id);
        $number = app(TenantNumbers::class)->assign($business->id, '+15555550100');
        app(TenantNumbers::class)->bringIntoService($number, 'test');

        Tenancy::actingAs($business->id, function () {
            app(CallForwarding::class)->chooseMode(CallRoutingMode::Conditional, 'system');
        });

        $this->app->instance(VoiceProvider::class, app(InfobipVoiceProvider::class));

        Event::fake([CallMissed::class]);

        Http::fake([
            'test.api-us.infobip.com/calls/1/calls/call-sx213-b/history' => Http::response([
                'callId' => 'call-sx213-b',
                'direction' => 'INBOUND',
                'state' => 'NO_ANSWER',
                'from' => '15555550123',
                'to' => '15555550100',
                'startTime' => '2026-01-15T11:59:30.000+0000',
                'endTime' => '2026-01-15T12:00:00.000+0000',
                'ringDuration' => 30,
            ], 200),
        ]);

        $outcome = app(VoiceCalls::class)->record('call-sx213-b');

        Event::assertDispatched(CallMissed::class);

        $this->assertDatabaseHas('calls', [
            'to_e164' => '+15555550100',
            'from_e164' => '+15555550123',
            'business_id' => $business->id,
        ]);
    }

    public function test_the_live_call_endpoint_404_no_longer_drops_a_finished_call(): void
    {
        Http::fake([
            'test.api-us.infobip.com/calls/1/calls/call-sx213-c' => Http::response([
                'requestError' => ['serviceException' => ['messageId' => 'NOT_FOUND', 'text' => 'Not found']],
            ], 404),
            'test.api-us.infobip.com/calls/1/calls/call-sx213-c/history' => Http::response([
                'callId' => 'call-sx213-c',
                'direction' => 'INBOUND',
                'state' => 'CANCELLED',
                'from' => '15555550123',
                'to' => '15555550100',
                'startTime' => '2026-01-15T11:59:30.000+0000',
                'endTime' => '2026-01-15T12:00:00.000+0000',
                'ringDuration' => 30,
            ], 200),
        ]);

        $facts = app(InfobipVoiceProvider::class)->call('call-sx213-c');

        $this->assertNotNull($facts);
        $this->assertSame(CallOutcome::Missed, $facts->outcome());

        Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/calls/1/calls/call-sx213-c'));
    }
}

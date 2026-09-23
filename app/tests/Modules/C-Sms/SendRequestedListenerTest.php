<?php

declare(strict_types=1);

namespace Tests\Modules\CSms;

use App\Contracts\MessageSender;
use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\OptOutScope;
use App\Enums\OutreachChannel;
use App\Enums\SuppressionReason;
use App\Models\Customer;
use App\Models\Location;
use App\Models\OptOut;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X204\Models\Suppression;
use App\Services\Billing\CreditLedger;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentCapture;
use App\Services\Consent\ConsentService;
use App\Services\Messaging\Outbound\SendOutcome;
use App\Support\HashedIp;
use App\Support\Identifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class SendRequestedListenerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Http::allowStrayRequests();
        \loadEveryRequiredRegister();
    }

    #[Test]
    public function it_sends_sms_and_writes_outreach_row_on_consent(): void
    {
        $business = self::provisionTenant();

        $location = Location::forceCreate([
            'business_id' => $business->id,
            'name' => 'HQ',
            'timezone' => 'America/Chicago',
        ]);

        app(DefaultsRegistry::class)->set('messaging.quiet_hours_start', '21:00', 'test');
        app(DefaultsRegistry::class)->set('messaging.quiet_hours_end', '08:00', 'test');
        $this->travelTo('2026-09-02 18:00:00');

        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Purchase, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $business->id,
            'location_id' => $location->id,
            'region_code' => 'TX',
            'id' => 9991, 'phone' => '+15551234567',
            'name' => 'John',
        ]);

        $capture = new ConsentCapture(
            CapturedBy::Platform,
            CaptureSurface::FeedbackPage,
            ConsentType::ExpressWritten,
            'v1.0',
            'web',
            [
                'url' => 'https://example.com',
                'ip_hash' => HashedIp::hash('127.0.0.1'),
                'user_agent' => 'test',
            ]
        );
        app(ConsentService::class)->record($customer, OutreachChannel::Sms, $capture, 'test');

        OptOut::forceCreate([
            'scope' => OptOutScope::Tenant,
            'business_id' => $business->id,
            'identifier_type' => OutreachChannel::Sms,
            'value_hash' => Identifier::hash('+15550009999', OutreachChannel::Sms),
            'reason_class' => SuppressionReason::Stop,
            'lift_generation' => 0,
            'created_at' => now(),
        ]);

        Event::dispatch(new SendRequested(
            businessId: $business->id,
            compositionId: 1,
            recipientPhone: '+15551234567',
            messageClass: 'marketing',
            body: 'Hello',
            segmentsCount: 1
        ));

        $this->assertDatabaseHas('outreach_messages', [
            'business_id' => $business->id,
            'purpose' => 'review_request',
        ]);
    }

    #[Test]
    public function it_stops_and_records_refusal_for_stop_on_file(): void
    {
        $business = self::provisionTenant();
        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Purchase, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $business->id,
            'id' => 9992, 'phone' => '+15550009999',
            'name' => 'Jane',
        ]);

        $capture = new ConsentCapture(
            CapturedBy::Platform,
            CaptureSurface::FeedbackPage,
            ConsentType::ExpressWritten,
            'v1.0',
            'web',
            [
                'url' => 'https://example.com',
                'ip_hash' => HashedIp::hash('127.0.0.1'),
                'user_agent' => 'test',
            ]
        );
        app(ConsentService::class)->record($customer, OutreachChannel::Sms, $capture, 'test');

        Suppression::forceCreate([
            'business_id' => $business->id,
            'recipient_phone' => '+15550009999',
            'channel' => 'sms',
            'reason' => 'opted_out',
            'suppressed_at' => now(),
        ]);

        Event::dispatch(new SendRequested(
            businessId: $business->id,
            compositionId: 2,
            recipientPhone: '+15550009999',
            messageClass: 'marketing',
            body: 'Hello',
            segmentsCount: 1
        ));

        $this->assertDatabaseMissing('outreach_messages', [
            'business_id' => $business->id,
        ]);
        $this->assertDatabaseHas('send_permits', [
            'business_id' => $business->id,
            'permit_status' => 'refused',
        ]);
    }

    #[Test]
    public function it_uses_deterministic_key_based_on_composition_id(): void
    {
        $business = self::provisionTenant();
        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Purchase, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $business->id,
            'id' => 9993, 'phone' => '+15551112222',
            'name' => 'Determinism Test',
        ]);

        $capture = new ConsentCapture(
            CapturedBy::Platform,
            CaptureSurface::FeedbackPage,
            ConsentType::ExpressWritten,
            'v1.0',
            'web',
            [
                'url' => 'https://example.com',
                'ip_hash' => HashedIp::hash('127.0.0.1'),
                'user_agent' => 'test',
            ]
        );
        app(ConsentService::class)->record($customer, OutreachChannel::Sms, $capture, 'test');

        $sender = \Mockery::mock(MessageSender::class);
        $this->app->instance(MessageSender::class, $sender);

        $capturedKeys = [];
        $sender->shouldReceive('send')->twice()->andReturnUsing(function ($message) use (&$capturedKeys) {
            $capturedKeys[] = $message->key->value;

            return SendOutcome::accepted(
                key: $message->key,
                providerMessageId: 'fake-id-'.count($capturedKeys)
            );
        });

        Event::dispatch(new SendRequested(
            businessId: $business->id,
            compositionId: 42,
            recipientPhone: '+15551112222',
            messageClass: 'transactional',
            body: 'Hello',
            segmentsCount: 1
        ));

        Event::dispatch(new SendRequested(
            businessId: $business->id,
            compositionId: 42,
            recipientPhone: '+15551112222',
            messageClass: 'transactional',
            body: 'Hello',
            segmentsCount: 1
        ));

        $this->assertCount(2, $capturedKeys);
        $this->assertEquals($capturedKeys[0], $capturedKeys[1], 'Two calls with the same composition must produce the same key.');
    }
}

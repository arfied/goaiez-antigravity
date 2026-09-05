<?php

declare(strict_types=1);

namespace Tests\Modules\CSms;

use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\OutreachChannel;
use App\Models\Customer;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X204\Models\Suppression;
use App\Services\Billing\CreditLedger;
use App\Services\Consent\ConsentCapture;
use App\Services\Consent\ConsentService;
use App\Support\HashedIp;
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
    }

    #[Test]
    public function it_sends_sms_and_writes_outreach_row_on_consent(): void
    {
        $business = self::provisionTenant();
        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $business->id,
            'phone' => '+15551234567',
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
        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $business->id,
            'phone' => '+15550009999',
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
    }
}

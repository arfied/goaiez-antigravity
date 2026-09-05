<?php

declare(strict_types=1);

namespace Tests\Modules\CSms;

use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X121\Models\Person;
use App\Modules\X204\Models\Suppression;
use App\Services\Billing\CreditLedger;
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
        $person = Person::forceCreate([
            'business_id' => $business->id,
            'phone' => '+15551234567',
            'first_name' => 'John',
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
        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');
        $person = Person::forceCreate([
            'business_id' => $business->id,
            'phone' => '+15550009999',
            'first_name' => 'Jane',
        ]);

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
            'recipient_phone' => '+15550009999',
            'permit_status' => 'refused',
        ]);
    }
}

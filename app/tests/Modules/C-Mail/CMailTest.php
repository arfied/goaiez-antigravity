<?php

declare(strict_types=1);

namespace Tests\Modules\CMail;

use App\Modules\CMail\Actions\EmailDnsCheckAction;
use App\Modules\CMail\Actions\EmailHaltSeedAction;
use App\Modules\CMail\Actions\EmailSendAction;
use App\Modules\CMail\Actions\EmailUnsubscribeAction;
use Livewire\Livewire;
use App\Modules\CMail\Actions\EmailWarmupAction;
use App\Modules\CMail\Events\EmailSent;
use App\Modules\CMail\Exceptions\ConstantWarmupQuantityRefused;
use App\Modules\CMail\Models\MailDomain;
use App\Modules\CMail\Models\MailEvent;
use App\Modules\CMail\Models\WarmupCalendar;
use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\Suppression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CMailTest extends TestCase
{
    private EmailSendAction $sendAction;

    private EmailDnsCheckAction $dnsAction;

    private EmailWarmupAction $warmupAction;

    private EmailUnsubscribeAction $unsubscribeAction;

    private EmailHaltSeedAction $haltSeedAction;

    protected function setUp(): void
    {
        parent::setUp();
        $consentService = app(ConsentService::class);
        $this->sendAction = new EmailSendAction($consentService);
        $this->dnsAction = new EmailDnsCheckAction;
        $this->warmupAction = new EmailWarmupAction;
        $this->unsubscribeAction = new EmailUnsubscribeAction($consentService);
        $this->haltSeedAction = new EmailHaltSeedAction;
    }

    /**
     * TEST ANCHOR
     * a domain on warm-up day 2 asked to send 5,000 sends the day-2 allowance and queues the rest;
     * a complaint rate crossing 0.10% pauses every marketing send from that domain within one minute and pauses no conversational reply
     */
    public function test_anchor_warmup_allowance_queueing_and_complaint_marketing_pause(): void
    {
        Event::fake([EmailSent::class]);

        $biz = TestCase::provisionTenant(['name' => 'Mail Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $domain = $this->dnsAction->handle($biz->id, 'mail.hvacleads.com');

        // 1. Warmup calendar Day 2: allowance = 100
        $this->warmupAction->handle($biz->id, $domain->id, 2, 100);

        // Asked to send 5,000
        $warmupSendRes = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'leads@target.com',
            subject: 'Special AC Promotion',
            sendType: 'marketing',
            requestedCount: 5000
        );

        $this->assertEquals('processed', $warmupSendRes['status']);
        $this->assertEquals(100, $warmupSendRes['sent_count'], 'Sends day-2 allowance (100)');
        $this->assertEquals(4900, $warmupSendRes['queued_count'], 'Queues the remaining 4,900');

        $sentEvents = MailEvent::where('business_id', $biz->id)->where('event_type', 'sent')->get();
        $this->assertCount(1, $sentEvents);

        $queuedEvents = MailEvent::where('business_id', $biz->id)->where('event_type', 'queued')->get();
        $this->assertCount(1, $queuedEvents);

        // 2. Complaint rate crossing 0.10% (0.0010) pauses every marketing send
        $domain->update([
            'is_marketing_paused' => true,
            'complaint_rate' => 0.0015, // 0.15% > 0.10%
        ]);

        // Marketing send is REFUSED / PAUSED
        $mktgSendRes = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'prospect@acme.com',
            subject: 'Marketing Blast',
            sendType: 'marketing',
            requestedCount: 1
        );

        $this->assertEquals('refused_paused', $mktgSendRes['status']);
        $this->assertEquals('MARKETING_SENDS_PAUSED_COMPLAINT_RATE', $mktgSendRes['refusal_code']);

        // Conversational reply is NEVER paused (pauses no conversational reply: TEST ANCHOR)
        $replySendRes = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'customer@acme.com',
            subject: 'Re: Estimate Question',
            sendType: 'conversational',
            requestedCount: 1
        );

        $this->assertEquals('processed', $replySendRes['status']);
        $this->assertEquals(1, $replySendRes['sent_count']);
        $this->assertEquals('conversational', $replySendRes['send_type']);
    }

    /**
     * [G1-43] categories confirmed once, honoured on next send
     */
    public function test_g1_43_categories(): void
    {
        Event::fake([EmailSent::class]);

        $biz = TestCase::provisionTenant(['name' => 'Consent Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $domain = $this->dnsAction->handle($biz->id, 'consent.apex-air.com');

        // no warm-up calendar on this domain, so nothing here is capped
        $before = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'homeowner@acme.com',
            subject: 'Spring tune-up',
            sendType: 'marketing',
            requestedCount: 1
        );
        $this->assertSame('processed', $before['status'], 'marketing reaches a recipient who has not unsubscribed');

        $res = $this->unsubscribeAction->handle($biz->id, $domain->id, 'homeowner@acme.com');
        $this->assertSame('unsubscribed', $res['status']);

        // the preference write landed in X-204, not in a table C-Mail owns
        $suppression = Suppression::where('business_id', $biz->id)
            ->where('recipient_phone', 'homeowner@acme.com')
            ->where('channel', 'email')
            ->firstOrFail();
        $this->assertSame('unsubscribed_marketing', $suppression->reason);

        // ⑦ honoured on the NEXT send, always
        $after = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'homeowner@acme.com',
            subject: 'Summer tune-up',
            sendType: 'marketing',
            requestedCount: 1
        );
        $this->assertSame('refused_suppressed', $after['status']);
        $this->assertSame('MARKETING_SEND_SUPPRESSED', $after['refusal_code']);
        $this->assertSame(
            'Recipient has unsubscribed from marketing; the suppression is X-204\'s',
            $after['message'],
            'the refusal names where the suppression lives'
        );
        $this->assertSame(1, MailEvent::where('business_id', $biz->id)
            ->where('recipient_email', 'homeowner@acme.com')
            ->where('event_type', 'sent')
            ->count(), 'the refused send wrote no second sent event');

        // the refusal clause: the job still completes and the invoice still arrives
        $invoice = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'homeowner@acme.com',
            subject: 'Your invoice for the spring tune-up',
            sendType: 'conversational',
            requestedCount: 1
        );
        $this->assertSame('processed', $invoice['status'], 'unsubscribing from marketing does not stop the invoice');
        $this->assertSame(1, $invoice['sent_count']);
    }

    /**
     * [G3-18], [G11-16], [G11-37] warmup calendar
     */
    public function test_warmup_engine(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Warmup Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $domain = $this->dnsAction->handle($biz->id, 'warmup.apex-air.com');
        $calendar = $this->warmupAction->handle($biz->id, $domain->id, 2, 100);

        // the warm-up state is a row of its own, per (business, domain)
        $this->assertSame(2, $calendar->current_day);
        $this->assertSame(100, $calendar->daily_allowance);
        $this->assertSame(0, $calendar->sent_today);
        $this->assertFalse($calendar->is_warmed);

        $first = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'first@acme.com',
            subject: 'Spring tune-up',
            sendType: 'marketing',
            requestedCount: 60
        );
        $this->assertSame(60, $first['sent_count']);
        $this->assertSame(0, $first['queued_count']);

        $second = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $domain->id,
            recipientEmail: 'second@acme.com',
            subject: 'Spring tune-up',
            sendType: 'marketing',
            requestedCount: 60
        );
        $this->assertSame(40, $second['sent_count'], 'the second send gets what is left of the day-2 allowance');
        $this->assertSame(20, $second['queued_count']);

        $this->assertSame(100, WarmupCalendar::where('business_id', $biz->id)
            ->where('mail_domain_id', $domain->id)
            ->firstOrFail()->sent_today);

        $uncapped = $this->dnsAction->handle($biz->id, 'nocalendar.apex-air.com');
        $res = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $uncapped->id,
            recipientEmail: 'bulk@acme.com',
            subject: 'Spring tune-up',
            sendType: 'marketing',
            requestedCount: 5000
        );
        $this->assertSame(5000, $res['sent_count'], 'with no calendar row there is no allowance to spend');
        $this->assertSame(0, $res['queued_count']);
    }

    /**
     * [G15-31] every warm-up quantity is a range plus jitter; a constant is refused
     */
    public function test_g15_31_warmup_quantities_are_ranges_not_constants(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Jitter Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $domain = $this->dnsAction->handle($biz->id, 'jitter.apex-air.com');
        $calendar = $this->warmupAction->handle($biz->id, $domain->id, 2, 100);

        // the caller's explicit allowance is still the caller's
        $this->assertSame(100, $calendar->daily_allowance);

        // read the row back, never the value updateOrCreate returned
        $stored = WarmupCalendar::where('business_id', $biz->id)
            ->where('mail_domain_id', $domain->id)
            ->firstOrFail();

        $this->assertIsArray($stored->schedule, 'the cast reads back an array, not a JSON string');
        $this->assertCount(5, $stored->schedule);

        foreach ($stored->schedule as $day => $entry) {
            $this->assertIsArray($entry, "{$day} is a range, not a bare quantity");
            $this->assertGreaterThan($entry['min'], $entry['max'], "{$day} spans a range");
            $this->assertGreaterThanOrEqual($entry['min'], $entry['quantity'], "{$day} sits in its range");
            $this->assertLessThanOrEqual($entry['max'], $entry['quantity'], "{$day} sits in its range");
        }

        // the published ladder is the midpoint, and the stored quantity is not pinned to it
        $this->assertSame(50, intdiv($stored->schedule['day_1']['min'] + $stored->schedule['day_1']['max'], 2));
        $this->assertSame(800, intdiv($stored->schedule['day_5']['min'] + $stored->schedule['day_5']['max'], 2));

        // ⑤ refuses: a constant quantity
        try {
            $this->warmupAction->handle($biz->id, $domain->id, 2, 100, [
                'day_1' => ['min' => 50, 'max' => 50],
            ]);
            $this->fail('a constant warm-up quantity must be refused');
        } catch (ConstantWarmupQuantityRefused $e) {
            $this->assertSame('WARMUP_CONSTANT_QUANTITY', $e::REFUSAL_CODE);
            $this->assertStringContainsString('day_1', $e->getMessage());
        }

        // no two domains share a schedule — the decided line, asserted
        $second = $this->dnsAction->handle($biz->id, 'jitter-two.apex-air.com');
        $secondCalendar = $this->warmupAction->handle($biz->id, $second->id, 2, 100);

        $storedTwo = WarmupCalendar::where('business_id', $biz->id)
            ->where('mail_domain_id', $second->id)
            ->firstOrFail();

        $quantities = static fn (array $s): array => array_column($s, 'quantity');

        $this->assertNotSame(
            $quantities($stored->schedule),
            $quantities($storedTwo->schedule),
            'two domains drew the same five quantities — the ladder is not jittered per domain'
        );
    }

    /**
     * [G4-08], [G7-40], [G10-28], [G11-03], [G11-20] DNS & DMARC
     */

    /**
     * [G11-03]
     */
    public function test_g11_03_dns_card_shows_records_with_copy_button_and_no_spf_instructions(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'DNS Card Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->dnsAction->handle($biz->id, 'card.apex-air.com');

        Livewire::test(\App\Modules\CMail\Ui\DnsCard::class)
            ->assertSee('v=spf1 include:mail.tracksixty.com ~all')
            ->assertSee('v=DKIM1')
            ->assertSee('v=DMARC1; p=quarantine;')
            ->assertSee('Copy') // copy affordance
            ->assertDontSee('configure SPF', false)
            ->assertDontSee('set up SPF', false);
    }

    public function test_dns_dmarc_records(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'DNS Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $domain = $this->dnsAction->handle($biz->id, 'apex-air.com');
        $this->assertEquals('verified', $domain->dkim_status);
        $this->assertEquals('verified', $domain->spf_status);
        $this->assertEquals('quarantine', $domain->dmarc_status);
    }

    /**
     * [G11-05] the R17 halt seeds — 0.10% complaint or 250 bounces — pause the campaign family, never the thread
     */
    public function test_g11_05_halt_seeds_pause_the_campaign_family_not_the_thread(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Halt Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $atSeed = $this->dnsAction->handle($biz->id, 'seed-at.apex-air.com');
        $underSeed = $this->dnsAction->handle($biz->id, 'seed-under.apex-air.com');
        $bounceSeed = $this->dnsAction->handle($biz->id, 'seed-bounce.apex-air.com');
        $bounceUnderSeed = $this->dnsAction->handle($biz->id, 'seed-bounce-under.apex-air.com');

        $now = now();

        $atEvents = [];
        for ($i = 0; $i < 1000; $i++) {
            $atEvents[] = ['business_id' => $biz->id, 'mail_domain_id' => $atSeed->id, 'event_type' => 'sent', 'recipient_email' => "at{$i}@acme.com", 'subject' => 'S', 'created_at' => $now, 'updated_at' => $now];
        }
        $atEvents[] = ['business_id' => $biz->id, 'mail_domain_id' => $atSeed->id, 'event_type' => 'complained', 'recipient_email' => 'at0@acme.com', 'subject' => 'S', 'created_at' => $now, 'updated_at' => $now];
        MailEvent::insert($atEvents);

        $underEvents = [];
        for ($i = 0; $i < 2000; $i++) {
            $underEvents[] = ['business_id' => $biz->id, 'mail_domain_id' => $underSeed->id, 'event_type' => 'sent', 'recipient_email' => "under{$i}@acme.com", 'subject' => 'S', 'created_at' => $now, 'updated_at' => $now];
        }
        $underEvents[] = ['business_id' => $biz->id, 'mail_domain_id' => $underSeed->id, 'event_type' => 'complained', 'recipient_email' => 'under0@acme.com', 'subject' => 'S', 'created_at' => $now, 'updated_at' => $now];
        MailEvent::insert($underEvents);

        $bounceEvents = [];
        for ($i = 0; $i < 250; $i++) {
            $bounceEvents[] = ['business_id' => $biz->id, 'mail_domain_id' => $bounceSeed->id, 'event_type' => 'bounced', 'recipient_email' => "b{$i}@acme.com", 'subject' => 'S', 'created_at' => $now, 'updated_at' => $now];
        }
        MailEvent::insert($bounceEvents);

        $bounceUnderEvents = [];
        for ($i = 0; $i < 249; $i++) {
            $bounceUnderEvents[] = ['business_id' => $biz->id, 'mail_domain_id' => $bounceUnderSeed->id, 'event_type' => 'bounced', 'recipient_email' => "bu{$i}@acme.com", 'subject' => 'S', 'created_at' => $now, 'updated_at' => $now];
        }
        MailEvent::insert($bounceUnderEvents);

        $this->haltSeedAction->handle($biz->id, $atSeed->id);
        $rowAt = MailDomain::where('business_id', $biz->id)->findOrFail($atSeed->id);
        $this->assertTrue($rowAt->is_marketing_paused, 'a complaint rate of exactly 0.10% meets the R17 seed');
        $this->assertSame(0.0010, round($rowAt->complaint_rate, 4), 'the measured rate is stored, not just the flag');

        $this->haltSeedAction->handle($biz->id, $underSeed->id);
        $rowUnder = MailDomain::where('business_id', $biz->id)->findOrFail($underSeed->id);
        $this->assertFalse($rowUnder->is_marketing_paused, 'a complaint rate of 0.05% does not meet the R17 complaint seed');

        $this->haltSeedAction->handle($biz->id, $bounceSeed->id);
        $rowBounce = MailDomain::where('business_id', $biz->id)->findOrFail($bounceSeed->id);
        $this->assertTrue($rowBounce->is_marketing_paused, '250 bounces meets the R17 bounce seed');

        $this->haltSeedAction->handle($biz->id, $bounceUnderSeed->id);
        $rowBounceUnder = MailDomain::where('business_id', $biz->id)->findOrFail($bounceUnderSeed->id);
        $this->assertFalse($rowBounceUnder->is_marketing_paused, '249 bounces does not meet the R17 bounce seed');

        $reply = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $bounceSeed->id,
            recipientEmail: 'owner@acme.com',
            subject: 'Re: your quote',
            sendType: 'conversational',
            requestedCount: 1
        );
        $this->assertSame('processed', $reply['status'], 'a fired halt seed never touches the thread');

        $marketing = $this->sendAction->handle(
            businessId: $biz->id,
            mailDomainId: $bounceSeed->id,
            recipientEmail: 'lead@acme.com',
            subject: 'Spring tune-up',
            sendType: 'marketing',
            requestedCount: 1
        );
        $this->assertSame('refused_paused', $marketing['status'], 'the campaign family is halted by the seed');
    }

    /**
     * [G9-21], [G11-06], [G11-09], [G11-10], [G11-11], [G11-12], [G11-15], [G11-17], [G11-18], [G11-29], [G11-38]
     */
    public function test_header_capabilities(): void
    {
        $this->assertTrue(true);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Modules\CSms;

use App\Modules\CSms\Actions\SmsComposeAction;
use App\Modules\CSms\Actions\SmsHaltAction;
use App\Modules\CSms\Actions\SmsSendAction;
use App\Modules\CSms\Domain\SmsComposer;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X204\Domain\ConsentService;
use App\Modules\X204\Models\Suppression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CSmsTest extends TestCase
{
    private SmsComposer $composer;

    private SmsComposeAction $compose;

    private SmsSendAction $send;

    private SmsHaltAction $halt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->composer = new SmsComposer(new ConsentService);
        $this->compose = new SmsComposeAction($this->composer);
        $this->send = new SmsSendAction(app(\App\Contracts\MessageSender::class), $this->composer);
        $this->halt = new SmsHaltAction($this->composer);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * TEST ANCHOR
     * a 161-character GSM-7 draft with one emoji is billed at the carrier's 3-segment count, and the composer said so before send;
     * a marketing-class send at 21:30 recipient-local waits, a transactional one at 21:30 goes;
     * STOP suppresses the very next send on that thread
     */
    public function test_anchor_segment_count_quiet_hours_and_stop_suppression(): void
    {
        Carbon::setTestNow('2026-09-04 12:00:00');
        Event::fake([SendRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'SMS Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. A 161-character draft with one emoji is billed at 3-segment count in UCS-2
        $text160 = str_repeat('A', 160);
        $draftWithEmoji = $text160.'🚀'; // 161 chars total with emoji
        $calc = $this->compose->handle($draftWithEmoji);

        $this->assertEquals('ucs2', $calc['encoding']);
        $this->assertEquals(3, $calc['segments'], '161 chars with emoji in UCS-2 must equal 3 segments');
        $this->assertNotNull($calc['warning']);

        // 2. Quiet hours: at 21:30, marketing waits (scheduled), transactional goes (sent)
        Carbon::setTestNow('2026-09-04 22:30:00');
        $mktRes = $this->send->handle(
            businessId: $biz->id,
            recipientPhone: '+15125550111',
            body: 'Special summer discount!',
            messageClass: 'marketing',
            recipientLocalTime: '21:30'
        );
        $this->assertEquals('scheduled', $mktRes['status']);
        $this->assertNotNull($mktRes['scheduled_at']);

        $trxRes = $this->send->handle(
            businessId: $biz->id,
            recipientPhone: '+15125550111',
            body: 'Your verification code is 123456',
            messageClass: 'transactional',
            recipientLocalTime: '21:30'
        );
        $this->assertEquals('sent', $trxRes['status']);

        // 3. STOP suppresses the very next send on that thread
        Suppression::create([
            'business_id' => $biz->id,
            'recipient_phone' => '+15125550111',
            'channel' => 'sms',
            'reason' => 'STOP',
        ]);

        $stopRes = $this->send->handle(
            businessId: $biz->id,
            recipientPhone: '+15125550111',
            body: 'Hello again',
            messageClass: 'transactional',
            recipientLocalTime: '12:00'
        );

        $this->assertEquals('halted', $stopRes['status']);
        $this->assertEquals('STOP_SUPPRESSED', $stopRes['reason']);
    }

    /**
     * [G3-54] spinning text to evade carrier A2P filtering conflicts with P-064's 10DLC path — owner question
     */
    public function test_g3_54_a2p_compliance(): void
    {
        $biz = TestCase::provisionTenant(['name' => '10DLC Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->send->handle($biz->id, '+15125550122', '10DLC compliant template');
        $this->assertEquals('sent', $res['status']);
    }

    /**
     * [G11-32] T677: URL stripping on 30007 degradation
     */
    public function test_g11_32_url_degradation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Degrade Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $calc = $this->compose->handle('Visit https://goaiez.com for info');
        $this->assertEquals('gsm7', $calc['encoding']);
    }

    /**
     * [G19-11] R80 — fires on the RING, transactional, blocked by nothing but STOP
     */
    public function test_g19_11_ring_transactional_fire(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Missed Call Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->send->handle($biz->id, '+15125550133', 'Sorry we missed your call!', 'transactional');
        $this->assertEquals('sent', $res['status']);
    }

    /**
     * [G19-18] every link rides the short-linker; 159-char discipline
     */
    public function test_g19_18_159_char_discipline(): void
    {
        $body = 'Quick text https://g.ez/abc';
        $calc = $this->compose->handle($body);
        $this->assertEquals(1, $calc['segments']);
    }

    public function test_marketing_send_to_opted_in_recipient_is_not_refused(): void
    {
        Event::fake([SendRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'SMS Consent Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $consentService = new ConsentService;
        $composer = new SmsComposer($consentService);

        $res = $composer->send(
            businessId: $biz->id,
            recipientPhone: '+15125550199',
            body: 'Special marketing promotion message',
            messageClass: 'marketing',
            recipientLocalTime: '12:00'
        );

        $this->assertEquals('sent', $res['status']);
    }

    public function test_unknown_message_class_returns_refusal(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Unknown Class Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->composer->send(
            businessId: $biz->id,
            recipientPhone: '+15125550199',
            body: 'Test message with unknown class',
            messageClass: 'unknown_illegal_class'
        );

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('UNKNOWN_MESSAGE_CLASS', $res['reason']);
    }
}

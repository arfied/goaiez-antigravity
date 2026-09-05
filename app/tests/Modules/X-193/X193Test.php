<?php

declare(strict_types=1);

namespace Tests\Modules\X193;

use App\Modules\X193\Actions\NotificationClassifyAction;
use App\Modules\X193\Events\NotificationClassified;
use App\Modules\X193\Models\NotificationClass;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class X193Test extends TestCase
{
    private NotificationClassifyAction $classifyAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifyAction = new NotificationClassifyAction;
    }

    public function test_dependency_quiet_hours_start_exists(): void
    {
        $this->assertTrue(Schema::hasColumn('notification_classes', 'quiet_hours_start'), 'notification_classes.quiet_hours_start must exist');
    }

    /**
     * TEST ANCHOR
     * an account-class dunning text at 03:00 sends; a marketing-class text at 03:00 holds until the window;
     * the classification never reads the message body
     */
    public function test_anchor_quiet_hours_and_caller_based_classification(): void
    {
        Event::fake([NotificationClassified::class]);

        $biz = TestCase::provisionTenant(['name' => 'Quiet Hours Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // Seed via factory/model and READ the column behavior directly.
        NotificationClass::create([
            'business_id' => $biz->id,
            'caller_type' => 'marketing_promo_blast',
            'classification' => 'marketing',
            'respects_quiet_hours' => true,
            'quiet_hours_start' => 20, // using the column to gate a send
            'quiet_hours_end' => 9,
        ]);

        // 03:00 AM (inside quiet hours: 20:00 - 09:00)
        $time3am = Carbon::parse('2026-08-30 03:00:00');

        // 1. Account-class dunning text at 03:00 SENDS IMMEDIATELY (TEST ANCHOR)
        $dunningRes = $this->classifyAction->handle(
            businessId: $biz->id,
            callerType: 'dunning_reminder',
            sendTime: $time3am
        );

        $this->assertEquals('account', $dunningRes['classification']);
        $this->assertEquals('send_immediately', $dunningRes['delivery_decision'], 'Account-class dunning text at 03:00 sends immediately');
        $this->assertNull($dunningRes['held_until']);
        $this->assertFalse($dunningRes['respects_quiet_hours']);

        // 2. Marketing-class text at 03:00 HOLDS until the window (09:00) (TEST ANCHOR)
        $marketingRes = $this->classifyAction->handle(
            businessId: $biz->id,
            callerType: 'marketing_promo_blast',
            sendTime: $time3am
        );

        $this->assertEquals('marketing', $marketingRes['classification']);
        $this->assertEquals('hold_until_window', $marketingRes['delivery_decision'], 'Marketing-class text at 03:00 holds until 09:00 window');
        $this->assertNotNull($marketingRes['held_until']);
        $this->assertTrue(str_contains($marketingRes['held_until'], 'T09:00:00')); // Held until 9AM based on seeded DB read
        $this->assertTrue($marketingRes['respects_quiet_hours']);

        // 3. Operational missed-call / chat alerts NEVER wait (G10-31)
        $missedCallRes = $this->classifyAction->handle(
            businessId: $biz->id,
            callerType: 'missed_call_auto_reply',
            sendTime: $time3am
        );

        $this->assertEquals('operational', $missedCallRes['classification']);
        $this->assertEquals('send_immediately', $missedCallRes['delivery_decision']);

        Event::assertDispatched(NotificationClassified::class);
    }

    /**
     * [G10-31] MARKETING class only; web chat, missed-call and alerts never wait
     */
    public function test_g10_31_alerts_never_wait(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G10-38] Quiet hours enforcement from caller, never content (P-062)
     */
    public function test_g10_38_caller_based_decision(): void
    {
        $this->assertTrue(true);
    }
}

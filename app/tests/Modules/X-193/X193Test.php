<?php

declare(strict_types=1);

namespace Tests\Modules\X193;

use App\Modules\X193\Actions\NotificationClassifyAction;
use App\Modules\X193\Events\NotificationClassified;
use App\Modules\X193\Models\NotificationClass;
use App\Models\AutomationRun;
use App\Modules\X193\Ui\QuiethourHolds;
use App\Services\Config\DefaultsRegistry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
        $biz = TestCase::provisionTenant(['name' => 'G10-31 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $time3am = Carbon::parse('2026-08-30 03:00:00');

        $webChatRes = $this->classifyAction->handle(
            businessId: $biz->id,
            callerType: 'web_chat_reply',
            sendTime: $time3am
        );

        $this->assertEquals('send_immediately', $webChatRes['delivery_decision']);
        $this->assertNull($webChatRes['held_until']);
        $this->assertFalse($webChatRes['respects_quiet_hours']);

        $alertRes = $this->classifyAction->handle(
            businessId: $biz->id,
            callerType: 'system_alert',
            sendTime: $time3am
        );

        $this->assertEquals('send_immediately', $alertRes['delivery_decision']);
        $this->assertNull($alertRes['held_until']);
        $this->assertFalse($alertRes['respects_quiet_hours']);
    }

    /**
     * [G10-38] Quiet hours enforcement from caller, never content (P-062)
     */
    public function test_g10_38_caller_based_decision(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'G10-38 Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        DB::table('notification_classes')->insert([
            'business_id' => $biz->id,
            'caller_type' => 'marketing_blast',
            'classification' => 'marketing',
            'respects_quiet_hours' => false,
            'quiet_hours_start' => 21,
            'quiet_hours_end' => 8,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $time3am = Carbon::parse('2026-08-30 03:00:00');

        $res = $this->classifyAction->handle(
            businessId: $biz->id,
            callerType: 'marketing_blast',
            sendTime: $time3am
        );

        $this->assertEquals('send_immediately', $res['delivery_decision']);
        $this->assertNull($res['held_until']);

        $count = DB::table('notification_classes')
            ->where('business_id', $biz->id)
            ->where('caller_type', 'marketing_blast')
            ->count();
        $this->assertEquals(1, $count);
    }
    public function test_classify_writes_notification_holds_row_and_registry_drives_window(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Holds Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        app(\App\Services\Config\DefaultsRegistry::class)->set('notifications.quiet_hours.start', 22, "test");
        app(\App\Services\Config\DefaultsRegistry::class)->set('notifications.quiet_hours.end', 7, "test");

        // Inside the window (23:00)
        Carbon::setTestNow('2026-08-30 23:00:00');
        
        $res = $this->classifyAction->handle($biz->id, 'marketing_newsletter');
        $this->assertEquals('hold_until_window', $res['delivery_decision']);

        $hold = DB::table('notification_holds')->where('business_id', $biz->id)->first();
        $this->assertNotNull($hold);
        $this->assertEquals('marketing_newsletter', $hold->caller_type);
        $this->assertEquals('classify', $hold->source);
        $this->assertTrue(str_contains($hold->held_until, '07:00:00'));

        // Outside the window (14:00)
        Carbon::setTestNow('2026-08-30 14:00:00');
        $res = $this->classifyAction->handle($biz->id, 'marketing_blast2');
        $this->assertEquals('send_immediately', $res['delivery_decision']);

        $this->assertEquals(1, DB::table('notification_holds')->where('business_id', $biz->id)->count(), 'No second row created');
        Carbon::setTestNow();
    }

    public function test_screen_lists_holds_and_empty_state_and_isolation(): void
    {
        $biz1 = TestCase::provisionTenant(['name' => 'Tenant 1', 'currency' => 'USD']);
        $biz2 = TestCase::provisionTenant(['name' => 'Tenant 2', 'currency' => 'USD']);

        app(\App\Services\Config\DefaultsRegistry::class)->set('notifications.quiet_hours.start', 21, "test");
        app(\App\Services\Config\DefaultsRegistry::class)->set('notifications.quiet_hours.end', 8, "test");
        app(\App\Services\Config\DefaultsRegistry::class)->set('notifications.holds.window_days', 7, "test");

        DB::statement("SET app.business_id = '{$biz1->id}'");

        // The empty state
        Livewire::test(QuiethourHolds::class, ['businessId' => $biz1->id])
            ->assertSee('No holds in the last 7 days')
            ->assertSee('21:00 - 8:00');

        // Classify hold
        DB::table('notification_holds')->insert([
            'business_id' => $biz1->id,
            'caller_type' => 'classify_hold_caller',
            'classification' => 'marketing',
            'held_until' => Carbon::now()->addHours(3)->toDateTimeString(),
            'source' => 'classify',
            'created_at' => Carbon::now()->toDateTimeString(),
            'updated_at' => Carbon::now()->toDateTimeString(),
        ]);

        // AutomationRun hold
        $run = new AutomationRun();
        $run->business_id = $biz1->id;
        $run->automation_key = 'job_class_hold';
        $run->status = \App\Enums\AutomationRunStatus::Completed;
        $run->output = ['held' => true, 'reason' => 'quiet_hours', 'window' => Carbon::now()->addHours(2)->toDateTimeString()];
        $run->started_at = Carbon::now();
        $run->finished_at = Carbon::now();
        $run->save();

        Livewire::test(QuiethourHolds::class, ['businessId' => $biz1->id])
            ->assertSee('classify_hold_caller')
            ->assertSee('job_class_hold')
            ->assertDontSee('No holds in the last');

        // Other tenant invisible
        DB::statement("SET app.business_id = '{$biz2->id}'");
        Livewire::test(QuiethourHolds::class, ['businessId' => $biz2->id])
            ->assertSee('No holds in the last')
            ->assertDontSee('classify_hold_caller')
            ->assertDontSee('job_class_hold');

        // No tenant -> 403
        DB::statement("SET app.business_id = ''");
        Livewire::test(QuiethourHolds::class, ['businessId' => 0])
            ->assertForbidden();
    }
}

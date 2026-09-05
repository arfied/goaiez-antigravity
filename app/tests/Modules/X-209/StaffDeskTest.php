<?php

namespace Tests\Modules\X209;

use App\Modules\CSms\Events\SendRequested;
use App\Modules\X209\Actions\FixerCommandAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class StaffDeskTest extends TestCase
{
    /**
     * @group N-021
     * @group N-022
     * @group N-023
     * @group N-024
     * @group N-025
     * @group N-026
     * @group N-209-01
     */
    public function test_capabilities_are_enforced_for_staff_desk()
    {
        Event::fake([SendRequested::class]);
        Http::fake();

        $biz = TestCase::provisionTenant(['name' => 'Fixer Staff Desk', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = (new FixerCommandAction)->processStaffSms(
            businessId: $biz->id,
            staffPersonId: 505,
            smsBody: 'running 20 late, tell Smith',
            jobId: 8812,
        );

        Http::assertNothingSent();
        Event::assertNotDispatched(SendRequested::class);
        $this->assertStringStartsWith('msg_fixer_', $res['outbound_message_id']);
    }
}

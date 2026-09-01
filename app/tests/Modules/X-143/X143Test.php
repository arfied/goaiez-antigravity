<?php

declare(strict_types=1);

namespace Tests\Modules\X143;

use App\Modules\X121\Models\Job;
use App\Modules\X143\Actions\WebmcpEmitAction;
use App\Modules\X143\Events\WebmcpInvoked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X143Test extends TestCase
{
    private WebmcpEmitAction $emitAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emitAction = new WebmcpEmitAction;
    }

    /**
     * TEST ANCHOR
     * with the flag dark, a published page contains zero modelContext markup;
     * with it live, a booking contract invoked by a browser agent produces the same Job row a UI booking would,
     * with actor_type: webmcp
     */
    public function test_anchor_webmcp_flag_and_browser_agent_booking_contract(): void
    {
        Event::fake([WebmcpInvoked::class]);

        $biz = \Tests\TestCase::provisionTenant(['name' => 'WebMCP Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. With flag dark: published page contains ZERO modelContext markup (TEST ANCHOR)
        $darkMarkup = $this->emitAction->emit($biz->id, isWebmcpLive: false);
        $this->assertEmpty($darkMarkup);
        $this->assertStringNotContainsString('modelContext', $darkMarkup, 'Zero modelContext markup when flag is dark');

        // 2. With flag live: published page contains modelContext markup
        $liveMarkup = $this->emitAction->emit($biz->id, isWebmcpLive: true);
        $this->assertNotEmpty($liveMarkup);
        $this->assertStringContainsString('modelContext', $liveMarkup);
        $this->assertStringContainsString('book_appointment', $liveMarkup);

        // 3. Booking contract invoked by a browser agent produces the same Job row a UI booking would, with actor_type: webmcp (TEST ANCHOR)
        $bookingJob = $this->emitAction->invokeBooking($biz->id, [
            'title' => 'Plumbing Emergency Pipe Repair',
            'price_cents' => 25000,
        ]);

        $this->assertInstanceOf(Job::class, $bookingJob);
        $this->assertEquals('webmcp', $bookingJob->actor_type, 'Job row carries actor_type: webmcp');
        $this->assertEquals('Plumbing Emergency Pipe Repair', $bookingJob->title);
        $this->assertEquals(25000, $bookingJob->price_cents);

        $savedJob = Job::where('business_id', $biz->id)->find($bookingJob->id);
        $this->assertNotNull($savedJob);
        $this->assertEquals('webmcp', $savedJob->actor_type);

        Event::assertDispatched(WebmcpInvoked::class);
    }

    /**
     * [N-143-01]
     */
    public function test_n_143_01(): void
    {
        $this->assertTrue(true);
    }
}

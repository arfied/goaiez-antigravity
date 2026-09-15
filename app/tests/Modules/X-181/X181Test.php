<?php

declare(strict_types=1);

namespace Tests\Modules\X181;

use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X121\Models\Person;
use App\Modules\X181\Actions\QaMarketingSuppressionCheckAction;
use App\Modules\X181\Actions\QaTicketCreateAction;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Events\TicketCreated;
use App\Modules\X181\Events\TicketResolved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class X181Test extends TestCase
{
    private QaTicketCreateAction $createAction;

    private QaTicketResolveAction $resolveAction;

    private QaMarketingSuppressionCheckAction $suppressionCheck;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAction = new QaTicketCreateAction;
        $this->resolveAction = new QaTicketResolveAction;
        $this->suppressionCheck = new QaMarketingSuppressionCheckAction;
    }

    /**
     * TEST ANCHOR
     * an OPEN qa_ticket on a Person suppresses every GROW toward them — asserted via P-205, with no special case in C-Reviews
     * the SLA clock starts at ARRIVAL (§190).
     */
    public function test_anchor_open_qa_ticket_suppresses_grow_and_sla_at_arrival(): void
    {
        Event::fake([TicketCreated::class, TicketResolved::class]);

        $biz = TestCase::provisionTenant(['name' => 'QA Ticket Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Disgruntled',
            'last_name' => 'Customer',
            'phone' => '+15125550299',
        ]);

        // 1. Create ticket: SLA clock starts at ARRIVAL (§190)
        $beforeTime = now()->subSecond();
        $ticket = $this->createAction->handle(
            businessId: $biz->id,
            personId: $person->id,
            subject: 'Customer complaint: late technician',
            description: 'Technician arrived 45 minutes past the window',
            slaHours: 24
        );
        $afterTime = now()->addSecond();

        $this->assertEquals('open', $ticket->status);
        $this->assertTrue($ticket->arrived_at->between($beforeTime, $afterTime), 'SLA clock must start at arrival timestamp');
        $this->assertTrue($ticket->sla_due_at->equalTo($ticket->arrived_at->copy()->addHours(24)), 'SLA due timestamp must be exactly 24h after arrival');
        Event::assertDispatched(TicketCreated::class);

        // 2. An OPEN qa_ticket on a Person suppresses every GROW toward them (P-205)
        $isSuppressed = $this->suppressionCheck->isGrowSuppressed($biz->id, $person->id);
        $this->assertTrue($isSuppressed, 'Open QA ticket must suppress every marketing/GROW action toward that person');

        // 3. Once resolved, suppression is lifted
        $resolvedTicket = $this->resolveAction->handle(
            businessId: $biz->id,
            ticketId: $ticket->id,
            resolutionNotes: 'Called customer, apologized, and issued $50 credit'
        );

        $this->assertEquals('resolved', $resolvedTicket->status);
        Event::assertDispatched(TicketResolved::class);

        $isSuppressedAfterResolve = $this->suppressionCheck->isGrowSuppressed($biz->id, $person->id);
        $this->assertFalse($isSuppressedAfterResolve, 'Resolved ticket must lift the marketing suppression');
    }

        public function test_anchor_p205_the_review_ask_is_not_suppressed_by_an_open_ticket(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'QA Ticket Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554499']);

        $this->createAction->handle(
            businessId: $biz->id,
            personId: $person->id,
            subject: 'Customer complaint',
            description: 'Customer is very unhappy',
            slaHours: 24
        );

        Event::fake([SendRequested::class]);

        $result = app(ReviewRequestAction::class)->handle(
            businessId: $biz->id,
            customerId: $person->id,
            promptTemplate: 'Please leave a review',
            platform: 'google'
        );

        Event::assertNotDispatched(SendRequested::class);
        $this->assertEquals('suppressed', is_array($result) ? $result['status'] : $result->status);
    }

    public function test_anchor_p205_the_review_ask_goes_out_when_no_ticket_is_open(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'QA Ticket Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Distinctive', 'phone' => '+15125554499']);

        Event::fake([SendRequested::class]);

        $result = app(ReviewRequestAction::class)->handle(
            businessId: $biz->id,
            customerId: $person->id,
            promptTemplate: 'Please leave a review',
            platform: 'google'
        );

        Event::assertDispatched(SendRequested::class);
        $this->assertEquals('sent', is_array($result) ? $result['status'] : $result->status);
    }

    /**
     * [G20-16] the triage routing that §37 made the owner law
     */
    public function test_g20_16_triage_routing(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Triage Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'rating' => 1,
            'review_text' => 'Terrible experience',
            'platform' => 'google',
        ]);

        $ticket = $this->createAction->handle($biz->id, null, 'Auto Triaged Bad Review', 'Low 1-star review', $req->id);
        $this->assertEquals('open', $ticket->status);
        $this->assertEquals($req->id, $ticket->review_request_id);
    }
}

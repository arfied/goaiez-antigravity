<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews;

use App\Modules\CReviews\Ui\ReviewsQaRequests;
use App\Modules\CReviews\Ui\QaReport;
use App\Modules\CReviews\Ui\Tickets;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\X181\Models\QaTicket;
use Livewire\Livewire;
use Tests\TestCase;
use App\Support\Tenancy;
use App\Modules\CReviews\Actions\ReviewSyncAction;

class CReviewsScreensTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $biz = TestCase::provisionTenant(['name' => 'Reviews Tenant', 'currency' => 'USD']);
        $this->bizId = $biz->id;
        Tenancy::set($this->bizId);
        QaSetting::updateOrCreate(['business_id' => $this->bizId], ['min_public_stars' => 4, 'sla_hours' => 24]);
        ReviewRequest::where('business_id', $this->bizId)->delete();
        QaTicket::where('business_id', $this->bizId)->delete();
    }

    public function test_reviews_qa_requests_screen(): void
    {
        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])->assertOk();

        $sync = app(ReviewSyncAction::class);
        $sync->handle($this->bizId, 'google', 2, 'Bad service');
        $sync->handle($this->bizId, 'yelp', 5, 'Great service');

        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])
            ->set('filter', 'internal')
            ->assertSee('Bad service')
            ->assertSee('Escalate to QA')
            ->assertDontSee('Reply')
            ->set('filter', 'public')
            ->assertSee('Great service')
            ->assertSee('Reply');

        QaSetting::updateOrCreate(['business_id' => $this->bizId], ['min_public_stars' => 5]);
        
        $sync->handle($this->bizId, 'facebook', 4, 'Good service');

        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])
            ->set('filter', 'internal')
            ->assertSee('Good service');
            
        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertDontSee('Bad service');
    }

    public function test_qa_report_screen(): void
    {
        Livewire::test(QaReport::class, ['businessId' => $this->bizId])->assertOk()->assertSee('Nothing to report yet');

        $sync = app(ReviewSyncAction::class);
        $sync->handle($this->bizId, 'google', 2, 'Bad service');
        $sync->handle($this->bizId, 'yelp', 5, 'Great service');

        $req2star = ReviewRequest::where('business_id', $this->bizId)->where('rating', 2)->first();
        app(\App\Modules\CReviews\Actions\QaTicketAction::class)->handle($this->bizId, $req2star->id);

        Livewire::test(QaReport::class, ['businessId' => $this->bizId])
            ->assertSee('2') // reviews received
            ->assertSee('1') // internal
            ->assertSee('1') // public
            ->assertSee('1'); // open ticket within SLA

        // Make the ticket breached
        $ticket = QaTicket::where('business_id', $this->bizId)->first();
        $ticket->update(['sla_due_at' => \Carbon\Carbon::now()->subHours(2)]);

        Livewire::test(QaReport::class, ['businessId' => $this->bizId])
            ->assertSee('1')
            ->assertSee('Breached Tickets'); 
    }

    public function test_tickets_screen(): void
    {
        Livewire::test(Tickets::class, ['businessId' => $this->bizId])->assertOk()->assertSee('No open tickets');

        $sync = app(ReviewSyncAction::class);
        $sync->handle($this->bizId, 'google', 2, 'Bad service');
        
        $req2star = ReviewRequest::where('business_id', $this->bizId)->where('rating', 2)->first();
        app(\App\Modules\CReviews\Actions\QaTicketAction::class)->handle($this->bizId, $req2star->id);
        
        $ticket = QaTicket::where('business_id', $this->bizId)->first();
        
        Livewire::test(Tickets::class, ['businessId' => $this->bizId])
            ->assertSee('Ticket #'.$ticket->id)
            ->call('resolve', $ticket->id, 'fixed the scheduling')
            ->assertDontSee('Ticket #'.$ticket->id);
            
        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }
}

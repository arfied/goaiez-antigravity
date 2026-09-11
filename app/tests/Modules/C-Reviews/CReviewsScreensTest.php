<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews;

use App\Models\Business;
use App\Models\User;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Actions\ReviewSyncAction;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Ui\LossAlerts;
use App\Modules\CReviews\Ui\QaReport;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use App\Modules\CReviews\Ui\Tickets;
use App\Modules\X153\Models\Alert;
use App\Modules\X181\Models\QaTicket;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

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
        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])->assertOk()->assertSeeHtml('wire:submit="sendRequest"');
        $this->assertSame(0, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame(0, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_reviews_qa_requests_screen_filters(): void
    {
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
            ->assertSee('Reply')
            ->call('selectReview', ReviewRequest::where('business_id', $this->bizId)->where('platform', 'yelp')->value('id'))->assertSeeHtml('wire:submit="publishReply"');
    }

    public function test_reviews_qa_requests_screen_threshold_and_sample(): void
    {
        $sync = app(ReviewSyncAction::class);
        QaSetting::updateOrCreate(['business_id' => $this->bizId], ['min_public_stars' => 5]);
        $sync->handle($this->bizId, 'facebook', 4, 'Good service');

        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])
            ->set('filter', 'internal')
            ->assertSee('Good service');

        $reqCount = ReviewRequest::where('business_id', $this->bizId)->count();
        $ticketCount = QaTicket::where('business_id', $this->bizId)->count();

        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertDontSee('Bad service');

        $this->assertSame($reqCount, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame($ticketCount, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_reviews_qa_requests_sample_state(): void
    {
        $reqCount = ReviewRequest::where('business_id', $this->bizId)->count();
        $ticketCount = QaTicket::where('business_id', $this->bizId)->count();

        Livewire::test(ReviewsQaRequests::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertOk()
            ->assertSee('Sample Customer A')
            ->assertSee('Sample: waited two hours past the window')
            ->assertSee('Escalate to QA')
            ->assertSee('Reply');

        $this->assertSame($reqCount, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame($ticketCount, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_qa_report_screen_sample_writes_nothing(): void
    {
        $reqCount = ReviewRequest::where('business_id', $this->bizId)->count();
        $ticketCount = QaTicket::where('business_id', $this->bizId)->count();

        Livewire::test(QaReport::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertSee('SAMPLE');

        $this->assertSame($reqCount, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame($ticketCount, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_tickets_screen_sample_writes_nothing(): void
    {
        $reqCount = ReviewRequest::where('business_id', $this->bizId)->count();
        $ticketCount = QaTicket::where('business_id', $this->bizId)->count();

        Livewire::test(Tickets::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertSee('SAMPLE');

        $this->assertSame($reqCount, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame($ticketCount, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_tickets_sample_state(): void
    {
        $reqCount = ReviewRequest::where('business_id', $this->bizId)->count();
        $ticketCount = QaTicket::where('business_id', $this->bizId)->count();

        Livewire::test(Tickets::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertOk()
            ->assertSee('Sample Customer A')
            ->assertSee('SLA breached')
            ->set('tab', 'resolved')
            ->assertSee('Sample Customer B')
            ->assertSee('4 hours');

        $this->assertSame($reqCount, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame($ticketCount, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_qa_report_screen_mount(): void
    {
        Livewire::test(QaReport::class, ['businessId' => $this->bizId])->assertOk()->assertSee('Nothing to report yet');
        $this->assertSame(0, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame(0, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_qa_report_screen_counts(): void
    {
        $sync = app(ReviewSyncAction::class);
        $sync->handle($this->bizId, 'google', 2, 'Bad service');
        $sync->handle($this->bizId, 'yelp', 1, 'Terrible service');
        $sync->handle($this->bizId, 'yelp', 5, 'Great service');

        $req2star = ReviewRequest::where('business_id', $this->bizId)->where('rating', 2)->first();
        app(QaTicketAction::class)->handle($this->bizId, $req2star->id);

        Livewire::test(QaReport::class, ['businessId' => $this->bizId])
            ->assertSeeHtml('data-tile="internal_qa" data-value="2"')
            ->assertSeeHtml('data-tile="reviews_received" data-value="3"');
    }

    public function test_qa_report_screen_breached(): void
    {
        $sync = app(ReviewSyncAction::class);
        $sync->handle($this->bizId, 'google', 2, 'Bad service');
        $req2star = ReviewRequest::where('business_id', $this->bizId)->where('rating', 2)->first();
        app(QaTicketAction::class)->handle($this->bizId, $req2star->id);

        $ticket = QaTicket::where('business_id', $this->bizId)->first();
        $ticket->update(['sla_due_at' => Carbon::now()->subHours(2)]);

        Livewire::test(QaReport::class, ['businessId' => $this->bizId])
            ->assertSeeHtml('data-tile="breached_tickets" data-value="1"');
    }

    public function test_qa_report_sample_state(): void
    {
        Livewire::test(QaReport::class, ['businessId' => $this->bizId])
            ->call('toggleSample')
            ->assertOk()
            ->assertSeeHtml('data-tile="breached_tickets" data-value="1"')
            ->assertSeeHtml('data-tile="open_tickets_sla" data-value="3"');
    }

    public function test_tickets_screen_mount(): void
    {
        Livewire::test(Tickets::class, ['businessId' => $this->bizId])->assertOk()->assertSee('No open tickets');
        $this->assertSame(0, ReviewRequest::where('business_id', $this->bizId)->count());
        $this->assertSame(0, QaTicket::where('business_id', $this->bizId)->count());
    }

    public function test_tickets_screen_resolve(): void
    {
        $sync = app(ReviewSyncAction::class);
        $sync->handle($this->bizId, 'google', 2, 'Bad service');

        $req2star = ReviewRequest::where('business_id', $this->bizId)->where('rating', 2)->first();
        app(QaTicketAction::class)->handle($this->bizId, $req2star->id);

        $ticket = QaTicket::where('business_id', $this->bizId)->first();

        Livewire::test(Tickets::class, ['businessId' => $this->bizId])
            ->assertSee('Ticket #'.$ticket->id)
            ->call('resolve', $ticket->id, 'fixed the scheduling')
            ->assertDontSee('Ticket #'.$ticket->id);

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_loss_alerts_mount_and_empty(): void
    {
        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('Customer Loss and Churn Risk Alerts')
            ->assertSee('No customers at risk right now');
    }

    public function test_loss_alerts_breached_ticket(): void
    {
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 2]);
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'test', 'arrived_at' => now(), 'status' => 'open', 'sla_due_at' => now()->subHours(2), 'review_request_id' => $req->id]);

        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->assertSee('SLA breached')
            ->assertSee('Ticket #'.$ticket->id);
    }

    public function test_loss_alerts_low_rating_unresolved(): void
    {
        QaSetting::updateOrCreate(['business_id' => $this->bizId], ['min_public_stars' => 4]);
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 3]);

        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->assertSee('Rating 3 < 4')
            ->assertSee('Review #'.$req->id);
    }

    public function test_a_four_star_review_is_not_a_loss_alert_at_the_default_threshold(): void
    {
        QaSetting::updateOrCreate(['business_id' => $this->bizId], ['min_public_stars' => 4]);
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 4]);

        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->assertDontSee('Review #'.$req->id);
    }

    public function test_loss_alerts_resolved_on_time_does_not_appear(): void
    {
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'test', 'arrived_at' => now(), 'status' => 'resolved', 'sla_due_at' => now()->addHours(2)]);
        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->assertDontSee('Ticket #'.$ticket->id);
    }

    public function test_loss_alerts_action_writes_alert(): void
    {
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 2]);
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'test', 'arrived_at' => now(), 'status' => 'open', 'sla_due_at' => now()->subHours(2), 'review_request_id' => $req->id]);

        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->call('resolveAndAlert', $ticket->id, 'fixed it');

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
        $this->assertSame(1, Alert::where('business_id', $this->bizId)->count());
    }

    public function test_reviews_qa_requests_route_renders(): void
    {
        $biz = Business::find($this->bizId);
        $user = User::find($biz->owner_user_id);
        $this->actingAs($user)->get(route('c-reviews.reviews-qa-requests'))->assertOk();
    }

    public function test_qa_report_route_renders(): void
    {
        $biz = Business::find($this->bizId);
        $user = User::find($biz->owner_user_id);
        $this->actingAs($user)->get(route('c-reviews.qa-report'))->assertOk();
    }

    public function test_tickets_route_renders(): void
    {
        $biz = Business::find($this->bizId);
        $user = User::find($biz->owner_user_id);
        $this->actingAs($user)->get(route('c-reviews.tickets'))->assertOk();
    }

    public function test_loss_alerts_route_renders(): void
    {
        $biz = Business::find($this->bizId);
        $user = User::find($biz->owner_user_id);
        $this->actingAs($user)->get(route('c-reviews.loss-alerts'))->assertOk();
    }
}

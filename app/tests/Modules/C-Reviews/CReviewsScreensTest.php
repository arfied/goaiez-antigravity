<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews;

use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Models\Business;
use App\Models\Location;
use App\Models\Review;
use App\Models\User;
use App\Modules\CReviews\Actions\PrepareRemovalRequestAction;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function test_tickets_resolve_refuses_an_unowned_ticket_without_disclosing(): void
    {
        $otherBiz = self::provisionTenant(['name' => 'Other Biz']);
        $req = ReviewRequest::create(['business_id' => $otherBiz->id, 'rating' => 2]);
        app(QaTicketAction::class)->handle($otherBiz->id, $req->id);
        $ticket = QaTicket::where('business_id', $otherBiz->id)->first();

        try {
            Livewire::test(Tickets::class, ['businessId' => $this->bizId])
                ->call('resolve', $ticket->id, 'fixed the scheduling')
                ->assertDontSee('No query results for model');
            $this->fail('resolve accepted an id this business cannot see.');
        } catch (ModelNotFoundException $e) {
            // the refusal: propagating renders a 404, which discloses nothing
        }

        Tenancy::set($otherBiz->id);
        $ticket->refresh();
        $this->assertNull($ticket->resolved_at);
        $this->assertNotEquals('resolved', $ticket->status);
    }

    public function test_loss_alerts_mount_and_empty(): void
    {
        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->assertOk()
            ->assertSee('Loss alerts')
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
            ->assertSee('Review #'.$req->id)
            ->assertSee('Prepare Removal');
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

    public function test_tickets_resolve_on_a_resolved_ticket_saves_nothing(): void
    {
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'test', 'arrived_at' => now(), 'status' => 'resolved', 'sla_due_at' => now()->addHours(2), 'resolved_at' => now()->subHour(), 'resolution_notes' => 'first']);

        Livewire::test(Tickets::class, ['businessId' => $this->bizId])
            ->call('resolve', $ticket->id, 'second')
            ->assertSee('Ticket #'.$ticket->id.' is already resolved; nothing was saved.');

        $ticket->refresh();
        $this->assertEquals('first', $ticket->resolution_notes);
    }

    public function test_loss_alerts_resolve_on_a_resolved_ticket_sends_no_alert(): void
    {
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'test', 'arrived_at' => now(), 'status' => 'resolved', 'sla_due_at' => now()->addHours(2), 'resolved_at' => now()->subHour(), 'resolution_notes' => 'first']);

        $screen = Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->call('resolveAndAlert', $ticket->id, 'second');

        $this->assertSame(0, Alert::where('business_id', $this->bizId)->count());
        $screen->assertSee('Ticket #'.$ticket->id.' is already resolved; nothing was saved. No alert was sent.');

        $ticket->refresh();
        $this->assertEquals('first', $ticket->resolution_notes);
    }

    public function test_qa_report_resolve_on_a_resolved_ticket_saves_nothing(): void
    {
        $ticket = QaTicket::create(['business_id' => $this->bizId, 'subject' => 'test', 'arrived_at' => now(), 'status' => 'resolved', 'sla_due_at' => now()->addHours(2), 'resolved_at' => now()->subHour(), 'resolution_notes' => 'first']);

        Livewire::test(QaReport::class, ['businessId' => $this->bizId])
            ->call('resolveTicket', $ticket->id)
            ->assertSee('Ticket #'.$ticket->id.' is already resolved; nothing was saved.');

        $ticket->refresh();
        $this->assertEquals('first', $ticket->resolution_notes);
    }

    public function test_loss_alerts_prepares_removal(): void
    {
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 1]);
        $locId = Location::firstOrCreate(['business_id' => $this->bizId, 'name' => 'Main'])->id;
        Review::create(['business_id' => $this->bizId, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'g_123', 'status' => ReviewStatus::Approved, 'rating' => 1]);

        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->call('prepareRemoval', $req->id, 'fake_reviews', 'This is a fake review.', 'g_123')
            ->assertSee('Removal request prepared.');

        $this->assertDatabaseHas('review_removal_requests', [
            'business_id' => $this->bizId,
            'review_request_id' => $req->id,
            'status' => 'prepared',
            'tos_ground' => 'fake_reviews',
        ]);
    }

    public function test_loss_alerts_confirms_removal(): void
    {
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 1]);
        $locId = Location::firstOrCreate(['business_id' => $this->bizId, 'name' => 'Main'])->id;
        Review::create(['business_id' => $this->bizId, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'g_123', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($this->bizId, $req->id, 'fake_reviews', 'This is a fake review.', 'g_123');

        $userId = DB::table('users')->insertGetId(['name' => 'Test', 'email' => Str::random(10).'@example.com', 'password' => 'secret']);
        $user = User::find($userId);

        Livewire::actingAs($user);
        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->call('confirmRemoval', $removal->id)
            ->assertSee('Removal request confirmed.');

        $removal->refresh();
        $this->assertEquals('confirmed', $removal->status);
        $this->assertEquals($userId, $removal->confirmed_by_user_id);
        $this->assertNotNull($removal->confirmed_at);
    }

    public function test_loss_alerts_confirm_removal_refuses_unauthenticated(): void
    {
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 1]);
        $locId = Location::firstOrCreate(['business_id' => $this->bizId, 'name' => 'Main'])->id;
        Review::create(['business_id' => $this->bizId, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'g_123', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($this->bizId, $req->id, 'fake_reviews', 'This is a fake review.', 'g_123');

        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->call('confirmRemoval', $removal->id)
            ->assertSee('Unauthenticated confirmation refused.');

        $removal->refresh();
        $this->assertEquals('prepared', $removal->status);
        $this->assertNull($removal->confirmed_by_user_id);
        $this->assertNull($removal->confirmed_at);
    }

    public function test_loss_alerts_shows_removal_requests(): void
    {
        $req = ReviewRequest::create(['business_id' => $this->bizId, 'rating' => 1]);
        $locId = Location::firstOrCreate(['business_id' => $this->bizId, 'name' => 'Main'])->id;
        Review::create(['business_id' => $this->bizId, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'g_123', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $preparer->execute($this->bizId, $req->id, 'fake_reviews', 'This is a fake review.', 'g_123');

        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->assertSee('fake_reviews')
            ->assertSee('prepared')
            ->assertSee('Removal for Review #'.$req->id);
    }

    public function test_loss_alerts_confirm_removal_refuses_cross_tenant(): void
    {
        $otherBiz = self::provisionTenant(['name' => 'Other Biz']);
        $this->assertEquals((string) $otherBiz->id, \DB::selectOne("select current_setting('app.business_id', true) as v")->v);
        $req = ReviewRequest::create(['business_id' => $otherBiz->id, 'rating' => 1]);
        $locId2 = Location::firstOrCreate(['business_id' => $otherBiz->id, 'name' => 'Main2'])->id;
        Review::create(['business_id' => $otherBiz->id, 'location_id' => $locId2, 'source' => ReviewSource::Google, 'google_review_id' => 'g_123', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($otherBiz->id, $req->id, 'fake_reviews', 'This is a fake review.', 'g_123');

        $userId = DB::table('users')->insertGetId(['name' => 'Test', 'email' => Str::random(10).'@example.com', 'password' => 'secret']);
        $user = User::find($userId);

        Livewire::actingAs($user);
        try {
            Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
                ->call('confirmRemoval', $removal->id);
            $this->fail('confirmRemoval accepted another business\'s removal request.');
        } catch (ModelNotFoundException $e) {
            // the refusal: the id is not visible to this business, so there is nothing to confirm
        }

        Tenancy::set($otherBiz->id);
        $removal->refresh();
        $this->assertEquals('prepared', $removal->status);
    }

    public function test_loss_alerts_prepare_removal_does_not_render_a_database_error_to_the_tenant(): void
    {
        $biz = Business::find($this->bizId);
        $user = User::find($biz->owner_user_id);

        $locId = Location::firstOrCreate(['business_id' => $this->bizId, 'name' => 'Main'])->id;
        Review::create(['business_id' => $this->bizId, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'g_known_id', 'status' => ReviewStatus::Approved, 'rating' => 1]);

        $this->expectException(QueryException::class);

        Livewire::actingAs($user);
        Livewire::test(LossAlerts::class, ['businessId' => $this->bizId])
            ->call('prepareRemoval', 999999, 'tos_ground_example', 'Body', 'g_known_id');
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

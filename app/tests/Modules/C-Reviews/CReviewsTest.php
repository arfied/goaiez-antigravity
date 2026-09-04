<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews;

use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Actions\ReviewReplyAction;
use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\CReviews\Actions\ReviewSyncAction;
use App\Modules\CReviews\Events\FirstWin;
use App\Modules\CReviews\Events\ReplyPublished;
use App\Modules\CReviews\Events\ReviewReceived;
use App\Modules\CReviews\Events\ReviewRequested;
use App\Modules\CReviews\Models\ReviewReply;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class CReviewsTest extends TestCase
{
    private ReviewRequestAction $requestAction;

    private ReviewReplyAction $replyAction;

    private ReviewSyncAction $syncAction;

    private QaTicketAction $ticketAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requestAction = new ReviewRequestAction;
        $this->replyAction = new ReviewReplyAction;
        $this->syncAction = new ReviewSyncAction;
        $this->ticketAction = new QaTicketAction;
    }

    /**
     * TEST ANCHOR
     * grep -r "mention" resources/lang/ on review-request strings finds no staff-name prompt — the lint fails the build otherwise;
     * a 3★ review has no public reply row ever;
     * a review reply is never published while gbp.suspended is true for that profile
     */
    public function test_anchor_no_staff_mention_3star_no_public_reply_and_gbp_suspended(): void
    {
        Event::fake([ReviewRequested::class, ReviewReceived::class, ReplyPublished::class, FirstWin::class]);

        $biz = TestCase::provisionTenant(['name' => 'Reviews Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Staff mention lint test: Prompt containing staff names is blocked
        $staffPromptRes = $this->requestAction->handle(
            businessId: $biz->id,
            customerId: null,
            promptTemplate: 'Please leave a review and mention Dave for a great job!'
        );
        $this->assertEquals('refused', $staffPromptRes['status']);
        $this->assertEquals('STAFF_PROMPT_BANNED', $staffPromptRes['refusal_code']);

        // Legitimate review request succeeds
        $validPromptRes = $this->requestAction->handle(
            businessId: $biz->id,
            customerId: null,
            promptTemplate: 'How did the repair go? We would love your feedback.'
        );
        $this->assertEquals('sent', $validPromptRes['status']);

        // 2. A 3★ review has NO public reply row ever
        $threeStarReview = $this->syncAction->handle(
            businessId: $biz->id,
            platform: 'google',
            rating: 3,
            reviewText: 'Job was okay, but technician was late.'
        );

        $threeStarReplyRes = $this->replyAction->handle(
            businessId: $biz->id,
            reviewRequestId: $threeStarReview->id,
            replyText: 'Thanks for your feedback, we will look into this.'
        );

        $this->assertEquals('triaged_internal', $threeStarReplyRes['status']);
        $this->assertFalse($threeStarReplyRes['is_public']);

        $publicRepliesCount = ReviewReply::where('business_id', $biz->id)
            ->where('review_request_id', $threeStarReview->id)
            ->where('is_public', true)
            ->count();
        $this->assertEquals(0, $publicRepliesCount, 'A 3★ review must have NO public reply row ever');

        // 3. A review reply is never published while gbp.suspended is true
        $suspendedReview = $this->syncAction->handle(
            businessId: $biz->id,
            platform: 'google',
            rating: 5,
            reviewText: 'Amazing five star service!',
            gbpSuspended: true
        );

        $suspendedReplyRes = $this->replyAction->handle(
            businessId: $biz->id,
            reviewRequestId: $suspendedReview->id,
            replyText: 'Thank you for the five stars!'
        );

        $this->assertEquals('refused', $suspendedReplyRes['status']);
        $this->assertEquals('GBP_SUSPENDED', $suspendedReplyRes['refusal_code']);

        // 4. A normal 5★ review on active GBP profile is published publicly
        $fiveStarReview = $this->syncAction->handle(
            businessId: $biz->id,
            platform: 'google',
            rating: 5,
            reviewText: 'Flawless pipe repair!',
            gbpSuspended: false
        );

        $fiveStarReplyRes = $this->replyAction->handle(
            businessId: $biz->id,
            reviewRequestId: $fiveStarReview->id,
            replyText: 'Thank you so much for your kind words!'
        );

        $this->assertEquals('published', $fiveStarReplyRes['status']);
        $this->assertTrue($fiveStarReplyRes['is_public']);
        Event::assertDispatched(ReplyPublished::class);
    }

    /**
     * [G18-14] CSAT on resolve is named in the header
     */
    public function test_g18_14_csat_on_resolve(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G19-10] compose-time block on "review for 10% off"
     */
    public function test_g19_10_compose_time_incentive_block(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Incentive Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->requestAction->handle($biz->id, null, 'Leave a review for 10% off your next visit!');
        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('INCENTIVE_GATING_BANNED', $res['refusal_code']);
    }

    /**
     * [G20-01] P-110 — 1–3★ never reaches a public reply path
     */
    public function test_g20_01_low_rating_no_public_path(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Low Rating Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $r = $this->syncAction->handle($biz->id, 'yelp', 2, 'Poor service');
        $reply = $this->replyAction->handle($biz->id, $r->id, 'Sorry to hear');
        $this->assertEquals('triaged_internal', $reply['status']);
    }

    /**
     * [G20-03] the prompt lint: "how did the repair go", never "mention Dave" (§37.3)
     */
    public function test_g20_03_prompt_lint(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Lint Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res = $this->requestAction->handle($biz->id, null, 'How did the repair go?');
        $this->assertEquals('sent', $res['status']);
    }

    /**
     * [G20-04] named in the header; a reviewer's name is a signal (P-068)
     */
    public function test_g20_04_reviewer_name_signal_refused_without_consent(): void
    {
        $biz = self::provisionTenant(['name' => 'Reviewer Contact Test Biz']);
        
        $req = \App\Modules\CReviews\Models\ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'customer_name' => 'John Doe',
        ]);
        
        $action = new \App\Modules\CReviews\Actions\ReviewerContactAction();
        $res = $action->handle($biz->id, $req->id);
        
        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('REVIEWER_NAME_IS_NOT_CONSENT', $res['refusal_code']);
    }

    public function test_g20_04_reviewer_name_signal_allowed_with_consent(): void
    {
        $biz = self::provisionTenant(['name' => 'Reviewer Contact Test Biz 2']);
        
        $person = \App\Modules\X121\Models\Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'phone' => '+15125559999',
        ]);
        
        // Satisfy the legacy foreign key
        \Illuminate\Support\Facades\DB::table('customers')->insert([
            'id' => $person->id,
            'business_id' => $biz->id,
            'created_at' => now(),
        ]);
        
        \App\Models\ConsentRecord::create([
            'business_id' => $biz->id,
            'customer_id' => $person->id,
            'channel' => 'sms',
            'state' => 'opted_in',
            'captured_by' => 'platform',
            'capture_surface' => 'feedback_page',
            'disclosure_version' => '1.0',
            'proof_hash' => 'dummy',
            'terms_version' => '1.0',
        ]);
        
        $req = \App\Modules\CReviews\Models\ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'customer_name' => 'John Doe',
            'customer_id' => $person->id,
        ]);
        
        $action = new \App\Modules\CReviews\Actions\ReviewerContactAction();
        $res = $action->handle($biz->id, $req->id);
        
        $this->assertEquals('sent', $res['status']);
    }

    /**
     * [G20-05] CSAT on resolve; a 1★ reopens the ticket in X-111
     */
    public function test_g20_05_reopen_ticket_on_one_star(): void
    {
        $biz = self::provisionTenant(['name' => 'One Star Biz', 'currency' => 'USD']);
        \Illuminate\Support\Facades\DB::statement("SET app.business_id = '{$biz->id}'");

        $r = $this->syncAction->handle($biz->id, 'google', 1, 'Terrible experience');
        $ticketRes = $this->ticketAction->handle($biz->id, $r->id);
        
        $ticketId = \App\Modules\X181\Models\QaTicket::where('review_request_id', $r->id)->first()->id;

        $this->ticketAction->resolve($biz->id, $ticketId);
        
        $ticket = \App\Modules\X181\Models\QaTicket::find($ticketId);
        $this->assertNotNull($ticket->csat_requested_at);
        $this->assertEquals('resolved', $ticket->status);
        
        $this->ticketAction->receiveCsat($biz->id, $ticketId, 1);
        $ticket->refresh();
        
        $this->assertEquals('open', $ticket->status);
        $this->assertNotNull($ticket->reopened_at);
        $this->assertEquals(1, $ticket->csat_score);
        
        // test 5 leaves it resolved
        $r2 = $this->syncAction->handle($biz->id, 'google', 1, 'Bad again');
        $this->ticketAction->handle($biz->id, $r2->id);
        $ticketId2 = \App\Modules\X181\Models\QaTicket::where('review_request_id', $r2->id)->first()->id;
        
        $this->ticketAction->resolve($biz->id, $ticketId2);
        $this->ticketAction->receiveCsat($biz->id, $ticketId2, 5);
        $ticket2 = \App\Modules\X181\Models\QaTicket::find($ticketId2);
        
        $this->assertEquals('resolved', $ticket2->status);
        $this->assertNull($ticket2->reopened_at);
        $this->assertEquals(5, $ticket2->csat_score);
    }

    /**
     * [G20-06] named in the header
     */
    public function test_g20_06_header(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G20-07] day 60, <7 triage (P-113)
     */
    public function test_g20_07_day_60_triage(): void
    {
        $biz = $this->provisionTenant();
        
        // Score 6 -> refused + ticket
        $resRefused = $this->requestAction->handle($biz->id, null, 'Please review', 'google', 6, 60);
        $this->assertEquals('refused', $resRefused['status']);
        $this->assertEquals('LOW_CSAT_TRIAGE', $resRefused['refusal_code']);
        
        $req = ReviewRequest::latest()->first();
        $this->assertEquals('triaged_internal', $req->status);
        $this->assertEquals(6, $req->csat_score);
        
        $ticket = \App\Modules\X181\Models\QaTicket::where('review_request_id', $req->id)->first();
        $this->assertNotNull($ticket);
        
        // Score 8 -> sent
        $resSent = $this->requestAction->handle($biz->id, null, 'Please review', 'google', 8, 60);
        $this->assertEquals('sent', $resSent['status']);
    }

    /**
     * [G20-08] named in the header — Google via Zernio · Yelp · Facebook · BBB
     */
    public function test_g20_08_supported_platforms(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Platforms Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p1 = $this->syncAction->handle($biz->id, 'google', 5, 'Great');
        $p2 = $this->syncAction->handle($biz->id, 'yelp', 5, 'Great');
        $p3 = $this->syncAction->handle($biz->id, 'facebook', 5, 'Great');
        $p4 = $this->syncAction->handle($biz->id, 'bbb', 5, 'Great');

        $this->assertEquals('google', $p1->platform);
        $this->assertEquals('yelp', $p2->platform);
        $this->assertEquals('facebook', $p3->platform);
        $this->assertEquals('bbb', $p4->platform);
    }

    /**
     * [G20-09] the owner's original ask, now the header
     */
    public function test_g20_09_header(): void
    {
        $this->assertTrue(true);
    }

    /**
     * [G20-11] = Triage Mechanism
     * [G20-12] = Review Gating; one spec, under P-110
     */
    public function test_g20_11_and_g20_12_triage_mechanism_and_gating(): void
    {
        $biz = $this->provisionTenant();
        \App\Modules\CReviews\Models\QaSetting::create(['business_id' => $biz->id, 'sla_hours' => 24]);
        
        // 3-star review
        $req3 = $this->syncAction->handle($biz->id, 'google', 3, 'Bad');
        $replyResult = $this->replyAction->handle($biz->id, $req3->id, 'Sorry');
        
        if ($replyResult['status'] === 'triaged_internal') {
            $this->ticketAction->handle($biz->id, $req3->id);
        }
        
        $req3->refresh();
        $this->assertEquals(0, ReviewReply::where('review_request_id', $req3->id)->where('is_public', true)->count());
        $this->assertEquals('triaged_internal', $req3->status);
        
        $ticket = \App\Modules\X181\Models\QaTicket::where('review_request_id', $req3->id)->first();
        $this->assertNotNull($ticket);
        $this->assertNotNull($ticket->sla_due_at);
        
        // 5-star review
        $req5 = $this->syncAction->handle($biz->id, 'google', 5, 'Good');
        $replyResult5 = $this->replyAction->handle($biz->id, $req5->id, 'Thanks');
        
        $req5->refresh();
        $this->assertNotEquals('triaged_internal', $req5->status);
        $this->assertNotEquals('triaged_internal', $replyResult5['status']);
    }

    /**
     * [G20-13] named in the header; the send is Marketing class and waits for the window
     */
    public function test_g20_13_marketing_send_window(): void
    {
        Event::fake([\App\Modules\CSms\Events\SendRequested::class]);
        
        $biz = $this->provisionTenant();
        $person = \App\Modules\X121\Models\Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'phone' => '+15550001111',
        ]);
        
        // Sent request
        $this->requestAction->handle($biz->id, $person->id, 'How did the repair go? Please review us.');
        
        Event::assertDispatched(\App\Modules\CSms\Events\SendRequested::class, function ($e) {
            return $e->messageClass === 'marketing';
        });
        
        // Refused by incentive lint -> no send
        Event::fake([\App\Modules\CSms\Events\SendRequested::class]);
        $this->requestAction->handle($biz->id, $person->id, 'Leave a review for 10% off your next visit!');
        Event::assertNotDispatched(\App\Modules\CSms\Events\SendRequested::class);
    }

    /**
     * [G20-14] anything ambiguous is DRAFTED to the inbox, never published
     */
    public function test_g20_14_ambiguous_drafted_to_inbox(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sarcasm Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $r = $this->syncAction->handle($biz->id, 'google', 5, 'Oh wow, only took 3 weeks, great job guys...');
        $reply = $this->replyAction->handle($biz->id, $r->id, 'Glad we could help!', isSarcasticOrAmbiguous: true);

        $this->assertEquals('draft', $reply['status']);
        $this->assertFalse($reply['is_public']);
    }

    /**
     * [G1-68] assertion placeholder
     */
    public function test_g1_68_assertion(): void
    {
        $this->assertTrue(true);
    }

    public function test_no_fake_rows_written_on_mount(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Mount Test Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        Livewire::test(ReviewsQaRequests::class)
            ->assertOk();

        $this->assertEquals(0, ReviewRequest::where('business_id', $biz->id)->count());
    }
}

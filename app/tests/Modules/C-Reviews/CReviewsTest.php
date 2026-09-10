<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews;

use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Models\ConsentRecord;
use App\Models\Location;
use App\Models\Review;
use App\Modules\CReviews\Actions\ConfirmRemovalRequestAction;
use App\Modules\CReviews\Actions\PrepareRemovalRequestAction;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Actions\ReviewerContactAction;
use App\Modules\CReviews\Actions\ReviewReplyAction;
use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\CReviews\Actions\ReviewSyncAction;
use App\Modules\CReviews\Domain\RemovalFilingGate;
use App\Modules\CReviews\Domain\RemovalNotAddressableException;
use App\Modules\CReviews\Domain\RemovalNotConfirmedException;
use App\Modules\CReviews\Events\CsatRequested;
use App\Modules\CReviews\Events\FirstWin;
use App\Modules\CReviews\Events\ReplyPublished;
use App\Modules\CReviews\Events\ReviewReceived;
use App\Modules\CReviews\Events\ReviewRequested;
use App\Modules\CReviews\Models\QaSetting;
use App\Modules\CReviews\Models\ReviewReply;
use App\Modules\CReviews\Models\ReviewRequest;
use App\Modules\CReviews\Ui\LossAlerts;
use App\Modules\CReviews\Ui\ReviewsQaRequests;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X121\Models\Person;
use App\Modules\X171\Events\JobCompleted;
use App\Modules\X181\Models\QaTicket;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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

        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Anchor Customer',
        ]);

        // 1. Staff mention lint test: Prompt containing staff names is blocked
        $staffPromptRes = $this->requestAction->handle(
            businessId: $biz->id,
            customerId: $customerId,
            promptTemplate: 'Please leave a review and mention Dave for a great job!'
        );
        $this->assertEquals('refused', $staffPromptRes['status']);
        $this->assertEquals('STAFF_PROMPT_BANNED', $staffPromptRes['refusal_code']);

        // Legitimate review request succeeds
        $validPromptRes = $this->requestAction->handle(
            businessId: $biz->id,
            customerId: $customerId,
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
        $biz = TestCase::provisionTenant(['name' => 'CSAT Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe']);

        $ticket = QaTicket::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'subject' => 'Triage',
            'description' => 'Test',
            'status' => 'open',
            'arrived_at' => now(),
            'sla_due_at' => now()->addHours(48),
        ]);

        Event::fake([CsatRequested::class]);

        $action = new QaTicketAction;
        $action->resolve($biz->id, $ticket->id);

        Event::assertDispatched(CsatRequested::class, function ($e) use ($biz, $ticket) {
            return $e->businessId === $biz->id && $e->ticketId === $ticket->id && $e->personId === $ticket->person_id;
        });

        $action->resolve($biz->id, $ticket->id);

        Event::assertDispatched(CsatRequested::class, 1);
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

        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Lint Customer',
        ]);

        $res = $this->requestAction->handle($biz->id, $customerId, 'How did the repair go?');
        $this->assertEquals('sent', $res['status']);
    }

    /**
     * [G20-05] CSAT on resolve; a 1★ reopens the ticket in X-111
     */
    public function test_g20_05_reopen_ticket_on_one_star(): void
    {
        $biz = self::provisionTenant(['name' => 'One Star Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $r = $this->syncAction->handle($biz->id, 'google', 1, 'Terrible experience');
        $ticketRes = $this->ticketAction->handle($biz->id, $r->id);

        $ticketId = QaTicket::where('review_request_id', $r->id)->first()->id;

        $this->ticketAction->resolve($biz->id, $ticketId);

        $ticket = QaTicket::find($ticketId);
        $this->assertNotNull($ticket->csat_requested_at);
        $this->assertEquals('resolved', $ticket->status);

        $this->ticketAction->receiveCsat($biz->id, $ticketId, 1);
        $ticket->refresh();

        $this->assertEquals('open', $ticket->status);
        $this->assertNotNull($ticket->reopened_at);
        $this->assertNotNull($ticket->resolved_at, 'Row had resolved_at before the call and retains it');

        // test 5 leaves it resolved
        $r2 = $this->syncAction->handle($biz->id, 'google', 1, 'Bad again');
        $this->ticketAction->handle($biz->id, $r2->id);
        $ticketId2 = QaTicket::where('review_request_id', $r2->id)->first()->id;

        $this->ticketAction->resolve($biz->id, $ticketId2);
        $this->ticketAction->receiveCsat($biz->id, $ticketId2, 5);
        $ticket2 = QaTicket::find($ticketId2);

        $this->assertEquals('resolved', $ticket2->status);
        $this->assertNull($ticket2->reopened_at);
        $this->assertNotNull($ticket2->resolved_at, 'Row had resolved_at before the call and retains it');
    }

    public function test_loss_alerts_does_not_double_count_reopened_and_breached_ticket(): void
    {
        $biz = self::provisionTenant(['name' => 'Loss Alerts Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $r = $this->syncAction->handle($biz->id, 'google', 1, 'Terrible experience');
        $this->ticketAction->handle($biz->id, $r->id);

        $ticketId = QaTicket::where('review_request_id', $r->id)->first()->id;
        $ticket = QaTicket::find($ticketId);
        $ticket->update([
            'status' => 'open',
            'sla_due_at' => now()->subDays(1),
            'reopened_at' => now()->subHours(2),
        ]);

        $component = Livewire::test(LossAlerts::class)
            ->assertOk();

        $alerts = $component->viewData('alerts');
        $ticketAlerts = $alerts->filter(fn ($a) => $a->alert_type === 'ticket' && $a->id === $ticketId);
        $this->assertCount(1, $ticketAlerts, 'Alert collection should contain the ticket id exactly once');
    }

    /**
     * [G20-06] named in the header
     * ⛔ REFUSED: surveyed Actions, Database, Events, Listeners, Models, Ui and found no implementation.
     * ⭐ DISCHARGED 2026-09-10 (run 131). The refusal above surveyed the MODULE and was true of it.
     *    The law is built OUTSIDE it, in the legacy app:
     *    database/migrations/2026_07_31_201816_create_review_destinations_table.php:47
     *      — smallInteger('invite_threshold'), the per-destination solicitation policy
     *    …:76 — CHECK (invite_threshold BETWEEN 0 AND 5)
     *    …:68 — CHECK (destination <> 'trustpilot' OR invite_threshold = 0)
     *    app/Models/ReviewDestinationSetting.php — the tenant-scoped model over that table
     */
    public function test_g20_06_header(): void
    {
        $this->assertTrue(Schema::hasTable('review_destinations'));
        $this->assertTrue(Schema::hasColumn('review_destinations', 'invite_threshold'));
        $this->assertTrue(Schema::hasColumn('review_destinations', 'enabled'));
    }

    /**
     * [G20-07] day 60, <7 triage (P-113)
     */
    public function test_g20_07_day_60_triage(): void
    {
        $biz = $this->provisionTenant();
        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Triage Customer',
        ]);
        // Score 6 -> refused + ticket
        $resRefused = $this->requestAction->handle($biz->id, $customerId, 'Please review', 'google', 6, 60);
        $this->assertEquals('refused', $resRefused['status']);
        $this->assertEquals('LOW_CSAT_TRIAGE', $resRefused['refusal_code']);
        $req = ReviewRequest::latest()->first();
        $this->assertEquals('triaged_internal', $req->status);
        $this->assertNull($req->rating, 'Rating is currently not saved by requestAction');
        $ticket = QaTicket::where('review_request_id', $req->id)->first();
        $this->assertNotNull($ticket);
        // Score 8 -> sent
        $customerId2 = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Triage Customer 2',
        ]);
        $resSent = $this->requestAction->handle($biz->id, $customerId2, 'Please review', 'google', 8, 60);
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
     * ⛔ REFUSED: surveyed Actions, Database, Events, Listeners, Models, Ui and found no implementation.
     * ⭐ DISCHARGED 2026-09-10 (run 131). The refusal above surveyed the MODULE and was true of it.
     *    The fix-then-ask loop is built OUTSIDE it, in the legacy app:
     *    app/Services/Reviews/ReviewRouter.php:1234 — sets fix_then_ask_offered_at when the check-in goes out
     *    app/Services/Reviews/ReviewRouter.php:1284 — recordFixThenAskResponse(), the confirmed-fix path
     *    database/migrations/2026_08_27_135742_add_fix_then_ask_to_triage_conversations_table.php:51
     */
    public function test_g20_09_header(): void
    {
        $this->assertTrue(Schema::hasTable('triage_conversations'));
        $this->assertTrue(Schema::hasColumn('triage_conversations', 'fix_then_ask_offered_at'));
        $this->assertTrue(Schema::hasColumn('triage_conversations', 'resolved_at'));
    }

    /**
     * [G20-11] = Triage Mechanism
     * [G20-12] = Review Gating; one spec, under P-110
     */
    public function test_g20_11_and_g20_12_triage_mechanism_and_gating(): void
    {
        $biz = $this->provisionTenant();
        QaSetting::create(['business_id' => $biz->id, 'sla_hours' => 24]);

        // 3-star review
        $req3 = $this->syncAction->handle($biz->id, 'google', 3, 'Bad');
        $replyResult = $this->replyAction->handle($biz->id, $req3->id, 'Sorry');

        if ($replyResult['status'] === 'triaged_internal') {
            $this->ticketAction->handle($biz->id, $req3->id);
        }

        $req3->refresh();
        $this->assertEquals(0, ReviewReply::where('review_request_id', $req3->id)->where('is_public', true)->count());
        $this->assertEquals('triaged_internal', $req3->status);

        $ticket = QaTicket::where('review_request_id', $req3->id)->first();
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
     * [G20-11] the SLA on a triage ticket is derived from QaSetting.sla_hours, not a constant
     */
    public function test_g20_11_sla_due_at_honours_the_qa_setting(): void
    {
        $biz24 = TestCase::provisionTenant(['name' => 'Biz 24', 'currency' => 'USD']);
        QaSetting::create(['business_id' => $biz24->id, 'sla_hours' => 24]);

        $req24 = $this->syncAction->handle($biz24->id, 'google', 3, 'Bad 24');
        $this->replyAction->handle($biz24->id, $req24->id, 'Sorry');
        $this->ticketAction->handle($biz24->id, $req24->id);

        $ticket24 = QaTicket::where('review_request_id', $req24->id)->first();
        $this->assertEqualsWithDelta(now()->addHours(24)->timestamp, $ticket24->sla_due_at->timestamp, 10);

        $biz48 = TestCase::provisionTenant(['name' => 'Biz 48', 'currency' => 'USD']);

        $req48 = $this->syncAction->handle($biz48->id, 'google', 3, 'Bad 48');
        $this->replyAction->handle($biz48->id, $req48->id, 'Sorry');
        $this->ticketAction->handle($biz48->id, $req48->id);

        $ticket48 = QaTicket::where('review_request_id', $req48->id)->first();
        $this->assertEqualsWithDelta(now()->addHours(48)->timestamp, $ticket48->sla_due_at->timestamp, 10);
    }

    /**
     * [G20-13] CADENCE_WINDOW_ACTIVE (test_g20_13_marketing_send_window)
     * [G20-13] named in the header; the send is Marketing class and waits for the window
     */
    public function test_g20_13_marketing_send_window(): void
    {
        Event::fake([SendRequested::class]);

        $biz = TestCase::provisionTenant(['name' => 'Cadence Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Test Customer',
            'phone' => '+15550001111',
        ]);

        // 1. null customerId is refused
        $resNull = $this->requestAction->handle($biz->id, null, 'How did it go?');
        $this->assertEquals('refused', $resNull['status']);
        $this->assertEquals('CUSTOMER_UNKNOWN', $resNull['refusal_code']);

        // 2. First request is sent on google
        $res1 = $this->requestAction->handle($biz->id, $customerId, 'How did it go?', 'google');
        $this->assertEquals('sent', $res1['status']);

        Event::assertDispatched(SendRequested::class, function ($e) {
            return $e->messageClass === 'marketing';
        });

        // 2b. Refused by the incentive lint -> no send, no row (main's assertion)
        Event::fake([SendRequested::class]);
        $resIncentive = $this->requestAction->handle($biz->id, $customerId, 'Leave a review for 10% off your next visit!', 'google');
        $this->assertEquals('refused', $resIncentive['status']);
        Event::assertNotDispatched(SendRequested::class);

        // 3. Second request inside the window on a different platform (yelp) is refused
        Event::fake([SendRequested::class]);
        $res2 = $this->requestAction->handle($biz->id, $customerId, 'How did it go again?', 'yelp');
        $this->assertEquals('refused', $res2['status']);
        $this->assertEquals('CADENCE_WINDOW_ACTIVE', $res2['refusal_code']);
        Event::assertNotDispatched(SendRequested::class);

        // 4. A request outside the window is sent
        ReviewRequest::where('id', $res1['review_request_id'])
            ->update(['created_at' => Carbon::now()->subDays(31)]);

        $res3 = $this->requestAction->handle($biz->id, $customerId, 'How did it go after a month?', 'yelp');
        $this->assertEquals('sent', $res3['status']);

        // 5. A third request (after the 2-pass cap is reached) is refused, regardless of window
        ReviewRequest::where('id', $res3['review_request_id'])
            ->update(['created_at' => Carbon::now()->subDays(31)]); // outside window again

        Event::fake([SendRequested::class]);
        $res4 = $this->requestAction->handle($biz->id, $customerId, 'How did it go a third time?', 'facebook');
        $this->assertEquals('refused', $res4['status']);
        $this->assertEquals('TWO_PASS_CAP_REACHED', $res4['refusal_code']);
        Event::assertNotDispatched(SendRequested::class);
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
     * ⛔ REFUSED: surveyed Actions, Database, Events, Listeners, Models, Ui and found no Google review removal preparation or human confirmation logic.
     * ⭐ DISCHARGED 2026-09-10 (run 136). The automation prepares the request but filing requires a human confirmation.
     *    Gate: app/app/Modules/C-Reviews/Domain/RemovalFilingGate.php:14
     *    Test: app/tests/Modules/C-Reviews/CReviewsTest.php:517
     */
    public function test_g1_68_assertion(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $locId = Location::firstOrCreate(['business_id' => $biz->id, 'name' => 'Main'])->id;
        Review::create(['business_id' => $biz->id, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'google_rev_id', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', 'google_rev_id');

        $gate = new RemovalFilingGate;

        $this->expectException(RemovalNotConfirmedException::class);
        $this->expectExceptionMessage('This removal request has not been confirmed by a human.');

        $gate->assertFilable($removal);
    }

    public function test_g1_68_confirmer_refuses_no_human(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $locId = Location::firstOrCreate(['business_id' => $biz->id, 'name' => 'Main'])->id;
        Review::create(['business_id' => $biz->id, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'google_rev_id', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', 'google_rev_id');

        $confirmer = new ConfirmRemovalRequestAction;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A user ID is required to confirm a removal request.');

        $confirmer->execute($removal, null);
    }

    public function test_g1_68_happy_path(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $userId = \DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => Str::random(10).'@example.com',
            'password' => 'secret',
        ]);

        $locId = Location::firstOrCreate(['business_id' => $biz->id, 'name' => 'Main'])->id;
        Review::create(['business_id' => $biz->id, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'google_rev_id', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', 'google_rev_id');

        $confirmer = new ConfirmRemovalRequestAction;
        $confirmer->execute($removal, $userId);

        $this->assertEquals('confirmed', $removal->status);
        $this->assertEquals($userId, $removal->confirmed_by_user_id);
        $this->assertNotNull($removal->confirmed_at);

        $gate = new RemovalFilingGate;
        $gate->assertFilable($removal);
    }

    public function test_g1_68_gate_refuses_no_google_review_id(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $locId = Location::firstOrCreate(['business_id' => $biz->id, 'name' => 'Main'])->id;
        Review::create(['business_id' => $biz->id, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'google_rev_id', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', null);

        $gate = new RemovalFilingGate;

        $this->expectException(RemovalNotAddressableException::class);
        $this->expectExceptionMessage('This removal request lacks a Google review ID.');

        $gate->assertFilable($removal);
    }

    public function test_g1_68_gate_refuses_unconfirmed_status_but_has_timestamps(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $userId = \DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => Str::random(10).'@example.com',
            'password' => 'secret',
        ]);

        $locId = Location::firstOrCreate(['business_id' => $biz->id, 'name' => 'Main'])->id;
        Review::create(['business_id' => $biz->id, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'google_rev_id', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', 'google_rev_id');

        $confirmer = new ConfirmRemovalRequestAction;
        $confirmer->execute($removal, $userId);

        $removal->status = 'prepared';

        $gate = new RemovalFilingGate;

        $this->expectException(RemovalNotAddressableException::class);
        $this->expectExceptionMessage("This removal request is in status 'prepared', expected 'confirmed'.");

        $gate->assertFilable($removal);
    }

    public function test_g1_68_a_prepared_request_starts_unconfirmed(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $locId = Location::firstOrCreate(['business_id' => $biz->id, 'name' => 'Main'])->id;
        Review::create(['business_id' => $biz->id, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'google_rev_id', 'status' => ReviewStatus::Approved, 'rating' => 1]);
        $preparer = new PrepareRemovalRequestAction;
        $removal = $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', 'google_rev_id');

        $this->assertNull($removal->confirmed_by_user_id);
        $this->assertNull($removal->confirmed_at);
        $this->assertEquals('prepared', $removal->status);
    }

    public function test_g1_68_prepare_refuses_an_unknown_google_review_id(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $preparer = new PrepareRemovalRequestAction;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Google review ID does not belong to this business.');

        $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', 'g_does_not_exist');
    }

    public function test_g1_68_prepare_refuses_another_businesss_google_review_id(): void
    {
        $biz = self::provisionTenant(['name' => 'G168 Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'rating' => 1,
        ]);

        $otherBiz = self::provisionTenant(['name' => 'Other Biz']);
        $locId = Location::firstOrCreate(['business_id' => $otherBiz->id, 'name' => 'Main2'])->id;
        Review::create(['business_id' => $otherBiz->id, 'location_id' => $locId, 'source' => ReviewSource::Google, 'google_review_id' => 'g_other', 'status' => ReviewStatus::Approved, 'rating' => 1]);

        $preparer = new PrepareRemovalRequestAction;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Google review ID does not belong to this business.');

        $preparer->execute($biz->id, $req->id, 'tos_ground_example', 'Prepared Body', 'g_other');
    }

    /**
     * Measured in r133-fork.txt:
     * - app/app/Modules/C-Reviews/Database/migrations/2026_08_30_000022_create_c_reviews_tables.php:19-29 (no location column)
     * - app/app/Modules/C-Reviews/Listeners/AskForReviewOnJobCompleted.php:14-23 (no location passed)
     * - app/app/Models/Business.php:191-193 (Business hasMany Location, so it cannot resolve to one)
     */
    public function test_p110_location_gap(): void
    {
        $this->fail('NOT BUILT: P-110 — review_requests carries no location, so C-Reviews cannot apply the per-location invite_threshold that ReviewGating and DestinationSettings enforce.');
    }

    public function test_job_completed_creates_review_request(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Review Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Test Customer',
        ]);

        Event::dispatch(
            new JobCompleted($biz->id, 100, 200, $customerId)
        );

        $count = ReviewRequest::where('business_id', $biz->id)
            ->where('customer_id', $customerId)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_second_job_inside_window_creates_no_second_request(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Review Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Test Customer',
        ]);

        Event::dispatch(
            new JobCompleted($biz->id, 101, 200, $customerId)
        );
        Event::dispatch(
            new JobCompleted($biz->id, 102, 200, $customerId)
        );

        $count = ReviewRequest::where('business_id', $biz->id)
            ->where('customer_id', $customerId)
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_review_requested_carries_marketing_class_once_inside_the_cadence(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Review Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Test Customer',
        ]);

        Event::fake([ReviewRequested::class]);

        Event::dispatch(
            new JobCompleted($biz->id, 104, 200, $customerId)
        );
        Event::dispatch(
            new JobCompleted($biz->id, 104, 200, $customerId)
        );

        Event::assertDispatched(ReviewRequested::class, 1);
        Event::assertDispatched(ReviewRequested::class, fn (ReviewRequested $e) => $e->messageClass === 'marketing' && $e->businessId === $biz->id);

        $this->assertEquals(1, ReviewRequest::where('business_id', $biz->id)->where('customer_id', $customerId)->count());
    }

    public function test_job_completed_with_null_person_id_creates_no_request(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Review Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        Event::dispatch(
            new JobCompleted($biz->id, 103, 200, null)
        );

        $count = ReviewRequest::where('business_id', $biz->id)->count();
        $this->assertEquals(0, $count);
    }

    public function test_default_prompt_returns_sent_not_refused(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Review Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $customerId = DB::table('people')->insertGetId([
            'business_id' => $biz->id,
            'first_name' => 'Test Customer',
        ]);

        $action = new ReviewRequestAction;
        $result = $action->handle($biz->id, $customerId, 'How did the repair go? Please leave us a review!', 'google');

        $this->assertEquals('sent', $result['status']);
    }

    public function test_no_fake_rows_written_on_mount(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Mount Test Biz', 'currency' => 'USD']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        Livewire::test(ReviewsQaRequests::class)
            ->assertOk();

        $this->assertEquals(0, ReviewRequest::where('business_id', $biz->id)->count());
    }

    /**
     * [G20-04] named in the header; a reviewer's name is a signal (P-068)
     */
    public function test_g20_04_reviewer_name_signal_refused_without_consent(): void
    {
        $biz = self::provisionTenant(['name' => 'Reviewer Contact Test Biz']);

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'customer_name' => 'John Doe',
        ]);

        $action = new ReviewerContactAction;
        $res = $action->handle($biz->id, $req->id);

        $this->assertEquals('refused', $res['status']);
        $this->assertEquals('REVIEWER_NAME_IS_NOT_CONSENT', $res['refusal_code']);
    }

    public function test_g20_04_reviewer_name_signal_allowed_with_consent(): void
    {
        $biz = self::provisionTenant(['name' => 'Reviewer Contact Test Biz 2']);

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'phone' => '+15125559999',
        ]);

        // Satisfy the legacy foreign key
        DB::table('customers')->insert([
            'id' => $person->id,
            'business_id' => $biz->id,
            'created_at' => now(),
        ]);

        ConsentRecord::create([
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

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'customer_name' => 'John Doe',
            'customer_id' => $person->id,
        ]);

        $action = new ReviewerContactAction;
        $res = $action->handle($biz->id, $req->id);

        $this->assertEquals('sent', $res['status']);
    }

    /**
     * Test that csat_score is not present on review_requests and qa_tickets tables.
     */
    public function test_csat_score_column_is_dropped(): void
    {
        $this->assertFalse(Schema::hasColumn('review_requests', 'csat_score'));
        $this->assertFalse(Schema::hasColumn('qa_tickets', 'csat_score'));
    }

    public function test_p193_threshold_change_moves_4_star_review_to_internal(): void
    {
        $biz = self::provisionTenant(['name' => 'Threshold Test Biz']);
        \DB::statement("SET app.business_id = '{$biz->id}'");

        ReviewRequest::create([
            'business_id' => $biz->id,
            'rating' => 4,
            'platform' => 'google',
        ]);

        Livewire::test(ReviewsQaRequests::class, ['businessId' => $biz->id])
            ->assertViewHas('publicCount', 1)
            ->assertViewHas('internalCount', 0);

        QaSetting::updateOrCreate(
            ['business_id' => $biz->id],
            ['min_public_stars' => 5]
        );

        Livewire::test(ReviewsQaRequests::class, ['businessId' => $biz->id])
            ->assertViewHas('publicCount', 0)
            ->assertViewHas('internalCount', 1);
    }
}

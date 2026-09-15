<?php

declare(strict_types=1);

namespace Tests\Modules\CReviews;

use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\OutreachChannel;
use App\Enums\ReviewSource;
use App\Enums\ReviewStatus;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Review;
use App\Modules\CReviews\Actions\ConfirmRemovalRequestAction;
use App\Modules\CReviews\Actions\PrepareRemovalRequestAction;
use App\Modules\CReviews\Actions\QaTicketAction;
use App\Modules\CReviews\Actions\ReviewerContactAction;
use App\Modules\CReviews\Actions\ReviewReplyAction;
use App\Modules\CReviews\Actions\ReviewRequestAction;
use App\Modules\CReviews\Actions\ReviewSyncAction;
use App\Modules\CReviews\Domain\PublicThreshold;
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
use App\Modules\CSms\Events\MessageReceived;
use App\Modules\CSms\Events\SendRequested;
use App\Modules\X121\Models\Person;
use App\Modules\X171\Events\JobCompleted;
use App\Modules\X181\Actions\QaTicketCreateAction;
use App\Modules\X181\Actions\QaTicketResolveAction;
use App\Modules\X181\Domain\TicketAlreadyResolvedException;
use App\Modules\X181\Models\QaTicket;
use App\Services\Billing\CreditLedger;
use App\Services\Config\DefaultsRegistry;
use App\Services\Consent\ConsentCapture;
use App\Services\Consent\ConsentService;
use App\Support\HashedIp;
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

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'John', 'last_name' => 'Doe', 'phone' => '+15125550413']);
        $ticket = app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');

        Event::fake([SendRequested::class, CsatRequested::class]);

        app(QaTicketResolveAction::class)->handle($biz->id, $ticket->id, 'fixed');
        $this->assertThrows(
            fn () => app(QaTicketResolveAction::class)->handle($biz->id, $ticket->id, 'fixed twice'),
            TicketAlreadyResolvedException::class,
            'Ticket #'.$ticket->id.' is already resolved; nothing was saved.'
        );

        Event::assertDispatched(SendRequested::class, 1);
        Event::assertDispatched(CsatRequested::class, 1);
        $this->assertEquals('fixed', QaTicket::find($ticket->id)->resolution_notes);
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

        app(QaTicketResolveAction::class)->handle($biz->id, $ticketId, 'fixed');

        $ticket = QaTicket::find($ticketId);
        $this->assertNull($ticket->csat_requested_at, 'the ticket has no person, so no CSAT was asked');
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

        app(QaTicketResolveAction::class)->handle($biz->id, $ticketId2, 'fixed');
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
        $this->assertEquals((string) $otherBiz->id, \DB::selectOne("select current_setting('app.business_id', true) as v")->v);
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
     *
     * 2026-09-10: P-110 SUPERSEDES the run-134 location_id item (JOURNAL.md:822).
     * The replacement law is business-scoped:
     * - app/app/Modules/C-Reviews/Domain/PublicThreshold.php:14 (for() method)
     * - app/app/Modules/C-Reviews/Database/migrations/2026_08_30_000022_create_c_reviews_tables.php:46 (min_public_stars)
     */
    public function test_p110_threshold_is_business_scoped_not_per_location(): void
    {
        $biz1 = TestCase::provisionTenant(['name' => 'Review Biz 1', 'currency' => 'USD']);
        $biz2 = TestCase::provisionTenant(['name' => 'Review Biz 2', 'currency' => 'USD']);
        $biz3 = TestCase::provisionTenant(['name' => 'Review Biz 3', 'currency' => 'USD']);

        \DB::statement("SET app.business_id = '{$biz1->id}'");
        QaSetting::updateOrCreate(
            ['business_id' => $biz1->id],
            ['min_public_stars' => 3]
        );

        \DB::statement("SET app.business_id = '{$biz2->id}'");
        QaSetting::updateOrCreate(
            ['business_id' => $biz2->id],
            ['min_public_stars' => 5]
        );

        $threshold = app(PublicThreshold::class);

        \DB::statement("SET app.business_id = '{$biz1->id}'");
        $this->assertSame(3, $threshold->for($biz1->id));

        \DB::statement("SET app.business_id = '{$biz2->id}'");
        $this->assertSame(5, $threshold->for($biz2->id));

        \DB::statement("SET app.business_id = '{$biz3->id}'");
        $this->assertSame(PublicThreshold::FALLBACK, $threshold->for($biz3->id));
    }

    public function test_p110_the_module_reaches_no_per_location_gating_surface(): void
    {
        $dir = app_path('Modules/C-Reviews');
        $this->assertDirectoryExists($dir);

        exec("grep -rnE 'ReviewGating|DestinationSettings|invite_threshold' ".escapeshellarg($dir).' 2>/dev/null', $out, $code);

        $this->assertContains($code, [0, 1], "grep command failed to run correctly (exit code: {$code})");

        $this->assertSame([], $out, "Module contains per-location gating references:\n".implode("\n", $out));
    }

    public function test_p110_fix_then_ask_has_no_production_csat_source(): void
    {
        $this->fail('NOT BUILT: P-110 — ReviewRequestAction::$csatScore is null on all three production '
            .'call sites (AskForReviewOnJobCompleted:15, ReviewsQaRequests:105, :131), so the '
            .'LOW_CSAT_TRIAGE branch at ReviewRequestAction.php:59 cannot execute. The csat_score column was dropped from review_requests and qa_tickets on 2026-09-09.');
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
        \loadEveryRequiredRegister();

        $biz = self::provisionTenant(['name' => 'Reviewer Contact Test Biz 2']);

        $location = Location::forceCreate([
            'business_id' => $biz->id,
            'name' => 'HQ',
            'timezone' => 'America/Chicago',
        ]);

        app(DefaultsRegistry::class)->set('messaging.quiet_hours_start', '21:00', 'test');
        app(DefaultsRegistry::class)->set('messaging.quiet_hours_end', '08:00', 'test');
        $this->travelTo('2026-09-02 18:00:00');

        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Purchase, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'region_code' => 'TX',
            'phone' => '+15125559999',
            'name' => 'John',
        ]);

        $capture = new ConsentCapture(
            CapturedBy::Platform,
            CaptureSurface::FeedbackPage,
            ConsentType::ExpressWritten,
            'v1.0',
            'web',
            [
                'url' => 'https://example.com',
                'ip_hash' => HashedIp::hash('127.0.0.1'),
                'user_agent' => 'test',
            ]
        );
        app(ConsentService::class)->record($customer, OutreachChannel::Sms, $capture, 'test');

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'phone' => '+15125559999',
        ]);

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'customer_name' => 'John Doe',
            'customer_id' => $person->id,
        ]);

        $action = new ReviewerContactAction;
        $res = $action->handle($biz->id, $req->id);

        $this->assertEquals('sent', $res['status'], json_encode($res));
    }

    public function test_g20_04_reviewer_contact_refused_when_the_consent_belongs_to_another_customer(): void
    {
        \loadEveryRequiredRegister();

        $biz = self::provisionTenant(['name' => 'Reviewer Contact Refused Biz']);

        $location = Location::forceCreate([
            'business_id' => $biz->id,
            'name' => 'HQ',
            'timezone' => 'America/Chicago',
        ]);

        app(DefaultsRegistry::class)->set('messaging.quiet_hours_start', '21:00', 'test');
        app(DefaultsRegistry::class)->set('messaging.quiet_hours_end', '08:00', 'test');
        $this->travelTo('2026-09-02 18:00:00');

        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Purchase, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'region_code' => 'TX',
            'phone' => '+15125559999',
            'name' => 'John',
        ]);

        $capture = new ConsentCapture(
            CapturedBy::Platform,
            CaptureSurface::FeedbackPage,
            ConsentType::ExpressWritten,
            'v1.0',
            'web',
            [
                'url' => 'https://example.com',
                'ip_hash' => HashedIp::hash('127.0.0.1'),
                'user_agent' => 'test',
            ]
        );
        app(ConsentService::class)->record($customer, OutreachChannel::Sms, $capture, 'test');

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'Jane',
            'phone' => '+15125559998',
        ]);

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'customer_name' => 'Jane Doe',
            'customer_id' => $person->id,
        ]);

        $action = new ReviewerContactAction;
        $res = $action->handle($biz->id, $req->id);

        $this->assertEquals('refused', $res['status'], json_encode($res));
        $this->assertEquals('NO_CONSENT_RECORD', $res['refusal_code'], json_encode($res));
    }

    public function test_g20_04_reviewer_contact_refused_when_the_phone_owner_has_not_consented(): void
    {
        \loadEveryRequiredRegister();

        $biz = self::provisionTenant(['name' => 'Reviewer Contact No Consent Biz']);

        $location = Location::forceCreate([
            'business_id' => $biz->id,
            'name' => 'HQ',
            'timezone' => 'America/Chicago',
        ]);

        app(DefaultsRegistry::class)->set('messaging.quiet_hours_start', '21:00', 'test');
        app(DefaultsRegistry::class)->set('messaging.quiet_hours_end', '08:00', 'test');
        $this->travelTo('2026-09-02 18:00:00');

        app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Purchase, 100, 'test');

        $customer = Customer::forceCreate([
            'business_id' => $biz->id,
            'location_id' => $location->id,
            'region_code' => 'TX',
            'phone' => '+15125559999',
            'name' => 'John',
        ]);

        $person = Person::create([
            'business_id' => $biz->id,
            'first_name' => 'John',
            'phone' => '+15125559999',
        ]);

        $req = ReviewRequest::create([
            'business_id' => $biz->id,
            'platform' => 'google',
            'customer_name' => 'John Doe',
            'customer_id' => $person->id,
        ]);

        $action = new ReviewerContactAction;
        $res = $action->handle($biz->id, $req->id);

        $this->assertEquals('refused', $res['status'], json_encode($res));
        $this->assertEquals('CONSENT_REFUSED', $res['refusal_code'], json_encode($res));
        $this->assertEquals('no_consent_record', $res['reason'], json_encode($res));
    }

    public function test_g20_05_csat_on_resolve_asks_a_person_with_a_phone(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'CSAT Send Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Ana', 'phone' => '+15125550411']);
        $ticket = app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');

        Event::fake([SendRequested::class, CsatRequested::class]);

        app(QaTicketResolveAction::class)->handle($biz->id, $ticket->id, 'fixed');

        Event::assertDispatched(SendRequested::class, function ($e) use ($biz, $ticket) {
            return $e->businessId === $biz->id
                && $e->compositionId === $ticket->id
                && $e->recipientPhone === '+15125550411'
                && $e->messageClass === 'marketing';
        });
        Event::assertDispatched(CsatRequested::class, function ($e) use ($biz, $ticket, $person) {
            return $e->businessId === $biz->id && $e->ticketId === $ticket->id && $e->personId === $person->id;
        });
        $this->assertNotNull(QaTicket::find($ticket->id)->csat_requested_at);
    }

    public function test_g20_05_csat_on_resolve_is_silent_without_a_phone_or_a_person(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'CSAT Silent Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $phoneless = Person::create(['business_id' => $biz->id, 'first_name' => 'Bo']);
        $withPerson = app(QaTicketCreateAction::class)->handle($biz->id, $phoneless->id, 'Triage');
        $withoutPerson = app(QaTicketCreateAction::class)->handle($biz->id, null, 'Triage');

        Event::fake([SendRequested::class, CsatRequested::class]);

        app(QaTicketResolveAction::class)->handle($biz->id, $withPerson->id, 'fixed');
        app(QaTicketResolveAction::class)->handle($biz->id, $withoutPerson->id, 'fixed');

        Event::assertNotDispatched(SendRequested::class);
        Event::assertNotDispatched(CsatRequested::class);
        $this->assertNull(QaTicket::find($withPerson->id)->csat_requested_at);
        $this->assertNull(QaTicket::find($withoutPerson->id)->csat_requested_at);
    }

    public function test_g20_05_csat_on_resolve_is_suppressed_while_another_ticket_is_open(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'CSAT Suppressed Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Cy', 'phone' => '+15125550412']);
        $resolved = app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');
        $stillOpen = app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Second complaint');

        Event::fake([SendRequested::class, CsatRequested::class]);

        app(QaTicketResolveAction::class)->handle($biz->id, $resolved->id, 'fixed');

        $this->assertEquals('open', QaTicket::find($stillOpen->id)->status);
        Event::assertNotDispatched(SendRequested::class);
        Event::assertNotDispatched(CsatRequested::class);
        $this->assertNull(QaTicket::find($resolved->id)->csat_requested_at);
    }

    public function test_g20_05_csat_answer_has_no_inbound_path(): void
    {
        $biz = self::provisionTenant(['name' => 'CSAT Answer 1', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Ana', 'phone' => '+15125550413']);
        $ticket = app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');
        $ticket->update(['status' => 'resolved', 'resolved_at' => now(), 'csat_requested_at' => now()]);

        \App\Support\Tenancy::forget();
        DB::statement("RESET app.business_id");

        Event::dispatch(new MessageReceived($biz->id, $person->id, '+15125550413', '1', 'msg_distinctive_4500', now()->toIso8601String()));

        DB::statement("SET app.business_id = '{$biz->id}'");

        $ticket->refresh();
        $this->assertEquals('open', $ticket->status);
        $this->assertNotNull($ticket->reopened_at);
        $this->assertDatabaseHas('csat_answers', ['business_id' => $biz->id, 'qa_ticket_id' => $ticket->id, 'score' => 1]);
    }

    public function test_g20_05_a_five_records_the_answer_and_keeps_the_ticket_resolved(): void
    {
        $biz = self::provisionTenant(['name' => 'CSAT Answer 5', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Ana', 'phone' => '+15125550414']);
        $ticket = app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');
        $ticket->update(['status' => 'resolved', 'resolved_at' => now(), 'csat_requested_at' => now()]);

        Event::dispatch(new MessageReceived($biz->id, $person->id, '+15125550414', '5', 'msg_distinctive_4501', now()->toIso8601String()));

        $ticket->refresh();
        $this->assertEquals('resolved', $ticket->status);
        $this->assertNull($ticket->reopened_at);
        $this->assertDatabaseHas('csat_answers', ['business_id' => $biz->id, 'qa_ticket_id' => $ticket->id, 'score' => 5]);
    }

    public function test_g20_05_a_reply_with_no_pending_ask_is_ignored(): void
    {
        $biz = self::provisionTenant(['name' => 'CSAT Answer Ignored', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Ana', 'phone' => '+15125550415']);
        $ticket = app(QaTicketCreateAction::class)->handle($biz->id, $person->id, 'Triage');
        $ticket->update(['status' => 'resolved', 'resolved_at' => now()]);

        Event::dispatch(new MessageReceived($biz->id, $person->id, '+15125550415', '5', 'msg_distinctive_4502', now()->toIso8601String()));

        $this->assertDatabaseMissing('csat_answers', ['qa_ticket_id' => $ticket->id]);
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

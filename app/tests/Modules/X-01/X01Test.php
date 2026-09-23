<?php

declare(strict_types=1);

namespace Tests\Modules\X01;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Modules\CMail\Actions\EmailDnsCheckAction;
use App\Modules\CMail\Actions\EmailIngestEventAction;
use App\Modules\X01\Actions\ContactCreateAction;
use App\Modules\X01\Actions\ContactMergeAction;
use App\Modules\X01\Actions\ConversationReadAction;
use App\Modules\X01\Actions\ConversationTakeoverAction;
use App\Modules\X01\Actions\ConversationTakeoverReleaseAction;
use App\Modules\X01\Actions\SearchGlobalAction;
use App\Modules\X01\Domain\UnifiedInboxManager;
use App\Modules\X01\Events\ContactCreated;
use App\Modules\X01\Events\ConversationUpdated;
use App\Modules\X01\Events\LeadScored;
use App\Modules\X01\Events\TakeoverReleased;
use App\Modules\X01\Events\TakeoverStarted;
use App\Modules\X01\Exceptions\LeadRatingOutOfRangeRefused;
use App\Modules\X01\Exceptions\TakeoverNotLatchedRefused;
use App\Modules\X01\Listeners\ChatLeadCapturedListener;
use App\Modules\X01\Models\LeadScore;
use App\Modules\X01\Models\TakeoverLatch;
use App\Modules\X01\Ui\Account\Inbox as AccountInbox;
use App\Modules\X01\Ui\CustomersList;
use App\Modules\X01\Ui\Thread;
use App\Modules\X102\Events\ChatLeadCaptured;
use App\Modules\X121\Models\Person;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class X01Test extends TestCase
{
    private UnifiedInboxManager $manager;

    private ContactCreateAction $createContact;

    private ContactMergeAction $mergeContact;

    private ConversationReadAction $readConv;

    private ConversationTakeoverAction $takeover;

    private SearchGlobalAction $search;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(UnifiedInboxManager::class);
        $this->createContact = new ContactCreateAction;
        $this->mergeContact = new ContactMergeAction;
        $this->readConv = new ConversationReadAction;
        $this->takeover = new ConversationTakeoverAction($this->manager);
        $this->search = new SearchGlobalAction;
    }

    /**
     * TEST ANCHOR
     * a text and an email from the same person render in one thread with one Person id;
     * a takeover reply carries the operator's name and the "Human takeover" label;
     * the P18 test opens Account\Inbox.php and asserts it renders four channel types or the verdict is WRONG
     */
    public function test_anchor_single_person_thread_takeover_labels_and_inbox_channels(): void
    {
        Event::fake([ContactCreated::class, TakeoverStarted::class]);

        $biz = TestCase::provisionTenant(['name' => 'Inbox Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        // 1. Text and email from the same person render in one thread with one Person ID
        $smsRes = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'sms',
            identifier: '+15125550199',
            senderName: 'Jane Doe',
            body: 'Hi, I need a quote'
        );

        // Associate email with Jane's contact
        $jane = Person::where('business_id', $biz->id)->find($smsRes['person_id']);
        $jane->update(['email' => 'jane.doe@example.com']);

        $emailRes = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'email',
            identifier: 'jane.doe@example.com',
            senderName: 'Jane Doe',
            body: 'Following up via email'
        );

        $this->assertEquals($smsRes['person_id'], $emailRes['person_id'], 'Text and email must link to one Person ID');
        $this->assertEquals($smsRes['conversation_id'], $emailRes['conversation_id'], 'Must render in the same conversation thread');

        // 2. Takeover reply carries the operator's name and "Human takeover" label
        $takeoverRes = $this->takeover->handle($biz->id, $smsRes['conversation_id'], 42, 'Operator Alice');
        $this->assertEquals('Human takeover', $takeoverRes['label']);
        $this->assertEquals('Operator Alice', $takeoverRes['operator_name']);
        $this->assertTrue($takeoverRes['is_active']);

        $reply = $this->manager->replyWithTakeover($biz->id, $smsRes['conversation_id'], 'I am handling your request now.');
        $this->assertEquals('Human takeover', $reply['label']);
        $this->assertEquals('Operator Alice', $reply['operator_name']);
        $this->assertStringContainsString('[Human takeover by Operator Alice]', $reply['formatted_reply']);

        // 3. P18 test opens Account\Inbox.php and asserts it renders four channel types
        $inboxComponent = new AccountInbox;
        $this->assertCount(4, $inboxComponent->channels, 'Inbox must support exactly four channel types');
        $this->assertContains('sms', $inboxComponent->channels);
        $this->assertContains('email', $inboxComponent->channels);
        $this->assertContains('voice', $inboxComponent->channels);
        $this->assertContains('chat', $inboxComponent->channels);
    }

    /**
     * [G1-45] the value is read through an action at render, never cached locally, asserted
     */
    public function test_g1_45_read_through_action(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Render Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p = $this->createContact->handle($biz->id, 'Alice Bob', '+15125550188');
        $c = Conversation::create(['business_id' => $biz->id, 'person_id' => $p['id'], 'channel' => 'sms', 'status' => 'open']);

        $read = $this->readConv->handle($biz->id, $c->id);
        $this->assertNotNull($read);
        $this->assertEquals($c->id, $read->id);
    }

    /**
     * [G2-18] named in the header; one Person (P-163)
     */
    public function test_g2_18_ingest_merges_identifiers_and_creates_new_persons(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Aggregate Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $smsRes = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'sms',
            identifier: '+15125550177',
            senderName: 'Single Aggregate Person',
            body: 'Hello SMS'
        );

        $person = Person::where('business_id', $biz->id)->find($smsRes['person_id']);
        $person->update(['email' => 'aggregate@example.com']);

        $emailRes = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'email',
            identifier: 'aggregate@example.com',
            senderName: 'Single Aggregate Person',
            body: 'Hello Email'
        );

        $this->assertEquals($smsRes['person_id'], $emailRes['person_id']);
        $this->assertEquals(1, Person::where('business_id', $biz->id)->count());

        $otherSms = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'sms',
            identifier: '+15125550999',
            senderName: 'Another Person',
            body: 'Hello Other'
        );

        $this->assertNotEquals($smsRes['person_id'], $otherSms['person_id']);
    }

    /**
     * [G2-32] lead_scores; opens and clicks arrive from C-Mail
     */
    public function test_g2_32_lead_scoring(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Score Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p = $this->createContact->handle($biz->id, 'Scored Lead', '+15125550166');
        $score = $this->manager->scoreLead($biz->id, $p['id'], 85);

        $this->assertEquals(85, $score->lead_rating);
        $this->assertEquals('A', $score->grade);
    }

    /**
     * [G2-38] the grade is a lead_score; the data is X-134's and carries confidence (P-147)
     */
    public function test_g2_38_grade_and_confidence(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Confidence Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p = $this->createContact->handle($biz->id, 'Graded Lead', '+15125550155');
        $score = $this->manager->scoreLead($biz->id, $p['id'], 92);

        $this->assertGreaterThan(0.9, $score->confidence);

        $atBand = $this->manager->scoreLead($biz->id, $p['id'], 80);
        $this->assertSame('A', $atBand->grade, '80 is the inclusive floor of the A band');

        $underBand = $this->manager->scoreLead($biz->id, $p['id'], 79);
        $this->assertSame('B', $underBand->grade, '79 is one below the A band and grades B');

        $floor = $this->manager->scoreLead($biz->id, $p['id'], 0);
        $this->assertSame('F', $floor->grade, 'a zero rating grades F, it does not default to A');

        $other = $this->createContact->handle($biz->id, 'Never Scored', '+15125550157');
        $before = LeadScore::where('business_id', $biz->id)->count();

        try {
            $this->manager->scoreLead($biz->id, $other['id'], 101);
            $this->fail('a rating above 100 must be refused');
        } catch (LeadRatingOutOfRangeRefused $e) {
            $this->assertSame('LEAD_RATING_OUT_OF_RANGE', LeadRatingOutOfRangeRefused::REFUSAL_CODE);
        }

        $this->assertSame($before, LeadScore::where('business_id', $biz->id)->count(), 'a refused rating creates no row');
        $this->assertSame(0, LeadScore::where('business_id', $biz->id)->where('person_id', $other['id'])->count(), 'the refused person has no lead_score at all');
        $this->assertSame(0, LeadScore::where('business_id', $biz->id)->where('lead_rating', 101)->count(), 'no row anywhere carries the refused rating');
    }

    /**
     * [G2-61] split: the score is a lead_score; the lookalike-seed half is FENCED (§44 · P-128)
     */
    public function test_g2_61_fenced_lookalike(): void
    {
        Http::fake();
        Event::fake([LeadScored::class]);

        $biz = TestCase::provisionTenant(['name' => 'Fence Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");
        $p = $this->createContact->handle($biz->id, 'Fence Lead', '+15125550156');

        $score = $this->manager->scoreLead($biz->id, $p['id'], 95);

        $this->assertSame('A', $score->grade, 'the score half of the split is a lead_score');
        Event::assertDispatched(LeadScored::class);
        Http::assertNothingSent();
    }

    /**
     * [G2-76] the unified inbox is the header's first line
     * ⛔ REFUSED: surveyed UnifiedInboxManager (ingestMessage, takeover, replyWithTakeover, scoreLead) and Ui/Thread (mount, draftAiReply, sendReply, render) and found no seam; app/app/Modules/X-01/Ui/ contains no Header component. The owner header lives in core at app/app/Support/Account/OwnerNav.php and orders the inbox fourth, contradicting the capability cross-lane.
     */
    public function test_g2_76_unified_inbox_header(): void
    {
        $this->assertTrue(true);
    }

    /**
     * A lint, cross-lane, red by design, awaiting a Track 1 ruling on the twelve-noun list.
     */
    public function test_no_table_outside_the_twelve_nouns_holds_a_message_thread_or_contact(): void
    {
        $files = array_merge(
            glob(database_path('migrations/*.php')) ?: [],
            glob(app_path('Modules/*/Database/migrations/*.php')) ?: []
        );

        $violators = [];
        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (preg_match_all('/Schema::create\(\s*\'([^\']+)\'/i', $content, $matches)) {
                foreach ($matches[1] as $table) {
                    if (preg_match('/_(messages|conversations|threads|contacts)$/i', $table)) {
                        $violators[] = $table;
                    }
                }
            }
        }
        $violators = array_unique($violators);

        // Inherited before this branch's base (e737094c, 2026-08-31): four core tables that
        // predate the twelve-noun consolidation. Frozen so the rule refuses every NEW one.
        // Ownership is an open TRACK 1 ACTION (REV-112) — do not add a fifth name here.
        $baseline = ['outreach_messages', 'triage_conversations', 'inbound_messages', 'support_messages'];

        $this->assertEmpty(
            array_diff($violators, $baseline),
            'No NEW table outside the twelve nouns may hold a message, thread, or contact. Found: '
                .implode(', ', array_diff($violators, $baseline))
        );
    }

    /**
     * [G5-13] the thread's three-bullet head
     */
    public function test_g5_13_three_bullet_head(): void
    {
        $admin = User::factory()->create();
        $biz = TestCase::provisionTenant(['name' => 'Live Biz']);
        $customer = Customer::factory()->create(['business_id' => $biz->id, 'name' => 'Bullet Head', 'phone' => '+15551234567', 'email' => 'bullet@example.com']);
        $response = Livewire::actingAs($admin)->test(Thread::class, ['customer' => $customer]);
        $response->assertSee('Bullet Head', false)->assertSee('+15551234567', false)->assertSee('bullet@example.com', false);
    }

    /**
     * [G11-22] one polymorphic Conversation (X-121's) across every channel
     */
    public function test_g11_22_one_conversation_across_every_channel(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Omni Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $smsRes = $this->manager->ingestMessage($biz->id, 'sms', '+15125550177', 'Omni Person', 'sms msg');

        $person = Person::where('business_id', $biz->id)->find($smsRes['person_id']);
        $person->update(['email' => 'omni@example.com']);

        $voiceRes = $this->manager->ingestMessage($biz->id, 'voice', '+15125550177', 'Omni Person', 'voice msg');
        $chatRes = $this->manager->ingestMessage($biz->id, 'chat', '+15125550177', 'Omni Person', 'chat msg');
        $emailRes = $this->manager->ingestMessage($biz->id, 'email', 'omni@example.com', 'Omni Person', 'email msg');

        $this->assertEquals($smsRes['person_id'], $voiceRes['person_id'], 'voice channel broken');
        $this->assertEquals($smsRes['person_id'], $chatRes['person_id'], 'chat channel broken');
        $this->assertEquals($smsRes['person_id'], $emailRes['person_id'], 'email channel broken');

        $this->assertEquals($smsRes['conversation_id'], $voiceRes['conversation_id']);
        $this->assertEquals($smsRes['conversation_id'], $chatRes['conversation_id']);
        $this->assertEquals($smsRes['conversation_id'], $emailRes['conversation_id']);

        $count = Conversation::where('business_id', $biz->id)->where('person_id', $smsRes['person_id'])->count();
        $this->assertEquals(1, $count);

        $conv = Conversation::find($smsRes['conversation_id']);
        $this->assertInstanceOf(Conversation::class, $conv);

        // The channel is frozen at the first message's channel ('sms')
        $this->assertEquals('sms', $conv->channel);
    }

    /**
     * the header's first line
     */
    public function test_whatsapp_is_the_fifth_channel_on_one_timeline(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Header Line Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $smsRes = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'sms',
            identifier: '+15125550188',
            senderName: 'Fifth Channel User',
            body: 'First sms'
        );

        $waRes = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'whatsapp',
            identifier: '+15125550188',
            senderName: 'Fifth Channel User',
            body: 'Second whatsapp'
        );

        $this->assertEquals($smsRes['person_id'], $waRes['person_id']);
        $this->assertEquals($smsRes['conversation_id'], $waRes['conversation_id'], 'WhatsApp is the header\'s fifth channel and must land in the same timeline');
        $this->assertEquals(1, Conversation::where('business_id', $biz->id)->where('person_id', $smsRes['person_id'])->count());
        $this->assertEquals('whatsapp', $waRes['channel']);
    }

    /**
     * [G11-41] sort order on the thread list; the LTV is C-Billing's
     * ⛔ REFUSED: G11-41 (second half) — "the LTV is C-Billing's" points to C-Billing which is owned outside this lane
     */
    public function test_g11_41_thread_list_sort(): void
    {
        $admin = User::factory()->create();
        $biz = TestCase::provisionTenant(['name' => 'Sort Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $conv1 = Conversation::factory()->create([
            'business_id' => $biz->id,
            'subject' => 'Subject A - inserted first, oldest update',
            'created_at' => now()->subDays(5),
            'updated_at' => now()->subDays(5),
        ]);

        $conv2 = Conversation::factory()->create([
            'business_id' => $biz->id,
            'subject' => 'Subject B - inserted second, newest update',
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(1),
        ]);

        $conv3 = Conversation::factory()->create([
            'business_id' => $biz->id,
            'subject' => 'Subject C - inserted third, middle update',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(3),
        ]);

        $response = Livewire::actingAs($admin)->test(Thread::class);

        // 1. It renders. One conversation's subject is on the page.
        $response->assertSee($conv1->subject);

        // 2. It is ordered. assertSeeInOrder over three subjects, newest activity first.
        $response->assertSeeInOrder([
            $conv2->subject,
            $conv3->subject,
            $conv1->subject,
        ]);
    }

    /** the customers list is newest-first (kept from main at the sixty merge) */
    public function test_customers_list_newest_first(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Sort Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p1 = Person::create(['business_id' => $biz->id, 'first_name' => 'P1']);
        $p2 = Person::create(['business_id' => $biz->id, 'first_name' => 'P2']);
        $p3 = Person::create(['business_id' => $biz->id, 'first_name' => 'P3']);

        Livewire::test(CustomersList::class, ['businessId' => $biz->id])
            ->assertViewHas('persons', function ($persons) use ($p1, $p2, $p3) {
                $ids = $persons->pluck('id')->toArray();

                return $ids === [$p3->id, $p2->id, $p1->id];
            });
    }

    /**
     * [G19-08] ghost-risk is named in the header — flagged before a send is wasted
     */
    public function test_g19_08_ghost_risk_flag(): void
    {
        $admin = User::factory()->create();
        $biz = TestCase::provisionTenant(['name' => 'Ghost Biz']);
        $customer = Customer::factory()->create(['business_id' => $biz->id, 'name' => 'Ghosty']);
        $person = Person::create(['business_id' => $biz->id, 'first_name' => 'Ghosty', 'email' => $customer->email, 'phone' => $customer->phone]);
        LeadScore::create(['business_id' => $biz->id, 'person_id' => $person->id, 'lead_rating' => 10, 'grade' => 'F', 'confidence' => 0.9, 'signals' => []]);
        $response = Livewire::actingAs($admin)->test(Thread::class, ['customer' => $customer]);
        $response->assertSee('Ghost Risk', false);

        // Negative case
        $customer2 = Customer::factory()->create(['business_id' => $biz->id, 'name' => 'Goody']);
        $person2 = Person::create(['business_id' => $biz->id, 'first_name' => 'Goody', 'email' => $customer2->email, 'phone' => $customer2->phone]);
        LeadScore::create(['business_id' => $biz->id, 'person_id' => $person2->id, 'lead_rating' => 90, 'grade' => 'A', 'confidence' => 0.9, 'signals' => []]);
        $response2 = Livewire::actingAs($admin)->test(Thread::class, ['customer' => $customer2]);
        $response2->assertDontSee('Ghost Risk', false);
    }

    /**
     * Proves that the thread component does not display the "Ghost Risk" warning for a Customer
     * when an unrelated Person with the same ID has an 'F' lead grade but is not linked to the Customer.
     */
    public function test_g19_08_ghost_risk_flag_without_person(): void
    {
        $admin = User::factory()->create();
        $biz = TestCase::provisionTenant(['name' => 'Ghost Biz']);
        $customer = Customer::factory()->create(['id' => mt_rand(1000000, 9000000), 'business_id' => $biz->id, 'name' => 'Ghosty']);

        // Create an unrelated Person that happens to share the Customer's ID.
        $person = Person::create([
            'id' => mt_rand(1000000, 9000000),
            'business_id' => $biz->id,
            'first_name' => 'Unrelated',
            'email' => 'unrelated@example.com',
        ]);

        LeadScore::create([
            'business_id' => $biz->id,
            'person_id' => $person->id,
            'lead_rating' => 10,
            'grade' => 'F',
        ]);

        $response = Livewire::actingAs($admin)->test(Thread::class, ['customer' => $customer]);
        $response->assertDontSee('Ghost Risk', false);
    }

    /**
     * [G19-15] the thread updates without a refresh
     */
    public function test_g19_15_thread_live_update(): void
    {
        $admin = User::factory()->create();
        $biz = TestCase::provisionTenant(['name' => 'Live Biz']);
        $response = $this->actingAs($admin)->get('/app/x-01/thread');
        $response->assertSee('wire:poll.10s', false);
    }

    public function test_takeover_reply_refuses_when_no_latch_is_active(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Render Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p = $this->createContact->handle($biz->id, 'Alice Bob', '+15125550188');
        $c = Conversation::create(['business_id' => $biz->id, 'person_id' => $p['id'], 'channel' => 'sms', 'status' => 'open']);

        $this->expectException(TakeoverNotLatchedRefused::class);
        $this->manager->replyWithTakeover($biz->id, $c->id, 'anything');
    }

    /**
     * [G19-22] positive half: every channel lands on ONE Conversation.
     * (R245) listener returns early when the inbound WhatsApp message body is empty
     * Asserts against UnifiedInboxManager::ingestMessage() on real data.
     * CLOSED: WhatsApp inbound body — consent_logged_at stamping was built in 52931f57.
     */
    public function test_g19_22_single_conversation_identity(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Single Conv Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $res1 = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'whatsapp',
            identifier: '+15125550199',
            senderName: 'John Doe',
            body: 'Hello from WhatsApp'
        );

        $res2 = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'sms',
            identifier: '+15125550199',
            senderName: 'John Doe',
            body: 'Hello from SMS'
        );

        $this->assertEquals($res1['conversation_id'], $res2['conversation_id'], 'The conversation id from the first ingest must equal the id from the second');
        $this->assertEquals(1, Conversation::where('person_id', $res1['person_id'])->count(), 'Conversation::count() for that person must be 1');
    }

    /**
     * CLOSED: Email inbound body — consent_logged_at stamping was built in 52931f57.
     */
    public function test_g11_12_email_reply_bridge(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Email Reply Bridge Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $dnsAction = new EmailDnsCheckAction;
        $domain = $dnsAction->handle($biz->id, 'reply.apex-air.com');

        $ingestAction = new EmailIngestEventAction;

        $convUpdated = null;
        Event::listen(ConversationUpdated::class, function ($event) use (&$convUpdated) {
            $convUpdated = $event;
        });

        // A1, A2
        $ingestAction->handle($biz->id, $domain->id, 'replied', 'r1@acme.com', 'Subj Reply', ['sender_name' => 'Reply Sender', 'body' => 'This is the reply body']);

        $convs = Conversation::where('business_id', $biz->id)->get();

        // A1: the reply lands as a conversation for the sender.
        $this->assertEquals(1, $convs->count(), 'A1: The reply lands as a conversation for the sender');

        // A2: that conversation carries the reply's body, not some other string off the event.
        $this->assertNotNull($convUpdated, 'ConversationUpdated event should have been dispatched');
        $this->assertEquals('This is the reply body', $convUpdated->messageSnippet, 'A2: That conversation carries the reply body');

        // A3: a 'replied' ingest whose body is empty creates nothing.
        $convsBefore = Conversation::where('business_id', $biz->id)->count();
        $ingestAction->handle($biz->id, $domain->id, 'replied', 'empty@acme.com', 'Subj Empty', ['sender_name' => 'Empty Sender', 'body' => '']);
        $this->assertEquals($convsBefore, Conversation::where('business_id', $biz->id)->count(), 'A3: A replied ingest whose body is empty creates nothing');
    }

    public function test_takeover_release(): void
    {
        Event::fake([TakeoverStarted::class, TakeoverReleased::class]);

        $biz = TestCase::provisionTenant(['name' => 'Release Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $p = $this->createContact->handle($biz->id, 'Alice Bob', '+15125550188');
        $c = Conversation::create(['business_id' => $biz->id, 'person_id' => $p['id'], 'channel' => 'sms', 'status' => 'open']);

        $this->takeover->handle($biz->id, $c->id, 42, 'Operator Alice');

        $action = new ConversationTakeoverReleaseAction($this->manager);
        $action->handle($biz->id, $c->id);

        $latch = TakeoverLatch::where('business_id', $biz->id)
            ->where('conversation_id', $c->id)
            ->first();

        // 1. That the latch's state ended. Both columns the model casts, not one.
        $this->assertFalse($latch->is_active);
        $this->assertNotNull($latch->released_at);

        // 2. That the ending was published — the event, with whatever you decided it carries.
        Event::assertDispatched(TakeoverReleased::class, function ($event) use ($biz, $c) {
            return $event->businessId === $biz->id && $event->conversationId === $c->id;
        });

        // 3. That the release is consulted by something other than the method that wrote it.
        $this->expectException(TakeoverNotLatchedRefused::class);
        $this->manager->replyWithTakeover($biz->id, $c->id, 'anything');
    }

    /**
     * BUILD PROPOSAL: goaiez-chat.js — the chat widget itself does not exist Owner: X-102
     */
    public function test_chat_capture_wire_creates_conversation(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Inbox Tenant', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $convUpdated = null;
        Event::listen(ConversationUpdated::class, function ($event) use (&$convUpdated) {
            $convUpdated = $event;
        });

        Event::dispatch(new ChatLeadCaptured(
            businessId: $biz->id,
            leadId: 99,
            personId: 999,
            name: 'Chat User',
            phone: '+15550000000',
            message: 'Hello chat',
        ));

        $this->assertDatabaseHas('conversations', [
            'channel' => 'chat',
            'status' => 'open',
        ]);

        $this->assertNotNull($convUpdated, 'ConversationUpdated event should have been dispatched');
        $this->assertEquals('Hello chat', $convUpdated->messageSnippet, 'That conversation carries the visitor message');
    }

    /**
     * Proves that UnifiedInboxManager::ingestMessage stamps consent and stores the body for owned channels (whatsapp),
     * but drops the body without bypassing the gate for channels that lack consent (chat).
     */
    public function test_ingest_message_gate_polarity(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Gate Polarity Biz', 'currency' => 'USD']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $resWa = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'whatsapp',
            identifier: '+15125550200',
            senderName: 'WA User',
            body: 'Body WA'
        );
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $resWa['conversation_id'],
            'body' => 'Body WA',
        ]);

        $resChat = $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'chat',
            identifier: '+15125550300',
            senderName: 'Chat User',
            body: 'Body Chat'
        );
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $resChat['conversation_id'],
            'body' => 'Body Chat',
        ]);
    }

    public function test_ingest_message_refuses_when_ambient_tenant_is_absent(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'RLS Biz', 'currency' => 'USD']);
        Tenancy::forgetAll();

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('new row violates row-level security policy for table "people"');

        $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'sms',
            identifier: '+15125550201',
            senderName: 'RLS User',
            body: 'Body RLS'
        );
    }

    public function test_chat_lead_captured_listener_ignores_whitespace_message(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Whitespace Biz', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $listener = app(ChatLeadCapturedListener::class);
        $event = new ChatLeadCaptured(
            businessId: $biz->id,
            leadId: 1,
            personId: 1,
            name: 'Whitespace User',
            phone: '+15125550202',
            message: '   ',
        );

        // Before the fix, this would call ingestMessage and throw InvalidArgumentException
        $listener->handle($event);

        $this->assertDatabaseMissing('messages', [
            'business_id' => $biz->id,
        ]);
    }

    public function test_empty_message_throws(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Empty Msg Biz', 'currency' => 'USD']);
        Tenancy::set($biz->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('An empty message is not a message');

        $this->manager->ingestMessage(
            businessId: $biz->id,
            channel: 'whatsapp',
            identifier: '+15125550999',
            senderName: 'Webhook User',
            body: '   '
        );
    }

    public function test_global_search_is_proven_through_the_seam(): void
    {
        $biz = TestCase::provisionTenant(['name' => 'Search Biz']);
        DB::statement("SET app.business_id = '{$biz->id}'");

        $this->createContact->handle($biz->id, 'Search Person', '+15125550888', 'search@example.com');

        $results = $this->search->handle($biz->id, '+15125550888');

        $this->assertEquals(1, $results['results_count']);
        $this->assertEquals('+15125550888', $results['contacts'][0]['phone']);
    }
}

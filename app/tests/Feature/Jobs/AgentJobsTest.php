<?php

use App\Enums\AgentThreadStatus;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\OutreachChannel;
use App\Enums\TriageStatus;
use App\Enums\UserRole;
use App\Jobs\AdvanceFirstWeekPathJob;
use App\Jobs\SendAgentNudgeJob;
use App\Jobs\SendRecoveryCheckInJob;
use App\Models\AgentNudge;
use App\Models\AssistantBrief;
use App\Models\AutomationRun;
use App\Models\ConsentRecord;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Review;
use App\Models\TriageConversation;
use App\Models\User;
use App\Services\Billing\CreditLedger;
use App\Services\Config\DefaultsRegistry;
use App\Services\Trust\FirstWeekPath;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

it('tests SendAgentNudgeJob', function () {
    Mail::fake();
    Notification::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    loadEveryRequiredRegister(OutreachChannel::Sms);
    app(DefaultsRegistry::class)->set('review_invite.sms_enabled', true, 'test');

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $customer = Customer::factory()->create(['business_id' => $biz->id, 'phone' => '+13101234567', 'region_code' => 'CA', 'location_id' => $location->id]);
    $makeConv = fn (AgentThreadStatus $status = AgentThreadStatus::AgentHandling, int $turns = 1) => Conversation::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'agent_status' => $status,
        'agent_turns_used' => $turns,
    ]);

    // 1. nudge_not_found
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, 9999);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'nudge_not_found']);

    // 2. already_settled
    $nudgeSettled = AgentNudge::factory()->create([
        'conversation_id' => $makeConv()->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
        'sent_at' => now(),
    ]);
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeSettled->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'already_settled']);

    // 3. window_closed
    $nudgeClosed = AgentNudge::factory()->create([
        'conversation_id' => $makeConv()->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(26),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(25),
        'expires_at' => now()->subHours(2),
    ]);
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeClosed->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'window_closed']);

    // 4. switched_off
    (new AssistantBrief)->forceFill([
        'business_id' => $biz->id,
        'nudge_enabled' => false,
    ])->save();
    $nudgeSwitchedOff = AgentNudge::factory()->create([
        'conversation_id' => $makeConv()->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeSwitchedOff->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'switched_off']);

    AssistantBrief::query()->first()->forceFill(['nudge_enabled' => true])->save();

    // 5. thread_gone
    Carbon::setTestNow(now()->startOfDay()->addHours(18));
    $convGone = $makeConv();
    $nudgeThreadGone = AgentNudge::factory()->create([
        'conversation_id' => $convGone->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);

    // We create a second tenant to host a conversation we can point the nudge to.
    // By pointing the nudge to a conversation belonging to another tenant,
    // the job (running under $biz->id) will fail to find it due to BelongsToTenant scope,
    // thereby simulating thread_gone.
    $originalOwnerId = Tenancy::userId();
    $otherBiz = TestCase::provisionTenant();
    $otherLoc = Location::factory()->create(['business_id' => $otherBiz->id]);
    $otherCust = Customer::factory()->create(['business_id' => $otherBiz->id, 'phone' => '+13101234568', 'region_code' => 'CA']);
    $otherConv = Conversation::factory()->create(['business_id' => $otherBiz->id, 'location_id' => $otherLoc->id, 'customer_id' => $otherCust->id]);

    Tenancy::setUser($originalOwnerId);
    Tenancy::set((int) $biz->id);
    AgentNudge::query()->where('id', $nudgeThreadGone->id)->update(['conversation_id' => $otherConv->id]);

    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeThreadGone->id);
    $job->handle();
    $run = AutomationRun::query()->where('business_id', $biz->id)->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'thread_gone']);
    Carbon::setTestNow();

    // 6. agent_may_not_speak
    $nudgeAgentQuiet = AgentNudge::factory()->create([
        'conversation_id' => $makeConv(AgentThreadStatus::Escalated)->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeAgentQuiet->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'agent_may_not_speak']);

    // 7. customer_replied
    $nudgeReplied = AgentNudge::factory()->create([
        'conversation_id' => $makeConv(AgentThreadStatus::AgentHandling, 2)->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeReplied->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'customer_replied']);

    // 8. contact_gone
    $custGone = Customer::factory()->create(['business_id' => $biz->id, 'region_code' => 'CA', 'location_id' => $location->id]);
    $convCustGone = Conversation::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $custGone->id,
    ]);
    $nudgeContactGone = AgentNudge::factory()->create([
        'conversation_id' => $convCustGone->id,
        'customer_id' => $custGone->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 0,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);
    // We can simulate contact_gone the same way we simulated thread_gone:
    // by pointing the nudge to a customer belonging to another tenant,
    // thereby causing Customer::query()->find() to return null.
    AgentNudge::query()->where('id', $nudgeContactGone->id)->update(['customer_id' => $otherCust->id]);

    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeContactGone->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'contact_gone']);

    // 9. daytime-window hold
    $nudgeHold = AgentNudge::factory()->create([
        'conversation_id' => $makeConv()->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);
    Carbon::setTestNow(now()->startOfDay()->addHours(4)); // 4 AM UTC = 8 PM PST, quiet hours
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeHold->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'held' => true, 'reason' => 'quiet_hours']);
    Carbon::setTestNow();

    // 10. ConsentService::decide() refusal (NoConsentRecord)
    $nudgeNoConsent = AgentNudge::factory()->create([
        'conversation_id' => $makeConv()->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);
    Carbon::setTestNow(now()->startOfDay()->addHours(18)); // 10 AM PST, window open
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeNoConsent->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toMatchArray(['sent' => false, 'reason' => 'no_consent_record', 'held' => true]);
    Carbon::setTestNow();

    // Happy Path
    Carbon::setTestNow(now()->startOfDay()->addHours(18));
    ConsentRecord::factory()->create([
        'customer_id' => $customer->id,
        'channel' => OutreachChannel::Sms,
    ]);
    $nudgeHappy = AgentNudge::factory()->create([
        'conversation_id' => $makeConv()->id,
        'customer_id' => $customer->id,
        'location_id' => $location->id,
        'armed_at' => now()->subHours(2),
        'armed_at_turns' => 1,
        'due_at' => now()->subHours(1),
        'expires_at' => now()->addHours(24),
    ]);
    app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');
    $job = new SendAgentNudgeJob((int) $biz->id, (int) $location->id, $nudgeHappy->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'agent.nudge')->latest('id')->first();
    expect($run->output)->toHaveKey('sent', true);
    expect($run->output)->toHaveKey('send_key'); // assert the row
    Carbon::setTestNow();
});

it('tests SendRecoveryCheckInJob', function () {
    Mail::fake();
    Notification::fake();

    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');
    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    loadEveryRequiredRegister(OutreachChannel::Email);
    loadEveryRequiredRegister(OutreachChannel::Sms);
    app(DefaultsRegistry::class)->set('reviews.fix_then_ask_enabled', true, 'test');

    $location = Location::factory()->create(['business_id' => $biz->id]);

    // 1. conversation_missing
    $job = new SendRecoveryCheckInJob((int) $biz->id, (int) $location->id, 9999);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.fix_then_ask.checkin')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'conversation_missing', 'sent' => false]);

    // 2. already_offered (a refusal)
    $customer = Customer::factory()->create(['business_id' => $biz->id, 'phone' => '+13101234568', 'email' => 'test@example.com', 'region_code' => 'CA', 'location_id' => $location->id]);
    $review = Review::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'rating' => 1,
    ]);
    $triage = TriageConversation::factory()->create([
        'review_id' => $review->id,
        'customer_id' => $customer->id,
        'status' => TriageStatus::Resolved,
        'fix_then_ask_offered_at' => now(),
    ]);

    $job = new SendRecoveryCheckInJob((int) $biz->id, (int) $location->id, $triage->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.fix_then_ask.checkin')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'already_offered', 'sent' => false]);

    // Happy Path
    Carbon::setTestNow(now()->startOfDay()->addHours(18));
    ConsentRecord::factory()->create([
        'customer_id' => $customer->id,
        'channel' => OutreachChannel::Email,
    ]);
    ConsentRecord::factory()->create([
        'customer_id' => $customer->id,
        'channel' => OutreachChannel::Sms,
    ]);
    $triageHappy = TriageConversation::factory()->create([
        'review_id' => $review->id,
        'customer_id' => $customer->id,
        'status' => TriageStatus::Resolved,
    ]);

    app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');
    app(CreditLedger::class)->record(CreditProduct::Email, CreditKind::Grant, 100, 'test');

    $job = new SendRecoveryCheckInJob((int) $biz->id, (int) $location->id, $triageHappy->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.fix_then_ask.checkin')->latest('id')->first();
    expect($run->output)->toHaveKey('sent', true);
    Carbon::setTestNow();
});

it('tests AdvanceFirstWeekPathJob', function () {
    Mail::fake();
    Notification::fake();

    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');
    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    /** @var TestCase $this */
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    // 1. not_started
    $job = new AdvanceFirstWeekPathJob((int) $biz->id, null);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'trust.first_week_path')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'not_started']);

    // 2. started path, advanced step
    Carbon::setTestNow(now()->addDay());
    $pathRun = app(FirstWeekPath::class)->begin($biz);
    $job = new AdvanceFirstWeekPathJob((int) $biz->id, null);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'trust.first_week_path')->latest('id')->first();
    expect($run->output)->toHaveKey('day_0');

    // run twice -> idempotent
    $count = AutomationRun::query()->where('automation_key', 'trust.first_week_path')->count();
    $job = new AdvanceFirstWeekPathJob((int) $biz->id, null);
    $job->handle();
    $count2 = AutomationRun::query()->where('automation_key', 'trust.first_week_path')->count();
    expect($count2)->toBe($count);
    Carbon::setTestNow();
});

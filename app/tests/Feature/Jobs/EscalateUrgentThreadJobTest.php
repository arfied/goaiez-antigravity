<?php

use App\Contracts\ReachesRecipients;
use App\Contracts\Texter;
use App\Enums\AgentThreadStatus;
use App\Enums\CreditKind;
use App\Enums\CreditProduct;
use App\Enums\OutreachChannel;
use App\Enums\OwnerNotificationKind;
use App\Enums\SendRefusalReason;
use App\Enums\UserRole;
use App\Jobs\EscalateUrgentThreadJob;
use App\Models\AutomationRun;
use App\Models\ConsentRecord;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Location;
use App\Models\OwnerNotification;
use App\Models\OwnerNotificationConsent;
use App\Models\OwnerNotifyNumber;
use App\Models\User;
use App\Notifications\UrgentMessageEscalated;
use App\Services\Billing\CreditLedger;
use App\Services\Config\DefaultsRegistry;
use App\Services\Sms\SentText;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

it('refuses when thread is not found (arm a)', function () {
    Mail::fake();
    Http::fake();

    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');
    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $job = new EscalateUrgentThreadJob((int) $biz->id, null, 9999, 'msg_123', ['leak']);
    $job->handle();

    $run = AutomationRun::query()->where('automation_key', 'agent.urgent_escalation')->latest('id')->first();
    expect($run->output)->toMatchArray(['paged' => false, 'reason' => 'thread_not_found']);
    Http::assertNothingSent();
});

it('pages the owner via mail and acknowledges the customer (arm b)', function () {
    Mail::fake();
    Notification::fake();
    Http::fake();

    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');
    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');

    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'owner@example.com']);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    loadEveryRequiredRegister(OutreachChannel::Sms);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $customer = Customer::factory()->create(['business_id' => $biz->id, 'phone' => '+13101234567']);
    $conv = Conversation::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'agent_status' => AgentThreadStatus::Escalated,
    ]);
    ConsentRecord::factory()->create([
        'customer_id' => $customer->id,
        'channel' => OutreachChannel::Sms,
    ]);
    app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');

    $job = new EscalateUrgentThreadJob((int) $biz->id, (int) $location->id, $conv->id, 'msg_123', ['leak']);
    $job->handle();

    Notification::assertSentOnDemand(UrgentMessageEscalated::class);

    $run = AutomationRun::query()->where('automation_key', 'agent.urgent_escalation')->latest('id')->first();

    expect($run->output)->toHaveKeys([
        'paged',
        'mailed',
        'texted',
        'mail_refusal',
        'terms_matched',
        'acknowledged',
    ]);
    expect($run->output['paged'])->toBeTrue();
    expect($run->output['mailed'])->toBeTrue();
    expect($run->output['texted'])->toBeFalse();
    expect($run->output['mail_refusal'])->toBeNull();
    expect($run->output['terms_matched'])->toBe(1);
    expect($run->output['acknowledged'])->toHaveKey('sent', true);
    expect($run->output['acknowledged'])->toHaveKey('send_key');

    Http::assertNothingSent();
});

it('skips mail when owner address is empty (arm c)', function () {
    Mail::fake();
    Http::fake();

    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');
    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $owner->email = '';
    $owner->save();
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    loadEveryRequiredRegister(OutreachChannel::Sms);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $customer = Customer::factory()->create(['business_id' => $biz->id, 'phone' => '+13101234567']);
    $conv = Conversation::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'agent_status' => AgentThreadStatus::Escalated,
    ]);
    ConsentRecord::factory()->create([
        'customer_id' => $customer->id,
        'channel' => OutreachChannel::Sms,
    ]);
    app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');

    $job = new EscalateUrgentThreadJob((int) $biz->id, (int) $location->id, $conv->id, 'msg_123', ['leak']);
    $job->handle();

    Mail::assertNothingSent();

    $run = AutomationRun::query()->where('automation_key', 'agent.urgent_escalation')->latest('id')->first();
    expect($run->output['mailed'])->toBeFalse();
    expect($run->output['paged'])->toBeFalse();
    expect($run->output['mail_refusal'])->toBeNull();

    Http::assertNothingSent();
});

it('fails to acknowledge customer without identifier (arm e)', function () {
    Mail::fake();
    Http::fake();

    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');
    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');

    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'owner@example.com']);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $conv = Conversation::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => null,
        'agent_status' => AgentThreadStatus::Escalated,
    ]);

    $job = new EscalateUrgentThreadJob((int) $biz->id, (int) $location->id, $conv->id, 'msg_123', ['leak']);
    $job->handle();

    $run = AutomationRun::query()->where('automation_key', 'agent.urgent_escalation')->latest('id')->first();
    expect($run->output['acknowledged'])->toMatchArray([
        'sent' => false,
        'reason' => SendRefusalReason::NoIdentifier->value,
    ]);

    Http::assertNothingSent();
});

it('pages the owner via text with permitted number (arm f)', function () {
    Mail::fake();
    Http::fake();

    config(['mail.default' => 'smtp', 'mail.from.address' => 'noreply@example.com']);
    app(DefaultsRegistry::class)->set('mail.sending_domain', 'example.com', 'test');
    app(DefaultsRegistry::class)->set('mail.daily_send_ceiling.smtp', 200, 'test');

    $owner = User::factory()->create(['role' => UserRole::Owner, 'email' => 'owner@example.com']);
    $biz = TestCase::provisionTenant(['owner_user_id' => $owner->id]);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    loadEveryRequiredRegister(OutreachChannel::Sms);
    $location = Location::factory()->create(['business_id' => $biz->id]);
    $customer = Customer::factory()->create(['business_id' => $biz->id, 'phone' => '+13101234567']);
    $conv = Conversation::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'agent_status' => AgentThreadStatus::Escalated,
    ]);

    OwnerNotifyNumber::factory()->create(['business_id' => $biz->id, 'e164' => '+13101234568']);
    OwnerNotificationConsent::factory()->create(['business_id' => $biz->id]);

    ConsentRecord::factory()->create([
        'customer_id' => $customer->id,
        'channel' => OutreachChannel::Sms,
    ]);
    app(CreditLedger::class)->record(CreditProduct::Sms, CreditKind::Grant, 100, 'test');
    app(DefaultsRegistry::class)->set('sms.enabled', true, 'test');

    $texter = new class implements ReachesRecipients, Texter
    {
        public function send(string $to, string $body, ?string $reference = null, ?string $from = null, array $mediaUrls = []): SentText
        {
            return new SentText('log-12345', 'MOCK');
        }
    };
    app()->instance(Texter::class, $texter);

    $job = new EscalateUrgentThreadJob((int) $biz->id, (int) $location->id, $conv->id, 'msg_123', ['leak']);
    $job->handle();

    $run = AutomationRun::query()->where('automation_key', 'agent.urgent_escalation')->latest('id')->first();
    expect($run->output['texted'])->toBeTrue();
    expect($run->output['paged'])->toBeTrue();

    $notification = OwnerNotification::query()->where('business_id', $biz->id)->first();
    expect($notification)->not->toBeNull();
    expect($notification->kind)->toBe(OwnerNotificationKind::UrgentEscalation);
    expect($notification->occasion)->toBe('msg_123');

    Http::assertNothingSent();
});

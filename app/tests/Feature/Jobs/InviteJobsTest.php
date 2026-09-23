<?php

use App\Enums\OutreachChannel;
use App\Enums\UserRole;
use App\Jobs\SendConfirmedFixInviteJob;
use App\Jobs\SendInviteReminderJob;
use App\Jobs\SendOptInConfirmationJob;
use App\Models\AutomationRun;
use App\Models\Customer;
use App\Models\FeedbackPage;
use App\Models\Location;
use App\Models\OutreachMessage;
use App\Models\Review;
use App\Models\User;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

it('tests SendInviteReminderJob', function () {
    Mail::fake();
    Notification::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    loadEveryRequiredRegister(OutreachChannel::Sms);
    app(DefaultsRegistry::class)->set('review_invite.sms_enabled', true, 'test');

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $customer = Customer::factory()->create(['business_id' => $biz->id, 'phone' => '+15551234567']);
    $review = Review::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'rating' => 5,
    ]);

    // 1. review missing
    $job = new SendInviteReminderJob((int) $biz->id, (int) $location->id, 9999);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.reminder')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'review_missing', 'reminded' => false]);

    // 2. no customer or location
    $noCustomerReview = Review::factory()->create(['business_id' => $biz->id, 'location_id' => $location->id]);
    $job = new SendInviteReminderJob((int) $biz->id, (int) $location->id, (int) $noCustomerReview->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.reminder')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'no_customer_or_location', 'reminded' => false]);

    // 3. no invite to follow
    $job = new SendInviteReminderJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.reminder')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'no_invite_to_follow', 'reminded' => false]);

    // Create an invite so it can be followed up
    OutreachMessage::factory()->create([
        'business_id' => $biz->id,
        'customer_id' => $customer->id,
        'channel' => OutreachChannel::Sms,
        'purpose' => 'review_request',
        'created_at' => now()->subDays(1),
    ]);

    // 4. no feedback page
    $job = new SendInviteReminderJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.reminder')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'no_feedback_page', 'reminded' => false]);

    // Happy path
    FeedbackPage::factory()->forLocation($location)->create();
    $job = new SendInviteReminderJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.reminder')->latest('id')->first();
    expect($run->output)->toHaveKey('reminded');
});

it('tests SendOptInConfirmationJob', function () {
    Mail::fake();
    Notification::fake();

    $ownerA = User::factory()->create(['role' => UserRole::Owner]);
    $bizA = $this->provisionTenant(['owner_user_id' => $ownerA->id]);

    $ownerB = User::factory()->create(['role' => UserRole::Owner]);
    $bizB = $this->provisionTenant(['owner_user_id' => $ownerB->id]);

    $this->actingAs($ownerB);
    Tenancy::setUser($ownerB->id);
    Tenancy::set((int) $bizB->id);
    loadEveryRequiredRegister(OutreachChannel::Sms);
    app(DefaultsRegistry::class)->set('sms.optin_confirmation_enabled', true, 'test');

    $location = Location::factory()->create(['business_id' => $bizB->id]);
    $customerB = Customer::factory()->create(['business_id' => $bizB->id, 'phone' => '+15551234568']);

    // contact missing - belongs to biz A
    Tenancy::set((int) $bizA->id);
    $customerA = Customer::factory()->create(['business_id' => $bizA->id, 'phone' => '+15551234569']);

    Tenancy::set((int) $bizB->id);
    $job = new SendOptInConfirmationJob((int) $bizB->id, (int) $location->id, (int) $customerA->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'messaging.optin_confirmation')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'contact_missing', 'confirmed' => false]);

    // Happy path
    $job = new SendOptInConfirmationJob((int) $bizB->id, (int) $location->id, (int) $customerB->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'messaging.optin_confirmation')->latest('id')->first();
    expect($run->output)->toHaveKey('confirmed');
});

it('tests SendConfirmedFixInviteJob', function () {
    Mail::fake();
    Notification::fake();

    $owner = User::factory()->create(['role' => UserRole::Owner]);
    $biz = $this->provisionTenant(['owner_user_id' => $owner->id]);
    $this->actingAs($owner);
    Tenancy::setUser($owner->id);
    Tenancy::set((int) $biz->id);
    loadEveryRequiredRegister(OutreachChannel::Sms);
    app(DefaultsRegistry::class)->set('review_invite.sms_enabled', true, 'test');

    $location = Location::factory()->create(['business_id' => $biz->id]);
    $customer = Customer::factory()->create(['business_id' => $biz->id, 'phone' => '+15551234567']);
    $review = Review::factory()->create([
        'business_id' => $biz->id,
        'location_id' => $location->id,
        'customer_id' => $customer->id,
        'rating' => 5,
    ]);

    // review missing
    $job = new SendConfirmedFixInviteJob((int) $biz->id, (int) $location->id, 9999);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.fix_then_ask')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'review_missing', 'invited' => false]);

    // no customer or location
    $noCustomerReview = Review::factory()->create(['business_id' => $biz->id, 'location_id' => $location->id]);
    $job = new SendConfirmedFixInviteJob((int) $biz->id, (int) $location->id, (int) $noCustomerReview->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.fix_then_ask')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'no_customer_or_location', 'invited' => false]);

    // no feedback page
    $job = new SendConfirmedFixInviteJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.fix_then_ask')->latest('id')->first();
    expect($run->output)->toMatchArray(['skipped' => 'no_feedback_page', 'invited' => false]);

    // Happy path
    FeedbackPage::factory()->forLocation($location)->create();
    $job = new SendConfirmedFixInviteJob((int) $biz->id, (int) $location->id, (int) $review->id);
    $job->handle();
    $run = AutomationRun::query()->where('automation_key', 'review.invite.fix_then_ask')->latest('id')->first();
    expect($run->output)->toHaveKey('invited');
});

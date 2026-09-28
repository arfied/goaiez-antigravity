<?php

declare(strict_types=1);

use App\Enums\AutopilotActionType;
use App\Enums\DunningOutcome;
use App\Enums\SubscriptionStatus;
use App\Jobs\AdvanceDunningScheduleJob;
use App\Models\ActivityFeedItem;
use App\Models\Business;
use App\Models\DunningAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Dunning;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

it('does not advance if not due', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Subscription::factory()->create([
        'business_id' => $business->id,
        'authorize_net_subscription_id' => 'sub_123',
    ]);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $dunning = app(Dunning::class);
    $dunning->open($business);

    $outcome = $dunning->advanceIfDue($business);

    expect($outcome)->toBeNull();

    $attempts = DunningAttempt::where('business_id', $business->id)->count();
    expect($attempts)->toBe(1);

    Tenancy::forget();
});

it('records Declined for attempt 2 if due and no vendor answer', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Subscription::factory()->create([
        'business_id' => $business->id,
        'authorize_net_subscription_id' => null,
    ]);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $dunning = app(Dunning::class);
    $dunning->open($business);

    $this->travel(25)->hours();

    $outcome = $dunning->advanceIfDue($business);

    expect($outcome)->toBe(DunningOutcome::Declined);

    $attempts = DunningAttempt::where('business_id', $business->id)->get();
    expect($attempts)->toHaveCount(2);

    $attempt2 = $attempts[1];
    expect(now()->diffInMinutes($attempt2->next_attempt_at))->toBeGreaterThanOrEqual(71 * 60)->toBeLessThanOrEqual(73 * 60);

    Tenancy::forget();
});

it('does not count unreachable against the budget', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Subscription::factory()->create([
        'business_id' => $business->id,
        'authorize_net_subscription_id' => 'sub_123',
    ]);

    Http::fake([
        '*request.api' => Http::response(['messages' => ['resultCode' => 'Error', 'message' => [['code' => 'E00007']]]]),
    ]);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $dunning = app(Dunning::class);
    $dunning->open($business);

    $this->travel(25)->hours();

    $outcome = $dunning->advanceIfDue($business);

    expect($outcome)->toBe(DunningOutcome::Unreachable);

    $attempts = DunningAttempt::where('business_id', $business->id)->get();
    expect($attempts)->toHaveCount(2);
    $attempt2 = $attempts[1];
    expect(now()->diffInMinutes($attempt2->next_attempt_at))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(2 * 60);

    $this->travel(2)->hours();

    // Now make it return 'unknown' by wiping the subscription id so we get a counted failure
    Subscription::where('business_id', $business->id)->update(['authorize_net_subscription_id' => null]);

    $outcome2 = $dunning->advanceIfDue($business);
    expect($outcome2)->toBe(DunningOutcome::Declined);

    $attempts = DunningAttempt::where('business_id', $business->id)->get();
    expect($attempts)->toHaveCount(3);
    $attempt3 = $attempts[2];
    expect(now()->diffInMinutes($attempt3->next_attempt_at))->toBeGreaterThanOrEqual(71 * 60)->toBeLessThanOrEqual(73 * 60);

    Tenancy::forget();
});

it('ends the plan when the ladder is exhausted', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Subscription::factory()->create([
        'business_id' => $business->id,
        'authorize_net_subscription_id' => null,
    ]);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $dunning = app(Dunning::class);
    $dunning->open($business);

    $this->travel(25)->hours();
    $dunning->advanceIfDue($business);

    $this->travel(73)->hours();
    $dunning->advanceIfDue($business);

    $this->travel(121)->hours();
    $outcome = $dunning->advanceIfDue($business);

    expect($outcome)->toBe(DunningOutcome::Exhausted);

    $sub = Subscription::where('business_id', $business->id)->first();
    expect($sub->status)->toBe(SubscriptionStatus::Canceled);
    expect($sub->ends_at)->not->toBeNull();

    $activity = ActivityFeedItem::where('business_id', $business->id)->latest('id')->first();
    expect($activity->action_type->value)->toBe(AutopilotActionType::OwnerActionNeeded->value);
    expect($activity->title)->toBe('Your plan has ended because we could not take the payment');

    Tenancy::forget();
});

it('stops the ladder if the subscription is recovered', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Subscription::factory()->create([
        'business_id' => $business->id,
        'authorize_net_subscription_id' => 'sub_123',
    ]);

    Http::fake([
        '*request.api' => function ($request) {
            $body = json_decode($request->body(), true);
            $rootKey = array_key_first($body);
            if ($rootKey === 'ARBGetSubscriptionStatusRequest') {
                return Http::response([
                    'messages' => ['resultCode' => 'Ok', 'message' => [['code' => 'I00001']]],
                    'status' => 'active',
                ]);
            }
            throw new RuntimeException('Unexpected request: '.$rootKey);
        },
    ]);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $dunning = app(Dunning::class);
    $dunning->open($business);

    $this->travel(25)->hours();

    $outcome = $dunning->advanceIfDue($business);

    expect($outcome)->toBe(DunningOutcome::ResolvedByTenant);

    $sub = Subscription::where('business_id', $business->id)->first();
    expect($sub->status)->not->toBe(SubscriptionStatus::Canceled);

    $outcome2 = $dunning->advanceIfDue($business);
    expect($outcome2)->toBeNull();

    Tenancy::forget();
});

it('honours the kill switch', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Subscription::factory()->create([
        'business_id' => $business->id,
        'authorize_net_subscription_id' => null,
    ]);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $dunning = app(Dunning::class);
    $dunning->open($business);

    $this->travel(25)->hours();

    // Clear tenancy to simulate Job startup
    Tenancy::forget();

    Config::set('autopilot.disabled_automations', ['billing.dunning_tick']);

    $job = new AdvanceDunningScheduleJob((int) $business->id);
    $job->handle($dunning);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    $attempts = DunningAttempt::where('business_id', $business->id)->count();
    expect($attempts)->toBe(1); // the open() attempt only

    Tenancy::forget();
});

<?php

use App\Enums\DunningOutcome;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\Dunning;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

it('a written "1,2" schedule ends after two failures', function () {
    $user = User::factory()->create();
    $business = Business::factory()->create(['owner_user_id' => $user->id]);
    Subscription::factory()->create([
        'business_id' => $business->id,
        'authorize_net_subscription_id' => null,
    ]);

    Tenancy::setUser($user->id);
    Tenancy::set((int) $business->id);

    PlatformSetting::write('billing.dunning.schedule_hours', '1,2', 'test', 'desc');

    $dunning = app(Dunning::class);
    $dunning->open($business); // attempt 1

    test()->travel(2)->hours();
    $outcome = $dunning->advanceIfDue($business);
    expect($outcome)->toBe(DunningOutcome::Declined); // attempt 2

    test()->travel(3)->hours();
    $outcome2 = $dunning->advanceIfDue($business);
    expect($outcome2)->toBe(DunningOutcome::Exhausted); // Exhausted

    Tenancy::forget();
});

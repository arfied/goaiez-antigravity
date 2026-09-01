<?php

declare(strict_types=1);

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Services\Billing\StripeSubscriptionState;
use App\Services\Billing\Subscriptions;
use Illuminate\Support\Carbon;

it('applies a stripe subscription', function () {
    $business = Business::factory()->create();
    $state = new StripeSubscriptionState(
        subscriptionId: 'sub_123',
        customerId: 'cus_123',
        status: SubscriptionStatus::Active,
        trialEndsAt: null,
        currentPeriodEnd: Carbon::now()->addDays(30),
        endsAt: null,
        observedAt: Carbon::now()
    );

    app(Subscriptions::class)->applyStripeSubscription($business, $state);

    $this->assertDatabaseHas('subscriptions', [
        'business_id' => $business->id,
        'gateway' => 'stripe',
        'stripe_subscription_id' => 'sub_123',
    ]);
});

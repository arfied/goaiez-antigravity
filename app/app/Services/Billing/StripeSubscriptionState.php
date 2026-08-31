<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\SubscriptionStatus;
use Illuminate\Support\Carbon;

/**
 * A Stripe subscription, read out of an event payload and nothing more.
 *
 * The boundary between "what Stripe said" and "what we store". {@see
 * StripeWebhooks} does the reading, {@see Subscriptions} does the writing, and
 * this is what crosses between them — so the shape questions live in one place
 * and the state rules live in another.
 *
 * ⚠️ **CONSTRUCTED ONLY FROM A VERIFIED EVENT.** Nothing here validates
 * anything; by the time a value reaches this object the signature check has
 * already established that Stripe sent it.
 */
final readonly class StripeSubscriptionState
{
    public function __construct(
        public string $subscriptionId,
        public string $customerId,
        public SubscriptionStatus $status,
        public ?Carbon $trialEndsAt,
        public ?Carbon $currentPeriodEnd,
        public ?Carbon $endsAt,

        /**
         * The event's own `created` time — the watermark, not a fact about the
         * subscription. It is the only orderable value Stripe offers, because
         * the object in an event is a snapshot with no version on it.
         */
        public Carbon $observedAt,
    ) {}
}

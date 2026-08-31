<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which card gateway a subscription lives on (decision 2056, T137 R2).
 *
 * ⚠️ **ADDITIVE, NOT A MIGRATION, AND THIS ENUM IS WHERE THAT IS ENFORCED.**
 * The owner's answer was explicitly *"we are adding authorize.net as our second
 * billing provider"* — Stripe stays live, both sit behind one abstraction, and
 * **webhooks stay the source of truth on both**. So a subscription names its
 * gateway rather than the application having a global one, and the tenant whose
 * subscription was created on Stripe keeps being read from Stripe forever.
 *
 * ⚠️ **A SUBSCRIPTION NEVER CHANGES GATEWAY IN PLACE.** Neither vendor can be
 * told about the other's subscription, so "moving" a tenant means cancelling one
 * and creating the other, with a gap in which nothing bills them. Nothing in
 * this application does that today and nothing should acquire the ability by
 * accident — `App\Services\Billing\Subscriptions` refuses a gateway change on a
 * row that already has a subscription id.
 *
 * ⚠️ **A STRING COLUMN CAST TO THIS ENUM, NEVER A POSTGRES ENUM TYPE**
 * (`CLAUDE.md`, decision 863). A third gateway is a plausible six months from
 * now — 2057 already asks for several *merchant accounts* per provider — and a
 * database enum's values can never be dropped or reordered once added.
 */
enum PaymentGateway: string
{
    /**
     * Stripe, through hosted Checkout. Live since row 22 slice B and not going
     * anywhere: 2056 says both stay.
     */
    case Stripe = 'stripe';

    /**
     * Authorize.Net, through Accept.js and Automated Recurring Billing.
     *
     * ⚠️ **THE PRIMARY GATEWAY BY 2056, AND THE ONE WITH NO CASHIER BEHIND IT.**
     * Decision 2108 records the honest sizing: no Cashier equivalent and **no
     * native dunning**, so the subscription lifecycle, the webhook consumer, the
     * retry schedule and suspension are all ours. None of it may be assumed to
     * exist because the Stripe half of this application has it.
     */
    case AuthorizeNet = 'authorize_net';

    /**
     * The label a person reads. Outcome language, `22`'s rule: it names the
     * company a card statement will say, never our integration.
     */
    public function label(): string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::AuthorizeNet => 'Authorize.Net',
        };
    }
}

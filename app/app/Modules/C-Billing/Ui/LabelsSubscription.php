<?php

declare(strict_types=1);

namespace App\Modules\CBilling\Ui;

trait LabelsSubscription
{
    /**
     * The words a business owner reads for a subscription's status. The enum's
     * backing strings are the schema's vocabulary, not the owner's: the state a
     * newly registered tenant sits in is written 'pending_checkout' and read by
     * every owner who has not been through Stripe Checkout yet.
     *
     * 'active' is already the owner's word and is mapped to itself so the map is
     * complete rather than partial. An unmapped case falls through to its own
     * backing value, so a status this map has never seen still renders with a
     * name rather than blank.
     *
     * @return array<string, string>
     */
    protected function subscriptionLabels(): array
    {
        return [
            'pending_checkout' => 'checkout not completed',
            'trialing' => 'on trial',
            'active' => 'active',
            'past_due' => 'payment overdue',
            'canceled' => 'ended',
            'incomplete' => 'first payment not taken',
        ];
    }
}

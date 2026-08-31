{{--
    Billing — what this application actually knows, and the way back to Checkout.

    ⚠️ **THE STATE ON THIS PAGE COMES FROM OUR OWN ROW, NEVER FROM THE URL.**
    Stripe returns a customer here with `?checkout=complete`, and that parameter
    is wrong often enough to matter: somebody who reaches it may still have a
    failed payment, and somebody who closed the tab on the way back is fully
    subscribed. Only a verified webhook moves the row (`cashier-billing`:
    "webhooks are the source of truth, never the post-Checkout redirect"). So the
    parameter selects one sentence about what just happened and decides nothing.

    That is also why "we are still confirming" is a real, expected state rather
    than an error: Stripe's event usually lands within a second or two, and the
    page is honest about the gap instead of pretending to know.

    ⛔ **"IT IS WHERE REGISTRATION LANDS" WAS TRUE UNTIL 2026-08-24 AND IS NOT
    (9201, 9230).** This paragraph read *"Uses the setup shell, because for
    almost everybody this page IS part of signup — it is where registration
    lands (decision 690)"*. `RegisterResponse` now names `setup.index`, so
    nobody arrives here from the door: this page is reached from the wizard's
    last step and from `Your plan`, and it is where both gateways return a
    customer to. **The shell stays** — this is still a buying screen rather than
    a returning-owner screen, which is the split `Livewire\Account\Plan`'s
    docblock draws — and the reason it stays is no longer the reason written
    here.
--}}

@php
    use App\Enums\SubscriptionStatus;

    $status = $subscription?->status;

    /*
     * ⚠️ BOTH GATEWAYS, AND READING ONLY THE STRIPE ID WAS A REAL DEFECT THE
     * MOMENT THE SECOND ONE EXISTED (decision 2146). This line said
     * `$subscription?->stripe_subscription_id !== null`, so every Authorize.Net
     * subscriber — including one mid-trial with a card on file — would have been
     * told "You have not added a card yet" and offered a button that creates a
     * second subscription. Decision 2056 is additive: a business is on one of two
     * vendors and the page must not assume which.
     */
    $subscribed = $subscription?->stripe_subscription_id !== null
        || $subscription?->authorize_net_subscription_id !== null;
@endphp

<x-setup.layout title="Billing">
    <h1 class="font-display text-3xl font-semibold tracking-tight text-ink">Billing</h1>

    @if (session('billing.error'))
        <div class="mt-6">
            <x-ui.attention-card heading="Payment could not be started">
                {{ session('billing.error') }}
            </x-ui.attention-card>
        </div>
    @endif

    {{--
        ⛔ **A REAL SCREEN NOBODY HAD EVER LOADED FOUND THIS ONE, WAVE 36 LANE
        A** (10306-10312). `AuthorizeNetCheckoutController::store()` has
        flashed `session('billing.notice', 'Your card is on file. Your plan
        is active.')` on a successful card-add since the day that controller
        shipped, and this page never read it — only `session('billing.error')`
        a few lines above. The round trip genuinely succeeded every time; the
        one sentence written for the moment it did was silently dropped, and
        the person landed on the ordinary trial or active sentence below with
        nothing marking that anything had just happened.
    --}}
    @if (session('billing.notice'))
        <div class="mt-6">
            <x-ui.attention-card state="ok">
                {{ session('billing.notice') }}
            </x-ui.attention-card>
        </div>
    @endif

    @if ($returned === 'complete' && ! $subscribed)
        {{--
            The honest sentence for the gap. Not an error state and not styled as
            one: nothing has gone wrong, we simply have not been told yet.
        --}}
        <p class="mt-6 text-base text-ink-2">
            Thanks — we are confirming your card with your bank. This page will show your
            plan as soon as that comes back, usually within a few seconds.
        </p>
    @elseif ($returned === 'cancelled' && ! $subscribed)
        <p class="mt-6 text-base text-ink-2">
            No card was added, so nothing has been charged and nothing has started.
        </p>
    @endif

    <div class="mt-8">
        @if ($subscribed && $status === SubscriptionStatus::Trialing)
            <p class="text-base text-ink">
                Your free trial is running.
                @if ($subscription?->trial_ends_at !== null)
                    It ends on {{ $subscription->trial_ends_at->toFormattedDayDateString() }}, and
                    your card is charged then.
                @endif
            </p>
        @elseif ($subscribed && $status === SubscriptionStatus::Active)
            <p class="text-base text-ink">
                Your plan is active.
                @if ($subscription?->current_period_end !== null)
                    The next charge is on {{ $subscription->current_period_end->toFormattedDayDateString() }}.
                @endif
            </p>
        @elseif ($subscribed && $status === SubscriptionStatus::PastDue)
            {{--
                Outcome language (`22`): what they can do, not what our state
                machine calls it. And no threat about what stops working —
                nobody has decided that (588), so saying it would invent it.
            --}}
            <x-ui.attention-card heading="Your last payment did not go through">
                Your bank declined the most recent charge. Everything keeps working while we
                retry; updating your card is the fastest way to clear it.
            </x-ui.attention-card>
        @elseif ($subscribed && $status === SubscriptionStatus::Canceled)
            {{--
                ⚠️ **THE SECOND SENTENCE ARRIVED WITH THE CHECKOUT REFUSAL IT IS
                THE ANSWER TO (9092).** Until then a returning customer was not
                refused here at all: `BillingCheckout` read only the Stripe id,
                so anybody whose ended plan had been on Authorize.Net was sent to
                Checkout and charged, and the refusal reached them as nothing at
                all — a database CHECK inside a webhook. The guard now stops that
                before the card, which lands them on this page, and "Your plan
                has ended." on its own is where they stop.

                ⚠️ **IT IS THE SENTENCE `account/plan` ALREADY USES, WORD FOR
                WORD, AND DELIBERATELY NOT A BUTTON.** Nothing in this
                application recreates a subscription (8975, raised at 6508), so a
                control here would be a promise no code keeps. `1220`'s rule and
                `AutopilotActionType::OwnerActionNeeded`'s house position: name
                the person who can, rather than an action the reader cannot take.
            --}}
            <p class="text-base text-ink">
                Your plan has ended. If you want it back, ask us and we will set it up again for you.
            </p>
        @else
            {{--
                ⛔ **"WE ASK FOR ONE UP FRONT" STOPPED BEING TRUE THE DAY
                REGISTRATION STOPPED ASKING (9201, 9230).** Decision 98's card
                requirement was dropped by 2065 on 2026-08-11 and the redirect
                moved on 2026-08-24; a sentence describing our own funnel is the
                cheapest kind of stale claim to leave behind, and this one was
                being shown to the person the funnel had just changed for.

                ⚠️ **AND THE TRIAL CLOCK STARTS HERE RATHER THAN AT
                REGISTRATION.** `Subscriptions::openPendingSignup()` writes no
                trial date — *"the trial starts when the card is captured …
                a person who registers and never checks out has no trial end
                recorded, because they have no trial"* (685) — so *"nothing
                stops when the free trial ends"* was addressed to somebody whose
                trial had not begun.
            --}}
            <p class="text-base text-ink-2">
                You have not added a card yet. Nothing is being charged and nothing has
                stopped. Adding a card starts your free trial, and you are not charged
                until the trial ends.
            </p>

            @if ($cardMayBeAdded)
                {{--
                    ⚠️ AUTHORIZE.NET IS THE PRIMARY GATEWAY (2056, T137 R2) AND
                    STRIPE IS NOT REMOVED. There is deliberately no picker: a person
                    adding a card does not care which processor takes it, and
                    `CLAUDE.md`'s "never add a tenant-facing toggle" applies to a
                    choice with no outcome attached. The Stripe route stays live and
                    reachable for support and for anyone already on it.
                --}}
                <x-ui.button :href="route('billing.card')" class="mt-6">
                    Add a card
                </x-ui.button>
            @else
                {{--
                    ⛔ **A CONTROL THAT CANNOT SUCCEED AND IS NEVER WITHDRAWN**
                    (9296). `Add a card` is the only affordance this page has,
                    and with no gateway credential set every press bounced back
                    here and redrew the same button beside the same failure:
                    press, bounce, press, bounce, indefinitely. **Every
                    deployment of this application has been in that state**, and
                    the sentence above still said *"Adding a card starts your
                    free trial"* while nothing could add one.

                    ⚠️ **ABSENT RATHER THAN DISABLED** (1220), and it names the
                    person who can act rather than an action this reader cannot
                    take — `AutopilotActionType::OwnerActionNeeded`'s house
                    position, and the same choice the ended-plan branch above
                    makes.

                    ⛔ **THIS MARKUP IS NOT THE GATE** (398).
                    `AuthorizeNetCheckoutController::create()` refuses
                    `/billing/card` on its own, and that refusal is what the
                    test drives.
                --}}
                <x-ui.attention-card class="mt-6" heading="We cannot take a card just now">
                    Nothing is being charged and nothing has stopped. Reply to any email
                    from us and we will sort it out with you.
                </x-ui.attention-card>
            @endif
        @endif
    </div>
</x-setup.layout>

{{--
    Your plan — and the cancellation this application promised and did not have
    (2980–2999).

    ⚠️ THE OUTCOME IS NAMED BEFORE THE BOX, WHICH IS WHAT MAKES THE BOX A
    CONFIRMATION. A tick under the word "cancel?" confirms nothing; the sentences
    below say what this particular subscription does when the button is pressed —
    when access ends, whether a paid year stands, what is charged next — and the
    box sits under that. `CancelSubscriptionRequest`'s docblock carries the same
    reasoning from the other side.

    ⚠️ EVERY SENTENCE COMES FROM `CancellationOutcome`, NOT FROM `status`. The two
    gateways end a subscription in materially different ways — Stripe can stop at
    the end of a period it has already charged for and Authorize.Net cannot
    express that at all — so a single "your plan will be cancelled" would be a
    false statement to one of the two populations.

    OUTCOME LANGUAGE (`22`, `29` §2 rule 47). The words "subscription status",
    "gateway" and "webhook" appear nowhere on the page. Colour is never the sole
    signal: the pending-request notice is a bordered panel with a sentence in it,
    readable in monochrome and to a screen reader.

    WORKS AT 320px, and nothing here is below 16px.
--}}

@php
    use App\Enums\CancellationOutcome;
    use App\Enums\CardReplacementOffer;
@endphp

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your plan</h1>
        <p class="mt-1 text-base text-ink-2">
            What you are paying for, and how to stop it.
        </p>
    </div>

    {{--
        ⛔ THE PANEL THIS PAGE SPENT ITS WHOLE LIFE WITHOUT (3536, 4665, 4840).
        The comment further down says in as many words that "What am I paying?" is
        this page, and until now the page carried no figure of any kind. Every
        amount here is read from the subscription row through
        `PlanCharges::agreedPriceFor()` and its two siblings, never from the
        registry: 3443 is the owner's ruling that an existing customer keeps the
        price they signed up at, and the day a price moves the registry figure is
        the one number this screen must not show.

        ⚠️ THE PAYMENT COUNT IS NEVER A WORD HERE. The retail annual is three
        payments and the founder annual is two (2092, 2754); 4643's defect was a
        card page that said "three" unconditionally. `count($payments)` and a loop,
        with no branch on a number anywhere.

        COLOUR IS NOT THE SIGNAL: the amount is set apart by size and weight, and
        the panel reads identically in monochrome and to a screen reader. Works at
        320px — nothing here is a table — and nothing is below 16px.
    --}}
    @if ($agreedPrice !== null)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">What you pay</h2>

            <p class="mt-2 text-ink">
                <span class="font-display text-2xl font-semibold">{{ $agreedPrice }}</span>@if ($termCadence !== null)<span class="text-base text-ink-2"> {{ $termCadence }}</span>@endif
            </p>

            @if ($priceIsAgreed)
                {{--
                    ⚠️ SHOWN ONLY WHERE THE ROW ITSELF CARRIES THE PRICE. On a
                    subscription written before the agreed-price columns existed
                    the amount above comes from today's registry, and promising it
                    was "the price you signed up at" would be 3444's false
                    statement made directly to the customer. See the component.
                --}}
                <p class="mt-2 text-base text-ink-2">
                    This is the price you agreed to when you signed up. It stays yours for as
                    long as you keep this plan, even if our prices change.
                </p>
            @endif

            @if ($extraLocations > 0)
                {{--
                    The total above already has these in it. A total that quietly
                    includes something is the plausible-number failure this project
                    keeps meeting, so it is named.
                --}}
                <p class="mt-2 text-base text-ink-2">That includes {{ $extraLocations }} extra {{ Str::plural('location', $extraLocations) }} at {{ $extraLocationPrice }} each.</p>
            @endif

            @if ($payments !== [])
                <h3 class="mt-5 text-base font-semibold text-ink">How it is collected</h3>

                <p class="mt-2 text-base text-ink-2">
                    You pay this in {{ count($payments) }} payments, one each billing period:
                </p>

                <ol class="mt-2 space-y-1">
                    {{--
                        empty-state: absent because an empty list here does not mean
                        "we found nothing" — it means this plan is taken in ONE
                        payment, and `Account\Plan::instalments()` returns `[]` for
                        exactly that case rather than a list of one (754: a single
                        figure printed twice is a figure that can disagree with
                        itself). The amount is already named above this section in
                        its own right, so an invitation would have to say "you have
                        no payments" to somebody who has one — a report on a query,
                        and a false one, which is the opposite of what `29` §9.1
                        asks an empty state to be. The `@if` above is the honest
                        branch and its sibling is the panel itself.
                    --}}
                    @foreach ($payments as $index => $amount)
                        <li class="text-base text-ink">Payment {{ $index + 1 }} — {{ $amount }}</li>
                    @endforeach
                </ol>

                {{--
                    ⚠️ THE LAST PAYMENT CAN BE A PENNY LARGER AND SAYING SO IS THE
                    DISCLOSURE 2055 ASKED FOR, NOT A ROUNDING BUG. The remainder
                    rides on the final payment so that the payments sum exactly to
                    the price this page quotes; spreading it would make the plan
                    cost a cent less than every page that names it.
                --}}
                <p class="mt-2 text-base text-ink-2">
                    The last payment can be a penny more than the others, so the payments add
                    up to exactly the price above.
                </p>
            @endif
        </div>
    @endif

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Where things stand</h2>

        {{--
            ⚠️ READ FROM OUR OWN ROW, AND OUR OWN ROW ONLY MOVES ON A VERIFIED
            NOTIFICATION FROM THE PAYMENT PROVIDER (2056). Nothing on this page
            is inferred from a redirect, a query string or the fact that somebody
            pressed a button a moment ago.
        --}}
        <p class="mt-2 text-base text-ink-2">
            @if ($outcome === CancellationOutcome::NothingToCancel)
                You do not have a paid plan on this account, so nothing is being charged
                and there is nothing to cancel.
            @elseif ($outcome === CancellationOutcome::AlreadyEnded)
                {{--
                    ⛔ THIS SAID ONLY "Your plan has ended. Nothing more will be
                    charged." UNTIL 6500, AND THE MISSING HALF IS THE ONLY HALF
                    SOMEBODY IN THIS STATE WANTS. 6404 names it: a tenant whose
                    plan ended because three payments failed reads a full stop
                    where they were looking for a way back. ⚠️ A NEW CARD IS NOT
                    THAT WAY: on this payment provider a terminated subscription
                    "can no longer be reactivated and must be recreated", and
                    nothing in this application recreates one — so the honest
                    sentence names the person who can, which is 1220's rule and
                    `AutopilotActionType::OwnerActionNeeded`'s house position
                    ("naming an action the owner cannot take is worse jargon than
                    naming a mechanism"). Restarting it is raised at 6508.
                --}}
                Your plan has ended, and nothing more will be charged. If you want it back, ask us and we will set it up again for you.
            @elseif ($outcome === CancellationOutcome::NoFurtherCharge)
                Every payment for your plan has been taken. Nothing more is due and
                nothing renews by itself.
            @elseif ($subscription?->trial_ends_at !== null)
                Your plan is running. Your free trial ends on
                {{ $subscription->trial_ends_at->toFormattedDayDateString() }}.
            @else
                Your plan is running.
            @endif
        </p>

        {{--
            ⛔ THE NO-CARD FREE TRIAL, WHICH THIS PAGE COULD NOT SAY ANYTHING
            ABOUT AT ALL UNTIL 9400. Every other date on this screen is
            `ends_at`, `current_period_end` or `trial_ends_at`, and a
            `pending_checkout` row carries none of them — so the paragraph above
            answered the one population whose product is about to stop with "you
            do not have a paid plan on this account", which is true and is not
            the thing they came here to find out.

            ⚠️ THE SAME SENTENCE AS `/account/credit`, DELIBERATELY WORD FOR
            WORD, AND THE SAME DATE FORMAT. Two screens describing one clock in
            two ways is how a person comes to believe there are two clocks; the
            date is `translatedFormat('j F Y')` on both and in the email, rather
            than this file's `toFormattedDayDateString()`, because agreeing with
            the other two renderings of this clock is worth more than agreeing
            with the paid-plan dates above.

            ⚠️ IT STATES A DATE AND OFFERS THE CONTROL; IT DOES NOT PRESS. The
            trial is no-card by ruling (2065) and adding one is optional, so the
            second sentence is what happens if the person does nothing — which,
            on this product, is what the person is expected to do.
        --}}
        @if ($trialEndsOn !== null)
            <p class="mt-2 text-base text-ink-2">
                @if ($trialHasEnded)
                    Your free trial ran until {{ $trialEndsOn }}, and sending and publishing are
                    now paused. Nothing has been deleted and nothing has been charged — your
                    account stays exactly as it is, and adding a card starts everything again.
                @else
                    Your free trial runs until {{ $trialEndsOn }}. Everything is on and nothing is
                    being charged; if you do nothing, sending and publishing pause on that date and
                    your account stays exactly as it is.
                @endif
            </p>
        @endif

        @if ($outcome->endedSomething() && $subscription?->current_period_end !== null)
            <p class="mt-2 text-base text-ink-2">
                The next payment is due on
                {{ $subscription->current_period_end->toFormattedDayDateString() }}.
            </p>
        @endif

        {{--
            ⛔ THE DATE THE PLAN ITSELF ENDS, WHICH THIS PAGE COULD NOT SAY UNTIL
            2026-08-23 (8964). `Subscriptions::applyAuthorizeNetSubscription()`
            writes `ends_at` with the comment "so the billing page can say when
            access ends" — and no template in this application read the column.
            The tenant closest to needing it is the one who has just cancelled an
            annual term: they keep the year, and nothing told them which day it
            stops.

            ⚠️ IT IS PREFERRED OVER `annual_term_ends_on` RATHER THAN SHOWN BESIDE
            IT. On a cancelled annual row the two hold the same day, so printing
            both says one thing twice; `ends_at` is the recorded end of *service*
            on either gateway, and the term is only what a year was sold as.

            ⚠️ AND THE TENSE MOVES WITH THE DATE. Both sentences here were
            present-tense unconditionally, so a tenant whose year finished last
            spring read "the year you have paid for runs to" a date in the past —
            on the page they opened to find out why the product had stopped.
        --}}
        @if ($subscription?->ends_at !== null)
            <p class="mt-2 text-base text-ink-2">
                {{-- ⚠️ NOT "You keep everything until", WHICH THE CANCEL PANEL BELOW
                     ALREADY SAYS ON EVERY STRIPE ROW. Two sentences a page apart
                     opening with the same six words is a test that passes against
                     the wrong panel and a reader who thinks they have read it. --}}
                @if ($subscription->accessHasEnded())
                    Your plan ran to {{ $subscription->ends_at->toFormattedDayDateString() }} and has now ended.
                    Nothing more is charged. If you want it back, ask us and we will set
                    it up again for you.
                @else
                    Your plan runs to {{ $subscription->ends_at->toFormattedDayDateString() }} and you are not charged again.
                @endif
            </p>
        @elseif ($subscription?->annual_term_ends_on !== null)
            <p class="mt-2 text-base text-ink-2">
                @if ($subscription->annual_term_ends_on->isFuture())
                    The year you have paid for runs to
                    {{ $subscription->annual_term_ends_on->toFormattedDayDateString() }}.
                @else
                    The year you paid for ran to
                    {{ $subscription->annual_term_ends_on->toFormattedDayDateString() }}.
                @endif
            </p>
        @endif

        {{--
            T176 P17's "links from Plan" — as SIGNPOSTS AND NOT AS LINKS, and
            that is a refusal rather than a shortcut (4301).

            ⚠️ THE TWO SCREENS ANSWER TWO DIFFERENT QUESTIONS AND A PERSON
            LOOKING FOR ONE ARRIVES AT THE OTHER. "What am I paying?" is this
            page; "what have I got left?" is the credit page — and the plan
            price does not move when the credit does, so somebody who came here
            to find out why a campaign stopped reads a page that is entirely
            correct and answers nothing they asked. Naming the other two screens
            is what fixes that, and it is the whole of what P17 asked for.

            ⛔ THE `route()` CALLS THAT WERE HERE ARE GONE, BECAUSE MOVEMENT
            BETWEEN OWNER SCREENS COMES FROM `OwnerNav` AND FROM NOWHERE ELSE.
            `Architecture/OwnerNavTest`'s "an owner screen does not hand-write
            its own link to another owner screen" fails the build on them, and
            its allowlist entry for this very file says in as many words that a
            `GET` route name appearing there "would be the cross-link this lint
            exists to refuse". Widening that entry would have been overturning a
            written argument to keep two sentences; both destinations are
            already nav items under More, so what was actually lost is a hint
            and never a route.
        --}}
        <p class="mt-4 text-base text-ink-2">
            Looking for what you have left to spend? Your credit — under More —
            has your text, email and writing balances.
        </p>

        {{--
            And the locations the plan covers, for the same reason: "how many
            places does this cover" is a question about the plan and the answer
            lives on its own screen (T176 P25).
        --}}
        <p class="mt-2 text-base text-ink-2">
            Your locations — also under More — shows how many places your plan
            covers.
        </p>
    </div>

    {{--
        ⛔ THE CARD, AND THE HOLE 6404 FOUND (6500).

        `AuthorizeNetGateway::replacePaymentMethod()` calls itself "the dunning
        remedy, and the only recovery that actually works on this gateway" and had
        NO CALLER ANYWHERE IN `app/` — while the dunning notices went out telling
        tenants their plan was about to end. And the obvious button was worse than
        none: `billing.card` is the SIGNUP screen and `subscribe()` throws for any
        business already holding a subscription id, which is every business those
        notices reach. This panel is the door.

        ⛔ NO INPUT BELOW CARRIES A `name`, AND THAT IS THE WHOLE OF SAQ-A ON
        THIS PAGE. A field with no `name` is not serialised by the browser and
        this form is never submitted anyway — Alpine intercepts, Accept.js reads
        the card fields from the DOM, posts them straight to the payment
        provider, and hands back an opaque nonce. ADDING A `name` TO ANY OF THEM
        PUTS A CARD NUMBER IN OUR REQUEST LOGS, and nothing in the PHP would look
        different. `Account\Plan::replaceCard()` has no parameter anywhere on
        this path for a card number.

        ⛔ THE NAME ON THE CARD IS COLLECTED HERE SINCE 2026-08-29, AND IT IS THE
        FIRST VALUE THIS PANEL SENDS US THAT A PERSON TYPED. Authorize.Net will
        not create a payment profile this application can bill without a
        `billTo`, and a subscription pointed at one answers `E00014` —
        *"Bill-To First Name is required."* The two fields are read by Alpine and
        handed to `replaceCard()` as arguments, exactly like the nonce: they
        never become component state, so they never round-trip back into the page
        on a later render.

        ⚠️ THEY CARRY `cc-given-name` AND `cc-family-name`, WHICH IS THE SAME
        BROWSER AUTOFILL GROUP AS `cc-number`. That is the point — a person
        filling a card form should not retype the name — and it is also the
        hazard, so `replaceCard()` runs the same Luhn tripwire the signup form's
        request runs, and `CardholderName` refuses a card-shaped name again at
        the boundary. ⚠️ THIS SENTENCE READ *"EVERY CARD INPUT BELOW HAS `x-ref`
        AND NO `name`"*, WHICH WAS TRUE AND IS NO LONGER THE PROPERTY THAT
        MATTERS: what must never be serialised is a card **credential**, and
        nothing on this panel is serialised at all.

        ⚠️ THE PRESS IS THE CONFIRMATION, SO THE PAGE NAMES THE AMOUNT FIRST.
        `CLAUDE.md` reserves CONFIRM for anything that spends money, and on this
        vendor updating the payment information is what causes the outstanding
        payment to be taken. ⚠️ THE FIGURE IS `agreedPriceFor()`'s AND THE
        SENTENCE IS WITHHELD ENTIRELY ON THE PRE-COLUMNS FALLBACK ARM — 6406's
        rule, because this is a claim about a charge and on that arm the number is
        today's registry rather than what the card is charged. Two shapes, never
        one with a hole in it.

        OUTCOME LANGUAGE (`22`). Colour is never the sole signal: every state here
        is a bordered panel with a sentence in it, readable in monochrome and to a
        screen reader. Works at 320px — the month/year/code row is a three-column
        grid that stays legible — and nothing is below 16px.
    --}}
    @if ($cardMessage !== null)
        {{--
            ⚠️ A PANEL AND NOT A TOAST, BECAUSE THIS IS AN INSTRUCTION SOMEBODY
            HAS TO ACT ON WITH THE FORM STILL IN FRONT OF THEM. The success case
            is the toast; every refusal is here, where it stays put.
        --}}
        <div class="rounded-[--radius-panel] border border-rule-strong bg-card p-5" role="alert" aria-live="polite">
            <p class="text-base text-ink">{{ $cardMessage }}</p>
        </div>
    @endif

    @if ($cardFormReady)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Your card</h2>

            @if ($paymentIsOutstanding)
                <p class="mt-2 text-base text-ink-2">A payment for your plan did not go through. Putting a working card in here is what fixes it.</p>

                @if ($priceIsAgreed && $agreedPrice !== null)
                    <p class="mt-2 text-base text-ink-2">The payment we could not take is {{ $agreedPrice }}. Your payment provider will take it from the new card.</p>
                @else
                    <p class="mt-2 text-base text-ink-2">Your payment provider will take the payment we could not take from the new card.</p>
                @endif
            @else
                <p class="mt-2 text-base text-ink-2">Change the card your plan is paid from. Your next payment, and every one after it, comes off the new card.</p>

                @if ($priceIsAgreed && $agreedPrice !== null)
                    <p class="mt-2 text-base text-ink-2">
                        That is {{ $agreedPrice }}@if ($termCadence !== null) {{ $termCadence }}@endif.
                    </p>
                @endif
            @endif

            <p class="mt-2 text-base text-ink-2">
                We never see your card number. It goes straight from this page to our
                payment provider, and they hand us back a token that cannot be used to
                take a payment anywhere else.
            </p>

            {{--
                The accepted methods, per T137 R2. ⚠️ TEXT, NOT IMAGES, AND NOT
                COLOUR ALONE — `22`'s rule, and four brand marks with no text also
                fail at 320px and in a screen reader.
            --}}
            <p class="mt-2 text-base text-ink-2">
                Credit and debit cards accepted: Visa, Mastercard, American Express, Discover.
            </p>

            {{--
                ⚠️ ALPINE RATHER THAN `wire:submit`, AND IT IS THE VENDOR THAT
                DECIDES THAT. `wire:submit` would call the server the instant the
                button is pressed — before Accept.js has been anywhere near the
                card — so there would be no nonce to send. The card has to reach
                the payment provider first and the server second, which is one
                interception and cannot be two: a `wire:submit` beside an
                Alpine `submit` handler is two things racing for one press.

                ⛔ THE SUBMIT IS ALWAYS PREVENTED. If Accept.js failed to load —
                a blocked script, an ad blocker, an outage — this stops the form
                submitting as an ordinary POST, which is the one failure that
                would put a card number in our logs and the failure most likely
                to happen. The fields have no `name` and would carry nothing
                anyway; this is the belt to that braces.

                ⛔ THE ROUND TRIP HAD A `finally` AND NO `catch`, AND THE COST
                WAS NOT A MISSING MESSAGE — decision 10100. A `$wire` call
                rejects on a dropped connection, a 419 and a 500 alike
                (`livewire.js`: `sendRequest()` hands a fetch throw to
                `invokeOnFailure`, which calls `rejectActionPromises`). So
                `finally` cleared `busy`, `problem` stayed at '' — and the card
                fields had ALREADY been wiped two lines above the call. The form
                reset itself, said nothing, and no card had been replaced, on the
                one screen a customer in dunning is sent to.

                ⚠️ THE SENTENCE DOES NOT SAY THE CARD WAS NOT SAVED, BECAUSE WE
                DO NOT KNOW. A rejected fetch covers both `the request never
                left` and `the request was handled and the answer was lost`, and
                `PaymentMethodReplacement::replace()` makes three vendor calls —
                so a flat `your card has not been changed` would be a claim about
                somebody else's money that this handler cannot make. It names the
                one thing they control instead, in the voice this screen already
                uses for the shape it cannot classify: reply to any email from
                us.

                ⛔ AND `resultCode: Ok` WITH NO `opaqueData` COST THE BUTTON
                ITSELF (10101). `response.opaqueData.dataValue` raised a
                `TypeError` inside the vendor's own callback, so `busy` was never
                cleared: the person was left looking at the Saving… label for
                ever, fields wiped, nothing to press. ⚠️ It is asked as
                `response.opaqueData ? … : ''` rather than with `?.` because a
                missing `dataValue` on a present `opaqueData` has to reach the
                same arm, and an empty string is what both spellings of the
                vendor being unhelpful come out as.

                ⚠️ NONE OF THE THREE IS REACHABLE FROM PHP. Every `replaceCard()`
                test calls the method directly through `Livewire::test()`, which
                runs no line of this block, so a Feature suite has nothing to say
                about any of it. `tests/Browser/AccountScreenTest.php` is where
                they live. ⛔ THE COUNT IS DELETED RATHER THAN CORRECTED: this
                read "six tests" over five in `PlanScreenCardTest`, and
                `CardholderNameTest` has since added more — a hand-kept tally of
                a list that counts itself goes stale the first time either file
                grows, and the property above is what the sentence was for.
            --}}
            <form
                x-data="{
                    busy: false,
                    problem: '',
                    submit() {
                        this.problem = '';

                        if (typeof Accept === 'undefined') {
                            this.problem = 'We could not load the secure card form. Please refresh the page and try again.';

                            return;
                        }

                        this.busy = true;

                        Accept.dispatchData({
                            authData: {
                                clientKey: @js($acceptClientKey),
                                apiLoginID: @js($acceptApiLoginId),
                            },
                            cardData: {
                                cardNumber: this.$refs.number.value.replace(/\s/g, ''),
                                month: this.$refs.month.value,
                                year: this.$refs.year.value,
                                cardCode: this.$refs.code.value,
                            },
                        }, (response) => {
                            if (response.messages.resultCode !== 'Ok') {
                                this.busy = false;
                                this.problem = 'Please check the card details and try again.';

                                return;
                            }

                            const nonce = response.opaqueData ? response.opaqueData.dataValue : '';

                            if (! nonce) {
                                this.busy = false;
                                this.problem = 'We could not take that card just now. Please try again in a few minutes.';

                                return;
                            }

                            // ⛔ READ BEFORE THE FIELDS ARE WIPED. The four card
                            // fields are cleared two lines below and the two
                            // name fields are not — they are what the server
                            // needs — but reading them here keeps the order
                            // obvious to whoever next edits the wipe list.
                            const firstName = this.$refs.firstName.value;
                            const lastName = this.$refs.lastName.value;

                            ['number', 'month', 'year', 'code'].forEach((field) => {
                                this.$refs[field].value = '';
                            });

                            this.$wire.replaceCard(nonce, firstName, lastName)
                                .catch(() => {
                                    this.problem = 'We could not finish saving that card, and we cannot tell you whether it went through. Reply to any email from us and we will check it with you.';
                                })
                                .finally(() => {
                                    this.busy = false;
                                });
                        });
                    },
                }"
                x-on:submit.prevent="submit()"
                class="mt-5 max-w-md"
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="new-card-first-name" class="block text-base font-medium text-ink">First name on the card</label>
                        {{-- ⛔ NO `name` ATTRIBUTE — this value reaches us as an argument to a Livewire action, never as a form field. --}}
                        <input
                            type="text"
                            id="new-card-first-name"
                            x-ref="firstName"
                            autocomplete="cc-given-name"
                            maxlength="{{ \App\Support\CardholderName::MAX_LENGTH }}"
                            required
                            class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink"
                        >
                    </div>

                    <div>
                        <label for="new-card-last-name" class="block text-base font-medium text-ink">Last name on the card</label>
                        <input
                            type="text"
                            id="new-card-last-name"
                            x-ref="lastName"
                            autocomplete="cc-family-name"
                            maxlength="{{ \App\Support\CardholderName::MAX_LENGTH }}"
                            required
                            class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink"
                        >
                    </div>
                </div>

                <p class="mt-2 text-base text-ink-2">
                    Enter them exactly as they appear on the card. It does not have to be your own card.
                </p>

                <div class="mt-4">
                    <label for="new-card-number" class="block text-base font-medium text-ink">Card number</label>
                    {{-- ⛔ NO `name` ATTRIBUTE. See the comment at the top of this panel. --}}
                    <input
                        type="text"
                        id="new-card-number"
                        x-ref="number"
                        inputmode="numeric"
                        autocomplete="cc-number"
                        required
                        class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink"
                    >
                </div>

                <div class="mt-4 grid grid-cols-3 gap-3">
                    <div>
                        <label for="new-card-month" class="block text-base font-medium text-ink">Month</label>
                        <input type="text" id="new-card-month" x-ref="month" inputmode="numeric" autocomplete="cc-exp-month" required
                               class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink">
                    </div>
                    <div>
                        <label for="new-card-year" class="block text-base font-medium text-ink">Year</label>
                        <input type="text" id="new-card-year" x-ref="year" inputmode="numeric" autocomplete="cc-exp-year" required
                               class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink">
                    </div>
                    <div>
                        <label for="new-card-code" class="block text-base font-medium text-ink">Security code</label>
                        <input type="text" id="new-card-code" x-ref="code" inputmode="numeric" autocomplete="cc-csc" required
                               class="mt-2 block w-full rounded-lg border border-rule-strong px-3 py-2 text-base text-ink">
                    </div>
                </div>

                <p class="mt-4 text-base text-alert" role="alert" aria-live="polite" x-show="problem !== ''" x-text="problem" style="display: none"></p>

                {{--
                    The loading state, aimed at this handler and nothing else. A
                    person waits on two round trips here — the payment provider's
                    and ours — and a second press during either is the one that
                    creates a second payment profile.
                --}}
                <x-ui.button type="submit" class="mt-6" x-bind:disabled="busy">
                    <span x-show="! busy">Save this card</span>
                    <span x-show="busy" style="display: none">Saving…</span>
                </x-ui.button>
            </form>
        </div>

        {{--
            ⚠️ THE SCRIPT HOST DIFFERS BETWEEN SANDBOX AND PRODUCTION ON THIS
            VENDOR — `jstest.authorize.net` against `js.authorize.net` — where
            Stripe distinguishes modes by the key alone. The component picks it;
            loading the wrong one fails in the browser with a message about
            authentication, which reads as a bad key.

            ⚠️ THE API LOGIN ID AND THE PUBLIC CLIENT KEY ARE IN THIS PAGE ON
            PURPOSE. The vendor's own documentation: "you cannot use the Public
            Client Key to initiate a transaction, you may safely store the Public
            Client Key in a website". ⛔ The TRANSACTION key is a different value,
            never appears here, and there is no code path that could put it here —
            the component names the two keys it wants rather than passing a
            credential bag.
        --}}
        <script src="{{ $acceptJsUrl }}" charset="utf-8"></script>
    @elseif ($cardOffer === CardReplacementOffer::AccountOnHold)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Your card</h2>

            <p class="mt-2 text-base text-ink-2">Your account is on hold, so we cannot take a new card here. Reply to any email from us and we will sort it out with you.</p>
        </div>
    @elseif ($cardOffer === CardReplacementOffer::HandledByStripe)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Your card</h2>

            <p class="mt-2 text-base text-ink-2">The card for this plan is held by a different payment provider. Ask us and we will change it with you.</p>
        </div>
    @elseif ($cardOffer === CardReplacementOffer::Available)
        {{--
            The offer stands and the form cannot be drawn: a platform credential
            is missing. ⚠️ THIS IS AN OPERATOR PROBLEM AND THE SENTENCE SAYS SO
            WITHOUT NAMING A MECHANISM (`22`). `PlatformCredentials::get()` throws
            on an unset key, so the component asks `has()` first — a missing key
            must degrade to a person, never to a stack trace on the page somebody
            opened to fix their bill.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Your card</h2>

            <p class="mt-2 text-base text-ink-2">We cannot take a new card here just now. Reply to any email from us and we will sort it out with you.</p>
        </div>
    @elseif ($cardOffer->invitesAFirstPlan())
        {{--
            ⛔ THE ARM THAT WAS NOT HERE, FOR THE POPULATION 9201 CREATES (9229).
            The chain above ran `@if ($cardFormReady)` → `AccountOnHold` →
            `HandledByStripe` → `Available` → `@endif` with no `@else`, so an
            owner who has never bought reached this page and found a heading, the
            sentence "you do not have a paid plan on this account", and **no
            control of any kind** — `CLAUDE.md`'s *a rendered screen with no
            control on it*, one panel down. It mattered less while registration
            opened on a checkout; 9201 moved that to `/setup`, so this is now the
            state a new owner is in.

            ⚠️ `invitesAFirstPlan()` RATHER THAN `=== NoPlanToPayFor`, WHICH IS
            THE SAME QUESTION ASKED IN THE ONE PLACE. The wizard's last step asks
            it too (9229), and two screens comparing against a case by hand is
            how they come to disagree the day a seventh case exists — the enum's
            own stated reason for having methods at all. It is an exhaustive
            `match`, so that seventh case reddens both screens instead of
            silently choosing "no".

            ⚠️ A CONTROL RATHER THAN A SENTENCE, BECAUSE THE SENTENCE IS ALREADY
            ON THIS PAGE. "Where things stand" above says it, from
            `CancellationOutcome::NothingToCancel`, and repeating it here would
            be one fact printed twice — 754's trap, where whichever copy is wrong
            is invisible. What was missing was the way to act on it.

            ⚠️ THE TWO SIBLING CASES ARE DELIBERATELY STILL ABSENT FROM THIS
            CHAIN. `NothingIsDue` is unreachable as `$cardOffer` at all —
            `PaymentMethodReplacement::offer()` never returns it, because only
            the vendor knows a schedule has finished, so it arrives through
            `$cardMessage` after a press. And `PlanHasEnded`'s sentence is the
            `AlreadyEnded` arm of "Where things stand", which also carries the
            reason there is no button: on this provider a terminated
            subscription cannot be reactivated and nothing here recreates one
            (8975, raised at 6508).

            ⚠️ IT POINTS AT `billing.index`, NOT AT `billing.card`. The comment
            at the head of this panel is about an EXISTING subscriber — that is
            the population `subscribe()` throws for — and it says the obvious
            button was worse than none for them. This arm is the opposite
            population, and the destination is the screen that reads the row and
            decides, so a page drawn before somebody bought in another tab lands
            them on the truth rather than on a card field.

            OUTCOME LANGUAGE (`22`). Colour is not the signal: a bordered panel
            with a sentence in it, readable in monochrome and to a screen reader.
            Works at 320px; nothing below 16px.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Your card</h2>

            <p class="mt-2 text-base text-ink-2">
                There is no card on this account and nothing is being charged. Adding one
                starts your free trial, and you are not charged until the trial ends.
            </p>

            <x-ui.button :href="route('billing.index')" size="default" class="mt-4">
                Add a card
            </x-ui.button>
        </div>
    @endif

    {{--
        ⚠️ "WE HAVE ASKED", NOT "IT IS CANCELLED", AND THE DIFFERENCE IS THE WHOLE
        DESIGN (2056). `cancellation_requested_at` records our own act — this
        person pressed this button at this moment. Whether the subscription has
        actually ended is `status`, and that moves only when the payment provider
        tells us so. A screen that read the request as the state would show a
        tenant as cancelled while the provider had never received the call.
    --}}
    @if ($requestedAt !== null)
        <div class="rounded-[--radius-panel] border border-rule-strong bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">You asked us to cancel</h2>

            <p class="mt-2 text-base text-ink-2">
                We passed that on to your payment provider on
                {{ $requestedAt->toFormattedDayDateString() }}. Nothing further is
                needed from you. If anything still shows as due after a day or two, ask
                us and we will look.
            </p>
        </div>
    @endif

    @if ($outcome->endedSomething())
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Cancel your plan</h2>

            {{--
                ⚠️ THE ARMS ARE THE HONEST ANSWERS AND NOT THE CODE PATHS. See
                `App\Enums\CancellationOutcome` — collapsing them would leave one
                population reading something untrue about their own money.
            --}}
            @if ($outcome === CancellationOutcome::StopsAtPeriodEnd)
                <p class="mt-2 text-base text-ink-2">
                    You keep everything until the end of the period you have already paid
                    for, and you are not charged again. Nothing stops today.
                </p>
            @elseif ($outcome === CancellationOutcome::KeepsPaidTerm)
                <p class="mt-2 text-base text-ink-2">
                    You have already paid for the year, so you keep the product until it
                    runs out. Any payment that has not been taken yet is stopped, and
                    nothing renews afterwards.
                </p>
            @else
                <p class="mt-2 text-base text-ink-2">
                    Your plan stops when you cancel. Your payment provider cannot hold a
                    cancellation until the end of the month, so the rest of this month is
                    not carried over — cancel on the day that suits you.
                </p>
            @endif

            <p class="mt-2 text-base text-ink-2">
                There is no cancellation fee and no notice period. Nothing you have
                collected is deleted — your reviews, your customers and your messages
                stay where they are.
            </p>

            @if ($mayCancel)
                {{--
                    ⚠️ A PLAIN FORM POST RATHER THAN A LIVEWIRE ACTION (1900, 1997).
                    `SuspendedTenantStatus` exempts by route name and a Livewire
                    button posts to `default-livewire.update`, so only a named route
                    can be exempted at the width of this promise. See the component.
                --}}
                <form x-data="{ confirmed: false }" method="POST" action="{{ route('account.plan.cancel') }}" class="mt-5 space-y-4">
                    @csrf

                    <label class="flex items-center min-h-[40px] gap-3">
                        {{--
                            ⛔ UNCHECKED. `29` §2's rule for a consent box is a rule
                            about every box this application renders: a pre-ticked
                            confirmation confirms nothing, and `accepted` on the form
                            request is what makes a missing field a refusal rather
                            than a false.
                        --}}
                        <input
                            type="checkbox"
                            name="confirm"
                            value="1"
                            x-model="confirmed"
                            class="size-5 rounded border-rule-strong text-ink"
                        />
                        <span class="text-base text-ink">I want to end this plan.</span>
                    </label>

                    @error('confirm')
                        <p class="text-base text-alert" role="alert">{{ $message }}</p>
                    @enderror

                    <x-ui.button type="submit" variant="secondary" size="default" x-bind:disabled="!confirmed">
                        End my plan
                    </x-ui.button>
                </form>
            @else
                {{--
                    ⚠️ ABSENT RATHER THAN DISABLED (1220), AND IT NAMES WHO CAN.
                    `SubscriptionPolicy` is owner-only on purpose: a manager or an
                    agency ending the commercial relationship is the support call
                    this product should not be able to generate.
                --}}
                <p class="mt-5 text-base text-ink-2">
                    Only the account owner can end the plan. Ask them to sign in and do it
                    here.
                </p>
            @endif
        </div>
    @endif
</div>

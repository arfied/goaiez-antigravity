{{--
    Your credit — six balances, four rules, and the one place a tenant can buy
    more (3482).

    ⛔ SIX FIGURES, NEVER ONE, AND NEVER A SUM (3315). Three products, each with
    the credit the plan includes and the credit the person bought. They have
    different lifetimes and — for the assistant — a different unit entirely, so
    adding two of them together would produce a number that answers no question a
    tenant has. Nothing in this template adds anything.

    ⛔ AND FOUR RULES ARE STATED IN PLAIN WORDS, BECAUSE EACH ONE OTHERWISE READS
    AS A BUG. Monthly credit restarts and does not carry over (3440); bought
    credit never runs out of time (3307); a send draws the monthly kind first
    (3307); and a text campaign to your own list may only use the credit you
    bought (3309) — which is the one that makes a tenant with 500 monthly texts
    watch a campaign refuse to start.

    ⛔ "WAITING", NEVER "EXPIRED" (3441). While a plan is not running, purchased
    credit cannot be spent and is otherwise untouched. The owner's own instruction
    is that this wording is part of the ruling.

    OUTCOME LANGUAGE (`22`, `29` §2 rule 47). The words "pool", "ledger", "SKU",
    "grant", "top-up" and "hundredths" appear nowhere a person can read them.
    Colour is never the sole signal: every notice is a bordered panel with a
    heading and a sentence, readable in monochrome and to a screen reader, and
    `x-ui.attention-card` carries an icon and an off-screen label of its own.

    WORKS AT 320px — single column, stacking to three at `sm` — and nothing here
    is below 16px.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your credit</h1>
        <p class="mt-1 text-base text-ink-2">
            What you have left, and how to get more.
        </p>
    </div>

    {{--
        ⚠️ "WE HAVE TAKEN THE PAYMENT", NOT "HERE IS YOUR CREDIT" (2056, 3457).
        The credit is written when the payment provider tells us the money
        cleared, and never on the strength of the browser arriving back here —
        which is a redirect anybody can type. So this says what was done and what
        follows, and the figures above it are the truth.
    --}}
    @if ($paymentTaken)
        <div class="rounded-[--radius-panel] border border-rule-strong bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Payment taken</h2>

            <p class="mt-2 text-base text-ink-2">
                Thank you. Your credit shows up here as soon as your payment clears —
                usually within a minute. There is nothing else for you to do.
            </p>
        </div>
    @endif

    @if ($paymentStopped)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Nothing was charged</h2>

            <p class="mt-2 text-base text-ink-2">
                You left the payment page before paying, so nothing has been taken and
                your credit is exactly as it was.
            </p>
        </div>
    @endif

    {{--
        ⛔ THE 3441 NOTE, AND ITS WORDING IS THE RULING RATHER THAN A STYLE
        CHOICE. "Expired" would be false — the balance is intact and becomes
        spendable the moment the plan runs again — and it is the word a person
        would reach for. `CreditLedger` refuses the spend in exactly these terms.

        ⛔ AND IT IS TWO PANELS NOW, BECAUSE THERE ARE TWO WAYS TO GET HERE AND
        ONLY ONE OF THEM HAD WORDS (9332). From 2026-08-25 a `pending_checkout`
        trial is bounded at fourteen days from registration, so the commonest
        reader of this panel is somebody who registered, never added a card, and
        has just gone past the fourteenth day. *"Start your plan again"* is a
        sentence to a lapsed customer; to them it names something that never
        happened, and it does not tell them the one fact they need — that the
        free trial is what ended.

        ⛔ THE FIRST PANEL IS THE ONLY PLACE IN THIS APPLICATION THAT TELLS THAT
        PERSON ANYTHING AT ALL. The wizard invites, the marketing pages promise,
        and until this the product went quiet and simply stopped working. A
        bounded trial that ends in silence is worse than an unbounded one,
        because the person cannot tell it from a fault.

        ⚠️ IT SAYS WHAT IS STILL TRUE BEFORE IT SAYS WHAT STOPPED (`22`'s outcome
        rule): the account is there, nothing was deleted, and one action brings
        it back. Colour is not the signal — `attention` carries an icon and a
        heading; nothing here is below 16px and it wraps at 320px.
    --}}
    @unless ($planIsRunning)
        @if ($trialHasEnded)
            <x-ui.attention-card
                state="attention"
                heading="Your free trial has ended"
                :action="$mayBuy ? 'Start a plan' : null"
                :href="$mayBuy ? route('billing.index') : null"
            >
                Your account is exactly as you left it — nothing has been deleted and
                nothing has been charged. Starting a plan turns sending, publishing and
                your credit back on, and everything you have set up is waiting for you.
            </x-ui.attention-card>
        @else
            <x-ui.attention-card state="attention" heading="Your credit is waiting for you">
                While your plan is not running, the credit you bought cannot be spent.
                Nothing has expired and nothing has been taken away — start your plan
                again and it is all still there, exactly as you left it.
            </x-ui.attention-card>
        @endif
    @endunless

    {{--
        ⛔ THE COUNTDOWN, AND IT IS NOT A WARNING (9332). The trial is bounded
        now, so a person who cannot find out when it ends is being timed by a
        clock they were never shown. It states a date and offers the control; it
        does not threaten, and it is absent for everybody who is not on the
        no-card trial — `trialEndsOn` is null unless the row is
        `pending_checkout`, so a paying tenant and a lapsed one never see it.

        ⚠️ IT SITS BESIDE THE PANEL ABOVE RATHER THAN INSIDE IT, because the two
        are mutually exclusive by construction: `trialEndsOn` is a future date on
        a running trial and `trialHasEnded` is the same trial past its end, and
        `planIsRunning` is what tells them apart.
    --}}
    @if ($planIsRunning && $trialEndsOn !== null)
        <x-ui.attention-card
            state="ok"
            heading="Your free trial runs until {{ $trialEndsOn }}"
            :action="$mayBuy ? 'Add a card' : null"
            :href="$mayBuy ? route('billing.index') : null"
        >
            Everything is on and nothing is being charged. Adding a card before then keeps
            it running; if you do nothing, sending and publishing pause on that date and
            your account stays exactly as it is.
        </x-ui.attention-card>
    @endif

    {{--
        T176 P17's low-balance banner.

        ⛔ IT WARNS AND IT NEVER REFUSES. 2904's rule is that an exhausted
        balance degrades and never throws, so nothing here says a send has
        stopped — it says one is about to, which is the part a person can still
        do something about.

        ⚠️ THE THRESHOLD IS THE AUTOMATIC TOP-UP'S OWN (see `runningLow()`), so
        "running low" means the same thing here as it does in the offer further
        down this page. Two figures would let this panel stay silent on the
        morning a card was charged.

        ⚠️ IT SITS ABOVE THE FIGURES DELIBERATELY. A warning under the numbers is
        one a person reads after they have already decided nothing is wrong.

        COLOUR IS NOT THE SIGNAL (`22`): `attention-card` carries a heading and a
        sentence, readable in monochrome and to a screen reader.
    --}}
    @if ($runningLow !== [])
        <x-ui.attention-card state="attention" heading="You are running low">
            {{ ucfirst(implode(' and ', $runningLow)) }}
            {{ count($runningLow) === 1 ? 'is' : 'are' }} nearly used up. Nothing
            stops working the moment it runs out — we tell you rather than cutting
            you off — but topping up below keeps everything going without a pause.
        </x-ui.attention-card>
    @endif

    <div>
        <h2 class="font-display text-lg font-semibold text-ink">What you have</h2>

        <p class="mt-1 text-base text-ink-2">
            Each of these is counted on its own. The credit your plan includes and the
            credit you buy behave differently, so we show them apart rather than as one
            number.
        </p>

        {{-- empty-state: absent because this is the three kinds of credit the product
             sells, one card each. The list is fixed by the product and can never come
             back empty, so an invitation here would be a branch nothing can reach. --}}
        @foreach ($cards as $card)
            <div class="mt-4 rounded-[--radius-panel] border border-rule bg-card p-5">
                <h3 class="font-display text-base font-semibold text-ink">{{ $card['heading'] }}</h3>

                <dl class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-base text-ink-2">Included with your plan</dt>
                        <dd class="mt-1 font-display text-xl font-semibold text-ink">{{ $card['monthly'] }}</dd>
                        <p class="mt-1 text-base text-ink-2">Starts again on {{ $resetsOn }}.</p>
                    </div>

                    <div>
                        <dt class="text-base text-ink-2">Credit you bought</dt>
                        <dd class="mt-1 font-display text-xl font-semibold text-ink">{{ $card['bought'] }}</dd>
                        <p class="mt-1 text-base text-ink-2">Never runs out of time.</p>
                    </div>
                </dl>
            </div>
        @endforeach
    </div>

    {{--
        ⛔ THE ONE THAT LOOKS LIKE A FAULT (3309). A tenant with a full month of
        text credit and nothing bought is refused a campaign, correctly, and has
        no way to work out why. Saying it here — before they meet it — is the
        whole reason this panel exists.
    --}}
    @if ($boughtTextCredit === 0)
        <x-ui.attention-card state="attention" heading="A text campaign needs credit you have bought">
            You have not bought any text credit yet, so a campaign to your own customer
            list will wait until you do. Review invites, missed-call replies and the
            chat are not affected — they use the credit your plan includes.
        </x-ui.attention-card>
    @endif

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">How your credit works</h2>

        <div class="mt-3 space-y-4">
            <div>
                <h3 class="text-base font-semibold text-ink">Your plan includes credit every month</h3>
                <p class="mt-1 text-base text-ink-2">
                    It starts again on {{ $resetsOn }}, whether you used it or not. Whatever
                    is left at the end of the month does not carry over.
                </p>
            </div>

            <div>
                <h3 class="text-base font-semibold text-ink">Credit you buy never runs out of time</h3>
                <p class="mt-1 text-base text-ink-2">
                    It stays on your account until you use it. Nothing about it changes at
                    the end of the month.
                </p>
            </div>

            <div>
                <h3 class="text-base font-semibold text-ink">We use the credit your plan includes first</h3>
                <p class="mt-1 text-base text-ink-2">
                    Everything you send takes from that first, and only starts using the
                    credit you bought once it has run out — so the part with a date on it
                    goes first.
                </p>
            </div>

            <div>
                <h3 class="text-base font-semibold text-ink">Text campaigns to your own list are the exception</h3>
                <p class="mt-1 text-base text-ink-2">
                    A campaign to your own customer list always uses credit you bought, and
                    never the credit your plan includes. If the only text credit you have is
                    the kind your plan includes, a campaign waits until you buy some. Review
                    invites, missed-call replies and the chat work the ordinary way.
                </p>
            </div>
        </div>
    </div>

    <div>
        <h2 class="font-display text-lg font-semibold text-ink">Get more credit</h2>

        @unless ($planIsRunning)
            {{--
                ⛔ THE PAGE SAID THIS CREDIT COULD NOT BE SPENT AND OFFERED TO SELL
                MORE OF IT (3867). Two panels on one screen, each correct on its
                own: the note at the top is 3441's ruling, and a live "Pay $250"
                button under it was an invitation to buy something the same screen
                had just said was unusable — a refund request with a receipt.
                `AutoTopUps::refusalFor()` had already argued this for the
                automatic path in exactly those words.

                ⚠️ THE BUTTONS ARE ABSENT RATHER THAN DISABLED (1220), AND THE
                REFUSAL IS NOT THIS MARKUP: `Credit::buy()` and `CreditTopUps`
                both refuse independently (398).
            --}}
            <x-ui.attention-card class="mt-3" state="attention" heading="Start your plan to buy more">
                While your plan is not running there is nothing to buy — credit can
                only be spent on a running plan, so buying more would not help you
                today. Start your plan and this is here waiting, along with
                everything you have already bought.
            </x-ui.attention-card>
        @elseif (! $paymentsAreAvailable)
            {{--
                ⛔ **THE PACK BUTTONS WERE LIVE AND THE PRESS ANSWERED A 500**
                (9296). With no gateway credential set — the state every
                deployment of this application has been in — `buy()` reached
                `StripeApi` (or `AuthorizeNetApi` on a stored card) and raised
                out of the Livewire action, which Livewire renders as a
                **full-page modal containing Laravel's error page**. It also
                left a `pending` `credit_purchases` row behind.

                ⚠️ THE BUTTONS ARE ABSENT RATHER THAN DISABLED (1220), the same
                shape the plan-not-running branch above takes, and for the
                reason `arrange()`'s docblock already gives: offering something
                that cannot work is worse than refusing it, because the person
                believes it is available.

                ⛔ THE REFUSAL IS NOT THIS MARKUP (398). `Credit::buy()` and
                both clients refuse independently, and the test drives the
                action rather than the page.
            --}}
            <x-ui.attention-card class="mt-3" state="attention" heading="We cannot take payments just now">
                Nothing has been charged and nothing has stopped. The credit you already
                have is untouched and still spends. Reply to any email from us and we
                will sort it out with you.
            </x-ui.attention-card>
        @elseif ($mayBuy)
            <p class="mt-1 text-base text-ink-2">
                Pick what you want and we will show you the amount before anything is
                charged.
            </p>

            {{--
                ⛔ CONFIRM LIVES HERE AND NOWHERE ELSE (`CLAUDE.md`: "anything that
                spends money"). Pressing a pack opens this panel; the panel names the
                amount and what it buys, and the box under it starts unticked. The
                sentence and the figure come from the component's own methods, so the
                wording recorded against the purchase is the wording on this screen —
                which is the question a chargeback asks.
            --}}
            @if ($pendingProduct !== null)
                <div class="mt-4 rounded-[--radius-panel] border border-rule-strong bg-card p-5">
                    <h3 class="font-display text-base font-semibold text-ink">Confirm this payment</h3>

                    <p class="mt-2 text-base text-ink-2">{{ $this->confirmationWording() }}</p>

                    <p class="mt-2 text-base text-ink-2">
                        @if ($paysWithCardOnFile)
                            This charges the card you already have on file, now.
                        @else
                            We will take you to our payment page to enter your card. Nothing
                            is charged until you finish there.
                        @endif
                    </p>

                    <form wire:submit="buy" class="mt-5 space-y-4">
                        <label class="flex items-start gap-3">
                            {{--
                                ⛔ UNTICKED, ALWAYS, AND RESET EVERY TIME THE PANEL
                                OPENS. A pre-ticked box confirms nothing, and `accepted`
                                is what makes a missing field a refusal rather than a
                                false.
                            --}}
                            <input
                                type="checkbox"
                                wire:model="confirmed"
                                @error('confirmed') aria-invalid="true" aria-describedby="confirm-purchase-error" @enderror
                                class="mt-1 size-5 rounded border-rule-strong text-ink"
                            />
                            <span class="text-base text-ink">
                                Yes, charge me {{ $this->confirmationAmount() }} now.
                            </span>
                        </label>

                        @error('confirmed')
                            <p id="confirm-purchase-error" class="text-base text-alert" role="alert">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.submit target="buy" size="default" busy="Paying…">
                                @if ($paysWithCardOnFile)
                                    Pay {{ $this->confirmationAmount() }}
                                @else
                                    Continue to payment
                                @endif
                            </x-ui.submit>

                            <x-ui.button
                                type="button"
                                variant="quiet"
                                size="default"
                                wire:click="cancelPurchase"
                            >Not now</x-ui.button>
                        </div>
                    </form>
                </div>
            @endif

            @forelse ($packs as $pack)
                <div class="mt-3 flex flex-col gap-3 rounded-[--radius-panel] border border-rule bg-card p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="font-display text-base font-semibold text-ink">{{ $pack['label'] }}</p>
                        <p class="mt-1 text-base text-ink-2">{{ $pack['price'] }}</p>
                    </div>

                    <x-ui.button
                        variant="secondary"
                        size="default"
                        wire:click="choose('{{ $pack['product'] }}', '{{ $pack['tier'] }}')"
                        wire:loading.attr="disabled"
                        wire:target="choose('{{ $pack['product'] }}', '{{ $pack['tier'] }}')"
                    >Choose</x-ui.button>
                </div>
            @empty
                {{--
                    ⚠️ A REAL STATE RATHER THAN A DEFENSIVE BRANCH. Every price and
                    quantity here is editable in Ops (3415), and `TopUpCatalog`
                    refuses a zero or negative one rather than selling it — so one
                    bad edit empties this list, and saying so beats drawing a row
                    with no price in it. No action, because there is genuinely
                    nothing the reader can press to fix it.
                --}}
                <x-ui.empty-state class="mt-3" heading="Nothing is on sale right now">
                    We cannot show prices at the moment, so there is nothing to buy on this
                    page. Ask us and we will sort it out — your existing credit is
                    untouched.
                </x-ui.empty-state>
            @endforelse
        @else
            {{--
                ⚠️ ABSENT RATHER THAN DISABLED (1220), AND IT NAMES WHO CAN.
                `SubscriptionPolicy::purchaseCredit()` is owner-only because buying
                credit charges the card on file — a manager or an agency spending
                somebody else's money is the support call this product should not
                be able to generate.
            --}}
            <p class="mt-1 text-base text-ink-2">
                Only the account owner can buy credit. Ask them to sign in and do it
                here. What you have is shown above, and everything keeps sending until
                it runs out.
            </p>
        @endif
    </div>

    {{--
        Buying more automatically — the arrangement (3517's first named gap).

        ⛔ NOTHING HERE CREATES AN ARRANGEMENT BY BEING LOOKED AT (3488, 2064).
        Off is the absence of a row, so a tenant who has never chosen sees "Off"
        rendered from nothing at all, and only the tick box below writes one.

        ⛔ CONFIRM IS ON THE ARRANGEMENT AND EVERY LATER CHARGE INHERITS IT
        (2064, 3490), which is why this panel names the amount, what it buys and
        the monthly limit before the box — the sentence it prints is the sentence
        stored against every charge made under it, months later, with nobody
        watching.

        ⚠️ AND THE HONEST GAP IS ON THE PAGE RATHER THAN BEHIND IT (3517). If an
        arrangement stops itself, nothing tells the tenant; this screen is the
        only place it shows, and the intro says so instead of implying a message
        that does not exist.

        OUTCOME LANGUAGE (`22`): "top-up", "arrangement", "ceiling", "SKU" and
        "suspended" appear nowhere a person can read them. Every state is a word
        and a sentence, never a colour — the stopped state is an
        `x-ui.attention-card`, which carries its own icon and off-screen label.
    --}}
    <div>
        <h2 class="font-display text-lg font-semibold text-ink">Buy more automatically</h2>

        <p class="mt-1 text-base text-ink-2">
            We can buy more credit for you when you are running low, so nothing stops
            while you are busy. You choose the most we may spend in a month, and you
            can turn it off whenever you like. If we ever have to stop — a card that
            will not go through, or a price that has changed — you will see it here.
            We do not send you a message about it.
        </p>

        @if ($mayBuy)
            @unless ($paysWithCardOnFile)
                {{--
                    ⛔ REFUSED RATHER THAN OFFERED (3487, 3501). The automatic
                    charge bills a card we already hold; with none, every nightly
                    attempt counts as a refusal and the whole thing stops itself
                    after three. Offering it would leave a tenant believing it was
                    running.
                --}}
                <x-ui.attention-card
                    class="mt-4"
                    state="attention"
                    heading="This needs a card saved on your account"
                >
                    We can only do this with a card we already hold, because there is
                    nobody at the keyboard when we buy. Save a card on your plan and
                    this is waiting for you here.
                </x-ui.attention-card>
            @endunless

            @if ($arrangingProduct !== null)
                <div class="mt-4 rounded-[--radius-panel] border border-rule-strong bg-card p-5">
                    <h3 class="font-display text-base font-semibold text-ink">
                        Confirm what we may spend
                    </h3>

                    <form wire:submit="agreeToAutomatic" class="mt-4 space-y-4">
                        <div>
                            <label
                                for="automatic-limit"
                                class="block text-base font-semibold text-ink"
                            >The most we may spend in a month</label>

                            {{--
                                ⛔ "THE MONTH", NOT "THIS ARRANGEMENT". Changing the
                                limit used to start the month's counting again, which
                                made lowering it the cheapest way to spend more; the
                                service counts by account and product now, and this
                                sentence is the promise that makes.
                            --}}
                            <p id="automatic-limit-note" class="mt-1 text-base text-ink-2">
                                We stop when the month's buying reaches this, and buy
                                nothing else until the next month. Changing it counts what
                                we have already bought this month — it does not start the
                                month again.
                            </p>

                            {{--
                                ⚠️ WHOLE MULTIPLES OF ONE PAYMENT, AND THAT IS THE
                                HONEST SET. A limit part-way between two payments
                                stops in exactly the same place as the one below it,
                                so offering it would offer a difference that does not
                                exist.
                            --}}
                            <select
                                id="automatic-limit"
                                wire:model.live="ceilingCents"
                                @error('ceilingCents') aria-invalid="true" @enderror
                                aria-describedby="automatic-limit-note @error('ceilingCents') automatic-limit-error @enderror"
                                class="mt-2 w-full rounded-[--radius-control] border border-rule-strong bg-card px-3 py-2 text-base text-ink sm:w-auto"
                            >
                                {{--
                                    ⛔ `@selected` IS WHAT MAKES THIS BOX AGREE WITH
                                    THE SENTENCE UNDER IT. The markup is rendered on
                                    the server and a browser shows the first option
                                    when none is marked — so a tenant pressing
                                    "Change the limit" on a $200 arrangement was
                                    shown $50 in the box while the agreement below
                                    read $200, and only a deliberate change would
                                    have reconciled them. The property was correct
                                    throughout, which is exactly why the component
                                    test could not see it: it asserted
                                    `ceilingCents` and never the rendered
                                    attribute. `components/account/location-picker.blade.php`
                                    is the sibling that already does this.
                                --}}
                                @foreach ($this->ceilingChoices() as $choice)
                                    <option
                                        value="{{ $choice['cents'] }}"
                                        @selected($choice['cents'] === $ceilingCents)
                                    >{{ $choice['label'] }}</option>
                                @endforeach
                            </select>

                            {{-- WCAG 2.2 AA: the message is tied to the control it
                                 is about, not merely announced on its own. --}}
                            @error('ceilingCents')
                                <p id="automatic-limit-error" class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                            @enderror
                        </div>

                        <p class="text-base text-ink-2">{{ $this->arrangementWording() }}</p>

                        <label class="flex items-start gap-3">
                            {{--
                                ⛔ UNTICKED, ALWAYS. `accepted` makes a missing field a
                                refusal, and `updatedCeilingCents()` unticks it again
                                whenever the limit above changes — the select is `.live`
                                and this box is deferred, so without that a tick made at
                                $50 could be submitted as agreement to $500.

                                ⛔ AND IT NAMES BOTH FIGURES. The riskiest box on this
                                screen had the weakest label: "buy more for me when I am
                                running low" authorises an indefinite series of charges
                                and disclosed less than the one-off box above it, which
                                says "Yes, charge me $50 now."
                            --}}
                            <input
                                type="checkbox"
                                wire:model="automaticConfirmed"
                                @error('automaticConfirmed') aria-invalid="true" aria-describedby="confirm-automatic-error" @enderror
                                class="mt-1 size-5 rounded border-rule-strong text-ink"
                            />
                            <span class="text-base text-ink">
                                Yes, charge me {{ $this->arrangementAmount() }} whenever I am
                                running low, and never more than
                                {{ $this->arrangementCeiling() }} a month.
                            </span>
                        </label>

                        @error('automaticConfirmed')
                            <p id="confirm-automatic-error" class="text-base text-alert" role="alert">{{ $message }}</p>
                        @enderror

                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.submit target="agreeToAutomatic" size="default" busy="Setting up…">
                                Set this up
                            </x-ui.submit>

                            <x-ui.button
                                type="button"
                                variant="quiet"
                                size="default"
                                wire:click="dismissArrangement"
                            >Not now</x-ui.button>
                        </div>
                    </form>
                </div>
            @endif
        @else
            {{-- ABSENT RATHER THAN DISABLED (1220), AND IT NAMES WHO CAN. --}}
            <p class="mt-1 text-base text-ink-2">
                Only the account owner can set this up. What it is doing today is
                below, so you can see whether anything is going to run out.
            </p>
        @endif

        {{-- empty-state: absent because this is the three kinds of credit the
             product sells, one row each, derived from the enum's own cases. The
             list is fixed by the product and can never come back empty, so an
             invitation here would be a branch nothing can reach. --}}
        @foreach ($automatic as $row)
            <div class="mt-4 rounded-[--radius-panel] border border-rule bg-card p-5">
                <h3 class="font-display text-base font-semibold text-ink">{{ $row['heading'] }}</h3>

                @if ($row['state'] === 'on')
                    <p class="mt-2 text-base text-ink">
                        On. We charge you {{ $row['amount'] }} for more whenever you are
                        running low, and never more than {{ $row['ceiling'] }} a month.
                    </p>

                    @unless ($planIsRunning)
                        <p class="mt-2 text-base text-ink-2">
                            While your plan is not running we will not buy anything, and
                            nothing is charged.
                        </p>
                    @endunless

                    @if ($mayBuy)
                        <div class="mt-3 flex flex-wrap items-center gap-3">
                            <x-ui.button
                                variant="secondary"
                                size="default"
                                wire:click="arrange('{{ $row['product'] }}')"
                            >Change the limit</x-ui.button>

                            <x-ui.button
                                variant="quiet"
                                size="default"
                                wire:click="turnOffAutomatic('{{ $row['product'] }}')"
                            >Turn off</x-ui.button>
                        </div>
                    @endif
                @elseif ($row['state'] === 'stopped')
                    {{--
                        ⚠️ OUR SENTENCE, NEVER THE ONE ON THE ROW. `stopped_reason`
                        is written for whoever has to explain the account to
                        somebody and carries our words for our machinery; the state
                        is derived instead, so a customer reads a customer's
                        sentence.
                    --}}
                    <x-ui.attention-card
                        class="mt-2"
                        state="attention"
                        heading="We have stopped buying this for you"
                    >
                        @if ($row['stopped'] === 'card')
                            Your card would not go through, so we stopped rather than keep trying.
                            Check the card on your plan, then set this up again. Nothing has been charged.
                        @elseif ($row['stopped'] === 'price')
                            The price changed, so the amount you agreed to is out of date.
                            We stopped rather than charge you something you had not seen.
                            Set it up again to agree the new amount.
                        @elseif ($row['stopped'] === 'unconfirmed')
                            {{--
                                ⛔ THE ONE STOPPED SENTENCE THAT MAY NOT SAY "NOTHING
                                HAS BEEN CHARGED". The payment was sent and the answer
                                never came back, so the money may have moved — and it
                                is counted against this month's limit for that reason.
                                Saying otherwise here would be the reassurance that
                                made the defect expensive.
                            --}}
                            We sent a payment and did not get an answer back, so we stopped
                            rather than risk sending it twice. It may have gone through —
                            your credit shows it here if it did, usually within a few minutes.
                            Set it up again when you are ready, and ask us if it does not look right.
                        @else
                            {{--
                                ⛔ THE THIRD BRANCH IS NOT DEFENSIVE PADDING. The cause
                                used to be inferred from the failure count, so every
                                reason that was not the card read as "The price changed"
                                — a sentence about somebody's own money that was simply
                                untrue. `AutoTopUps::stoppedCause()` answers `other` for
                                anything it does not recognise, and this is what `other`
                                says: we stopped, and here is what to do about it.
                            --}}
                            We have stopped buying this for you and it needs setting up again.
                            Nothing has been charged. Ask us if you would like to know why.
                        @endif
                    </x-ui.attention-card>

                    {{--
                        ⚠️ GATED ON THE CARD, THE SAME WAY THE OFF STATE'S BUTTON
                        IS. `arrange()` refuses without one and says so in a toast,
                        which is correct — but offering a button whose only outcome
                        is an error message is a press that teaches nothing. The
                        panel above already tells a cardless tenant what to do.
                    --}}
                    @if ($mayBuy && $paysWithCardOnFile)
                        <div class="mt-3">
                            <x-ui.button
                                variant="secondary"
                                size="default"
                                wire:click="arrange('{{ $row['product'] }}')"
                            >Set it up again</x-ui.button>
                        </div>
                    @endif
                @else
                    <p class="mt-2 text-base text-ink">
                        Off. We never buy any of this for you without being asked.
                    </p>

                    @if ($row['offer'] !== null)
                        <p class="mt-1 text-base text-ink-2">
                            If you turn it on we would charge you {{ $row['offer'] }} each
                            time you run low.
                        </p>
                    @endif

                    @if ($mayBuy && $paysWithCardOnFile && $row['offer'] !== null)
                        <div class="mt-3">
                            <x-ui.button
                                variant="secondary"
                                size="default"
                                wire:click="arrange('{{ $row['product'] }}')"
                            >Turn this on</x-ui.button>
                        </div>
                    @endif
                @endif
            </div>
        @endforeach
    </div>

    {{--
        ⛔ THE RECEIPT 3536 NAMED AND 4665 RECORDED AS UNBUILT (4842). Every
        payment for credit this account has made, from `credit_purchases` — the
        table that has carried a full confirmation record since 3426 with nothing
        to show it back.

        ⛔ "PAYMENTS FOR CREDIT" AND NEVER "YOUR PAYMENTS" OR "YOUR INVOICES"
        (4843). Nothing in this schema records a plan charge — the subscription's
        payments live at the gateway — so a list under a heading that promised all
        of them would tell a customer we had never billed them for the plan.

        empty-state: present, because a tenant who has bought nothing is the
        ordinary case rather than an edge one, and a heading with nothing under it
        reads as a payment that has gone missing.

        Rows rather than a table, so it reflows at 320px. Colour is never the
        signal: each line's state is a sentence.
    --}}
    <div class="space-y-3">
        <h2 class="font-display text-lg font-semibold text-ink">Payments for credit</h2>

        @if ($receipts === [])
            {{--
                ⛔ THE COMPONENT AND NOT A HAND-ROLLED PARAGRAPH, AND THE LINT WAS
                RIGHT TO REFUSE THE FIRST DRAFT (3002). `x-ui.empty-state` shipped
                with zero consumers while fourteen views hand-rolled a branch; a
                fifteenth would be that defect one screen further on.

                NO ACTION SLOT, DELIBERATELY. "Get more credit" is already a
                section of this very page a few hundred pixels up, so a button
                here would be a second door into the room the reader is standing
                in — and `29` §5.7's invitation is already carried by the sentence.
            --}}
            <x-ui.empty-state heading="Nothing bought yet">
                Anything you buy shows up here, with what it cost and what it added.
            </x-ui.empty-state>
        @else
            <p class="text-base text-ink-2">
                What you have paid us for credit. Your plan is charged separately — Your
                plan, under More, has that.
            </p>

            <ul class="space-y-3">
                @foreach ($receipts as $receipt)
                    <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                        <p class="text-base text-ink">
                            <span class="font-semibold">{{ $receipt['amount'] }}</span>
                            on {{ $receipt['paidOn'] }}
                        </p>

                        <p class="mt-1 text-base text-ink-2">{{ $receipt['bought'] }}</p>

                        {{--
                            ⚠️ THREE SENTENCES BECAUSE THERE ARE THREE HONEST ONES.
                            A credited payment is finished; an authorized one has
                            been taken and the credit is on its way, which is a real
                            gap of seconds to minutes; and a mismatched one is a
                            charge whose amount we could not reconcile, which the
                            customer must be told about rather than shown as
                            complete. `CreditPurchaseStatus::moneyMoved()` decides
                            which rows reach this list at all.
                        --}}
                        @if ($receipt['status'] === 'credited')
                            <p class="mt-1 text-base text-ink-2">Added to your balance.</p>
                        @elseif ($receipt['status'] === 'authorized')
                            <p class="mt-1 text-base text-ink-2">
                                Paid. We are adding it to your balance — this usually takes a
                                few minutes.
                            </p>
                        @else
                            <p class="mt-1 text-base text-ink-2">
                                We are checking this payment against your bank. Nothing is
                                needed from you, and ask us if it does not look right.
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            @if ($hasOlderReceipts)
                {{--
                    ⚠️ A LIST THAT STOPS WITHOUT SAYING SO READS AS A MISSING
                    PAYMENT. A cap is the smaller support surface than a pager on a
                    page whose subject is a balance — see `RECEIPTS_SHOWN`.
                --}}
                <p class="text-base text-ink-2">
                    These are your most recent payments. Ask us for anything older and we
                    will send it.
                </p>
            @endif
        @endif
    </div>
</div>

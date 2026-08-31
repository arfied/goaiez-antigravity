{{--
    Account 360 v1 (`28` §9.3) and the door into a customer's account (§9.4).

    Deliberately not a list — see the component for the decision and what would
    change it.

    COLOUR IS NOT THE SIGNAL (`22`). Every state on this page is carried by its
    words: an integration that needs attention says so, a health-information
    tenant is labelled rather than tinted, and the two impersonation modes are
    told apart by what each one asks for — choosing "make changes" reveals the
    ticket field, which is the strongest signal here and works in monochrome and
    to a screen reader.
--}}

<div class="w-full max-w-3xl space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Open a customer’s account</h1>
        <p class="mt-1 text-base text-ink-2">
            See what they see, or fix something for them. Either way it is recorded
            against their account and they can see you were there.
        </p>
    </div>

    @if ($account === null)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Account number or email</span>
                <input
                    id="support-account-reference"
                    type="text"
                    wire:model="reference"
                    wire:keydown.enter="resolve"
                    class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    placeholder="The email they signed up with"
                />
            </label>

            @error('reference')
                <p class="mt-3 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror

            <div class="mt-5">
                <x-ui.button wire:click="resolve" type="button">Find account</x-ui.button>
            </div>
        </div>
    @else
        {{-- The header (`28` §9.3): who this is, in one glance. --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">{{ $account->name }}</h2>
                <p class="font-mono text-sm text-ink-2">Account {{ $account->businessId }}</p>
            </div>

            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-ink-2">Owner</dt>
                    <dd class="text-base text-ink">
                        {{ $account->ownerName ?? 'Not recorded' }}
                        @if ($account->ownerEmail)
                            <span class="block text-sm text-ink-2">{{ $account->ownerEmail }}</span>
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-ink-2">Plan</dt>
                    <dd class="text-base text-ink">
                        @if ($account->subscription)
                            {{ $account->subscription->plan?->label() ?? 'Not set' }}
                            <span class="block text-sm text-ink-2">
                                {{ str($account->subscription->status?->value ?? 'unknown')->replace('_', ' ')->ucfirst() }}
                                @if ($account->subscription->trial_ends_at)
                                    — trial ends {{ $account->subscription->trial_ends_at->toFormattedDayDateString() }}
                                @elseif ($account->noCardTrialEndsAt)
                                    {{--
                                        ⛔ THE FREE TRIAL THE ROW CANNOT RECORD (9332).
                                        `trial_ends_at` is written only by the two
                                        card-bearing paths, so the branch above is blank
                                        for every `pending_checkout` account — which is
                                        every account that has registered and not bought.
                                        Since 2026-08-25 that trial ENDS, and this is the
                                        only place an operator can read when.
                                    --}}
                                    — free trial
                                    {{ $account->noCardTrialEndsAt->isPast() ? 'ended' : 'ends' }}
                                    {{ $account->noCardTrialEndsAt->toFormattedDayDateString() }}
                                @endif
                            </span>
                        @else
                            Nothing recorded
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-ink-2">Signed up</dt>
                    <dd class="text-base text-ink">
                        {{ $account->createdAt?->toFormattedDayDateString() ?? 'Not recorded' }}
                    </dd>
                </div>

                {{--
                    `28` §9.3's header flags. TWO ROWS RATHER THAN ONE
                    "stopped" line, because an account can be both and they
                    are cleared by different people — an agent who cannot
                    tell them apart lifts a hold and tells the owner their
                    product is back when it is not.

                    Words, never colour: "On hold" and "Paused" are the
                    signal, and "Running" is what the absence of both says
                    out loud rather than by omission.
                --}}
                <div>
                    <dt class="text-sm font-medium text-ink-2">Status</dt>
                    <dd class="text-base text-ink">
                        @if ($account->suspended)
                            On hold
                            <span class="block text-sm text-ink-2">
                                Put on hold by {{ $account->suspendedBy ?? 'someone here' }}.
                                @if ($account->suspensionReason)
                                    “{{ $account->suspensionReason }}”
                                @endif
                            </span>
                        @endif

                        @if ($account->paused)
                            Paused
                            <span class="block text-sm text-ink-2">
                                @if ($account->pauseReason)
                                    “{{ $account->pauseReason }}”
                                @else
                                    The owner paused this themselves.
                                @endif
                            </span>
                        @endif

                        @if (! $account->suspended && ! $account->paused)
                            Running
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-ink-2">Handling</dt>
                    <dd class="text-base text-ink">
                        {{ $account->classification->label() }}
                        @if ($account->classification === \App\Enums\DataClassification::Phi)
                            <span class="block text-sm text-ink-2">
                                {{ $account->baaInForce ? 'Agreement in force' : 'No agreement in force' }}
                            </span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Overview: what they have running. --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">What they have running</h2>

            <h3 class="mt-4 text-sm font-medium text-ink-2">Locations</h3>

            {{--
                ⛔ THIS LINE CARRIED "Autopilot on" OR "Autopilot off" PER
                LOCATION, AND IT SAID "on" FOR EVERY LOCATION ON THE
                PLATFORM, UNCONDITIONALLY (8580). It read
                `locations.is_autopilot_active`, whose every occurrence was
                its own migration line, an index over it, a cast, a factory
                seeding `true`, and this render — **no writer anywhere in
                `app/`**. The only value the column has ever held is its
                schema default of `true`, and no operator action could make
                it say "off". An agent triaging *"nothing has happened for
                three weeks"* was shown a green light generated by a
                `DEFAULT true` clause.

                ⚠️ THE REPLACEMENT IS NOT A BETTER INDICATOR, BECAUSE THERE
                IS NO PER-LOCATION AUTOPILOT STATE TO INDICATE. What stops
                automations running is the gates in `AutopilotJob::handle()`
                — the config kill switch, the suspension, the owner's pause,
                and a per-automation `isEnabled()` that returns `true`
                unconditionally — and the first three are **per business**.
                So the true answer is the Status block above, which is
                already correct, and what belongs here is a sentence sending
                the agent to it rather than a second reading of it.

                ⚠️ `google_place_id` AND `review_count` STAY, AND CHECKING
                THEM IS WHY THIS IS ONE REMOVAL AND NOT THREE. Both are
                live: `Places\PlaceConfirmation` writes the first,
                `Visibility\CompetitorSignals::recordOurOwnRating()` the
                second. `CLAUDE.md`'s `current_rating` pair is the warning —
                one looked alive and was dead, one looked dead and was alive
                — so every fact on this line was traced to a writer before it
                was kept or dropped.
            --}}
            {{--
                ⚠️ IT SAYS WHAT AN OPERATOR CAN CHANGE, NOT WHY NOTHING RAN,
                AND THE DIFFERENCE IS 314-316. "The status above is the
                answer" would be a second overclaim in the place the first
                one was removed from: a platform kill switch
                (`autopilot.kill_switch`) also stops every automation and is
                on none of these screens. So this sentence promises only the
                two things a person here can act on, and stops.
            --}}
            <p class="mt-1 text-sm text-ink-2">
                No autopilot switch is per location — automations stop for the whole
                account or not at all. The status above is the part an owner or an
                operator can change.
            </p>

            @if ($account->locations->isEmpty())
                <p class="mt-2 text-base text-ink-2">No locations yet.</p>
            @else
                <ul class="mt-2 space-y-2">
                    @foreach ($account->locations as $location)
                        <li class="text-base text-ink">
                            {{ $location->name }}
                            <span class="block text-sm text-ink-2">
                                {{ $location->google_place_id ? 'Google listing confirmed' : 'No Google listing yet' }}
                                @if ($location->review_count)
                                    · {{ $location->review_count }} reviews
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3 class="mt-5 text-sm font-medium text-ink-2">Connections</h3>
            @if ($account->integrations->isEmpty())
                <p class="mt-1 text-base text-ink-2">Nothing connected yet.</p>
            @else
                <ul class="mt-1 space-y-1">
                    @foreach ($account->integrations as $connection)
                        <li class="text-base text-ink">
                            {{ $connection->display_label ?: $connection->provider->value }}
                            <span class="text-sm text-ink-2">
                                — {{ str($connection->status->value)->replace('_', ' ') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <h3 class="mt-5 text-sm font-medium text-ink-2">Setup</h3>
            <p class="mt-1 text-base text-ink">
                @if ($account->setup === null)
                    Not started.
                @elseif ($account->setup->completed)
                    Finished.
                @else
                    On “{{ $account->setup->current_step?->label() ?? 'the first step' }}”.
                @endif
            </p>
        </div>

        {{--
            What Account 360 does not have yet, named rather than left as
            empty panels. An empty tab reads as a customer with no history;
            this reads as a feature that does not exist, which is the true
            statement (decision 566, and `CredentialsAdmin`'s precedent for
            putting the reason on the screen).
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Not here yet</h2>
            <ul class="mt-2 space-y-1 text-base text-ink-2">
                <li>Timeline — nothing keeps support tickets or internal notes yet.</li>
                <li>Billing actions — refunds and invoices need Stripe, which is not connected.</li>
                <li>Support — there is no ticketing system to show tickets from.</li>
                <li>Usage &amp; cost — nothing records per-tenant usage yet.</li>
                <li>Health and lifecycle — no health score or lifecycle stage is calculated anywhere.</li>
            </ul>
        </div>

        {{--
            `28` §9.5's lifecycle actions, on §9.3's quick-actions rail.

            ⚠️ RENDERED PER ABILITY, NEVER PER ROLE. This screen is behind
            the wider `SupportAccess` gate — a `support_agent` is legitimately
            here — so each block asks its own ability, and each action
            re-authorizes on the way in. Hiding a button is not a security
            control (decision 630); it is what stops an agent being offered
            something they will be refused.

            What is deliberately absent, and named rather than silent:
            Cancel, schedule-cancel and reactivate are `28` §11.5's Billing
            actions and need Stripe's subscription surface. Delete / erasure
            and consent audits live on the Data requests queue (linked in the
            nav) — not on this rail — so the two-person rule and due dates
            have one home. Restore of an *executed* deletion does not exist;
            cancelling a pending one is on that queue.
        --}}
        @if ($maySuspend || $mayPause)
            <div
                class="rounded-[--radius-panel] border border-rule bg-card p-5"
                role="group"
                aria-label="Stop or start this account"
            >
                <h2 class="font-display text-lg font-semibold text-ink">Stop or start this account</h2>

                @if ($maySuspend && $account->suspended)
                    <p class="mt-2 text-base text-ink-2">
                        This account is on hold. The owner sees a plain page telling them
                        to talk to us, and cannot start it again themselves.
                    </p>

                    @if ($confirming === 'lift')
                        <div class="mt-4 rounded-[--radius-control] border border-rule p-4">
                            <p class="text-base text-ink">
                                Take this account off hold? Someone put it on hold for a
                                reason — read it above before you do.
                            </p>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <x-ui.button wire:click="liftSuspension" type="button">Yes, take it off hold</x-ui.button>
                                <button
                                    type="button"
                                    wire:click="cancelConfirm"
                                    class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                                >
                                    Leave it on hold
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="mt-4">
                            <x-ui.button wire:click="confirm('lift')" type="button">Take off hold</x-ui.button>
                        </div>
                    @endif
                @elseif ($maySuspend)
                    <p class="mt-2 text-base text-ink-2">
                        Putting an account on hold stops everything we do for them and
                        replaces their screens with a page telling them to talk to us.
                        Their customers can still leave feedback, and their subscription
                        is untouched.
                    </p>

                    @if ($confirming === 'suspend')
                        <label class="mt-4 flex flex-col gap-1">
                            <span class="text-sm font-medium text-ink-2">Why (kept on the account, not shown to them)</span>
                            <textarea
                                wire:model="lifecycleReason"
                                rows="2"
                                class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                placeholder="Google reported review gating on this listing — ticket SUP-1042."
                            ></textarea>
                        </label>

                        @error('lifecycleReason')
                            <p class="mt-3 text-base text-alert" role="alert">{{ $message }}</p>
                        @enderror

                        <div class="mt-4 flex flex-wrap gap-3">
                            <x-ui.button wire:click="suspend" type="button">Put this account on hold</x-ui.button>
                            <button
                                type="button"
                                wire:click="cancelConfirm"
                                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                            >
                                Cancel
                            </button>
                        </div>
                    @else
                        <div class="mt-4">
                            <x-ui.button wire:click="confirm('suspend')" type="button">Put on hold</x-ui.button>
                        </div>
                    @endif
                @endif

                @if ($mayPause)
                    <div class="mt-6 border-t border-rule pt-5">
                        <h3 class="text-sm font-medium text-ink-2">Pause for them</h3>

                        @if ($account->paused)
                            <p class="mt-1 text-base text-ink-2">
                                Everything is paused for this account. The owner can start it
                                again themselves from their own settings.
                            </p>
                            <div class="mt-4">
                                <x-ui.button wire:click="resumeClient" type="button">Start everything again</x-ui.button>
                            </div>
                        @else
                            <p class="mt-1 text-base text-ink-2">
                                The same thing the owner’s own Pause button does, with your
                                name on it. They can start it again whenever they want.
                            </p>

                            <label class="mt-3 flex flex-col gap-1">
                                <span class="text-sm font-medium text-ink-2">Why (the owner sees this)</span>
                                <textarea
                                    wire:model="lifecycleReason"
                                    rows="2"
                                    class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                    placeholder="You asked us to hold everything while you rebrand."
                                ></textarea>
                            </label>

                            @error('lifecycleReason')
                                <p class="mt-3 text-base text-alert" role="alert">{{ $message }}</p>
                            @enderror

                            <div class="mt-4">
                                <x-ui.button wire:click="pauseClient" type="button">Pause everything for them</x-ui.button>
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{--
            `28` §9.1, §9.3's "grant credits" quick action.

            ⚠️ RENDERED PER ABILITY, NOT PER ROLE — the same rule as the
            lifecycle rail above. `support_agent`'s ceiling is shown so an
            agent knows their limit before they type an amount over it, not
            as the enforcement: `CreditGrants::grant()` is what actually
            refuses it.
        --}}
        @if ($mayGrantCredits)
            <div
                class="rounded-[--radius-panel] border border-rule bg-card p-5"
                role="group"
                aria-label="Grant credits"
            >
                <h2 class="font-display text-lg font-semibold text-ink">Grant credits</h2>

                {{--
                    3437 item (3): this screen reported no balances at all, so
                    an operator granted blind. All six are here — three
                    products, each with the two pools that have two different
                    lifetimes.

                    ⛔ NOTHING IS ADDED TO ANYTHING (3428, 3315). Not one
                    product's two pools, and certainly not the three products:
                    they are counted in two different units, so a total would
                    render perfectly and mean nothing.

                    ⚠️ NO COLOUR CARRIES MEANING (`22`) — these are figures,
                    not signals, and a zero balance is a fact rather than a
                    fault. What to do about one is the form underneath.
                --}}
                <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                    {{--
                        empty-state: absent because the three cards are
                        `CreditProduct::cases()` with a balance read against
                        each — statically non-empty, so an invitation branch
                        would be dead code rather than a missing state.
                    --}}
                    @foreach ($creditCards as $card)
                        <div class="rounded-[--radius-control] border border-rule p-3">
                            <dt class="text-sm font-medium text-ink-2">{{ $card['heading'] }}</dt>
                            {{--
                                3845: NAMED BY LIFETIME, NOT BY PROVENANCE.
                                The second pool said "credit they bought" —
                                and a support grant lands in it, so a
                                goodwill credit was reported to the next
                                operator as something the customer had paid
                                for, on the screen a refund is decided from.
                            --}}
                            <dd class="mt-1 text-base text-ink">
                                {{ $card['included'] }}
                                <span class="block text-sm text-ink-2">included with their plan — resets each month</span>
                            </dd>
                            <dd class="mt-2 text-base text-ink">
                                {{ $card['bought'] }}
                                <span class="block text-sm text-ink-2">bought or added by us — does not expire</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>

                {{--
                    3926: WHAT THE SIX FIGURES ABOVE DO NOT SAY. A support
                    grant is `CreditKind::Adjust`, which lands in the top-up
                    pool, and since 3441 that pool is filtered out of the draw
                    order while the plan is inactive. So an operator could add
                    750 emails to a cancelled account, watch the card update,
                    and have added nothing anybody can send.

                    ⛔ WAITING, NEVER EXPIRED. Nothing is taken away and the
                    balance is spendable again the moment the plan is — the
                    tenant's own credit screen carries the same sentence, and
                    the two must not tell them different stories.

                    ⚠️ INFORMATION, NOT A GATE, like the trial panel below it.
                    Support must be able to act on a cancelled account (3441),
                    so nothing here refuses the grant.
                --}}
                {{--
                    ⚠️ "the moment their plan is" RATHER THAN "the moment they
                    start again" (9332). From 2026-08-25 the commonest account on
                    this branch is one whose free trial ran out and which has
                    never had a plan to start again — the date is on the plan row
                    above.
                --}}
                @unless ($account->creditIsSpendable)
                    <p class="mt-3 border-t border-rule pt-3 text-base text-ink-2">
                        Their plan is not running, so anything you add here is waiting for
                        them rather than ready to spend. Nothing expires and nothing is
                        lost — it is all there the moment their plan is running.
                    </p>
                @endunless

                {{--
                    What the no-card trial's abuse controls say about this
                    account (2066, 3117), shown to the one person in this
                    application who can actually fund a balance today (3102).

                    ⚠️ INFORMATION, NOT A GATE. Nothing below refuses the
                    grant, and the form is unchanged — a goodwill credit
                    after an outage has nothing to do with trial fraud, and
                    two of the three refusals have entirely legitimate
                    causes. The agent is told and then decides.

                    ⚠️ NO COLOUR CARRIES THE MEANING (`22`). The heading word
                    says it and the reasons are listed in full; there is no
                    red badge doing the work of a sentence, and every reason
                    is shown rather than the first, so an agent does not fix
                    one thing and get refused again for the next.
                --}}
                <div class="mt-3 border-t border-rule pt-3">
                    @if ($account->trialGrant->isEligible())
                        <p class="text-base text-ink-2">
                            Trial allowance: this account meets the automatic checks.
                        </p>
                    @else
                        <p class="text-base text-ink-2">
                            Trial allowance: the automatic checks would hold it back.
                        </p>
                        <ul class="mt-1 list-disc pl-5 text-base text-ink-2">
                            @foreach ($account->trialGrant->refusals as $refusal)
                                <li>{{ $refusal->forOperator() }}</li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                {{--
                    3426: the grant knows which product it is granting. Three
                    real options, none of them marked "recommended" — the
                    pre-selected one is what this rail has always granted, so
                    an existing habit still produces the result it always did.

                    ⚠️ `wire:model.live` BECAUSE THE HINT UNDERNEATH THE
                    AMOUNT BOX CHANGES WITH IT. That hint is the whole
                    mitigation for the AI unit (3420) — a person typing 30
                    into an unlabelled box could mean thirty cents or thirty
                    hundredths of one — so it has to move when the choice
                    does, and not on submit.

                    ⚠️ THE WITHHELD-CEILING REFUSAL LANDS ON THIS FIELD, NOT
                    ON THE AMOUNT: nobody has ruled a limit yet, and changing
                    the amount cannot help.
                --}}
                {{--
                    3842: THE CONFIRMATION REPLACES THE FORM RATHER THAN
                    SITTING UNDER IT. An amount box a person can still edit
                    while an "are you sure" quotes a figure back at them is a
                    panel describing something other than what the button
                    will do.
                --}}
                @if ($grantConfirming && $grantConfirmation !== null)
                    <div class="mt-4 rounded-[--radius-control] border border-rule p-4">
                        <p class="text-base text-ink">
                            Add {{ $grantConfirmation }} to this account?
                        </p>
                        <p class="mt-2 text-base text-ink-2">
                            You have no set limit, so nothing is checking this figure but
                            you. It costs us real money, they see it on their own account
                            straight away, and it does not expire.
                        </p>
                        <p class="mt-2 text-base text-ink-2">
                            Why: {{ $grantReason }}
                        </p>

                        <div class="mt-4 flex flex-wrap gap-3">
                            {{--
                                3929: THE SECOND CLICK. Two presses a round
                                trip apart were two independent grants — the
                                `reset()` only lands with the first response —
                                and this is the one button in the application
                                that mints credit we pay for.

                                ⚠️ `x-ui.submit` CARRIES THIS ALREADY AND DOES
                                NOT APPLY: it hardcodes `type="submit"`, and
                                neither of these is in a form. `ScreenStates`'
                                loading lint covers submits only, deliberately
                                (3006), so this is that hole filled by hand for
                                the two buttons where it costs money — in the
                                same shape the lint asks of everything else.

                                ⚠️ AND IT IS THE BROWSER'S OPINION, LIKE EVERY
                                OTHER `wire:` ATTRIBUTE. What it closes is the
                                double press; what still bounds a deliberate
                                caller is the cap, the ceiling and the
                                confirmation.
                            --}}
                            <x-ui.button
                                wire:click="grantCredits"
                                type="button"
                                wire:loading.attr="disabled"
                                wire:target="grantCredits"
                            >
                                <span wire:loading.remove wire:target="grantCredits">Yes, add {{ $grantConfirmation }}</span>
                                <span wire:loading wire:target="grantCredits">Adding…</span>
                            </x-ui.button>
                            <button
                                type="button"
                                wire:click="cancelGrantConfirm"
                                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                            >
                                Cancel
                            </button>
                        </div>
                    </div>
                @else
                    <fieldset class="mt-4">
                        <legend class="text-sm font-medium text-ink-2">What are you granting?</legend>

                        <div class="mt-2 flex flex-wrap gap-4">
                            {{--
                                3844: A PRODUCT THIS OPERATOR CANNOT GRANT SAYS
                                SO HERE, NOT AFTER THEY HAVE TYPED AN AMOUNT AND
                                WRITTEN A REASON. The same shape as the
                                impersonation rail's locked mode below, and the
                                same caveat: the mark is a browser's opinion and
                                `CreditGrants` is what refuses.

                                3844: AND DELIBERATELY NOT THE SAME SENTENCE AS
                                THAT RAIL. It read "Needs a support lead." — word
                                for word what the locked impersonation mode says
                                further down the same page, to the same role — so
                                the test written to prove this mark exists was
                                satisfied by the other one and stayed green with
                                every product marked available. Two controls that
                                are refused for two different reasons say two
                                different things anyway, which is `22`'s rule
                                before it is a testability one.
                            --}}
                            @foreach ($grantOptions as $option)
                                <label class="flex items-center gap-2 {{ $option['available'] ? '' : 'opacity-60' }}">
                                    <input
                                        type="radio"
                                        wire:model.live="grantProduct"
                                        value="{{ $option['value'] }}"
                                        @disabled(! $option['available'])
                                    />
                                    <span class="text-base text-ink">
                                        {{ $option['label'] }}
                                        @unless ($option['available'])
                                            <span class="block text-sm text-ink-2">A support lead can add this.</span>
                                        @endunless
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        @error('grantProduct')
                            <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="mt-4 flex flex-wrap items-start gap-4">
                        <label class="flex flex-col gap-1">
                            <span class="text-sm font-medium text-ink-2">Amount</span>
                            <input
                                type="number"
                                min="1"
                                wire:model="grantAmount"
                                class="min-h-11 w-32 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                            />
                            <span class="text-sm text-ink-2">
                                {{--
                                    empty-state: absent because this is the unit
                                    label for the radio above it, over the same
                                    `CreditProduct::cases()` — a hint with no
                                    chosen product is a state the radio cannot
                                    produce.
                                --}}
                                @foreach ($grantOptions as $option)
                                    @if ($option['value'] === $grantProduct){{ $option['hint'] }}@endif
                                @endforeach
                            </span>
                            @error('grantAmount')
                                <span class="text-base text-alert" role="alert">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="flex flex-1 min-w-[16rem] flex-col gap-1">
                            <span class="text-sm font-medium text-ink-2">Why (kept on the account)</span>
                            <textarea
                                wire:model="grantReason"
                                rows="2"
                                class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                placeholder="Delayed reply after a missed automation run — ticket SUP-1088."
                            ></textarea>
                            {{--
                                3932: WHAT THIS BOX ACTUALLY IS, SAID BEFORE IT
                                IS TYPED INTO. It is operator free text that
                                lands in `audit_log` — append-only, no
                                retention policy, unrepairable — and in
                                `credit_ledger.reason`. `AccountDirectory`
                                refuses to log the typed *reference* for
                                exactly this reason and says so in its own
                                docblock; the box beside it invited a sentence
                                with no guidance at all.

                                ⚠️ GUIDANCE AND NOT A FILTER. A regex over
                                prose would be a lint tuned until it caught
                                nothing (511) and would refuse legitimate
                                reasons at the moment somebody is on the phone.
                                What a person can act on is knowing where it
                                goes.
                            --}}
                            <span class="text-sm text-ink-2">
                                Kept on the account for ever and read back in audits. Say
                                what happened and the ticket number — never the customer’s
                                own details, and never anything about one of their customers.
                            </span>
                            @error('grantReason')
                                <span class="mt-1 block text-base text-alert" role="alert">{{ $message }}</span>
                            @enderror
                        </label>
                    </div>

                    <div class="mt-4">
                        {{-- 3929, and see the confirmation button above. --}}
                        <x-ui.button
                            wire:click="grantCredits"
                            type="button"
                            wire:loading.attr="disabled"
                            wire:target="grantCredits"
                        >
                            <span wire:loading.remove wire:target="grantCredits">Add credits</span>
                            <span wire:loading wire:target="grantCredits">Adding…</span>
                        </x-ui.button>
                    </div>
                @endif
            </div>
        @endif

        {{-- §9.4's two buttons, one click from Account 360 as the spec asks. --}}
        <div
            class="rounded-[--radius-panel] border border-rule bg-card p-5"
            role="group"
            aria-label="Open this account"
        >
            <h2 class="font-display text-lg font-semibold text-ink">Open their account</h2>
            <p class="mt-1 text-base text-ink-2">
                You can never change their sign-in details, accept an agreement for
                them, or record a customer’s consent from inside their account.
            </p>

            <fieldset class="mt-5">
                <legend class="text-sm font-medium text-ink-2">What do you need to do?</legend>

                <div class="mt-2 space-y-3">
                    @foreach ($modes as $option)
                        @php $locked = $option === \App\Enums\ImpersonationMode::Act && ! $mayAct; @endphp
                        <label class="flex items-start gap-2 {{ $locked ? 'opacity-60' : '' }}">
                            <input
                                type="radio"
                                wire:model.live="mode"
                                value="{{ $option->value }}"
                                @disabled($locked)
                                class="mt-1.5"
                            />
                            <span>
                                <span class="text-base text-ink">{{ $option->label() }}</span>
                                <span class="block text-sm text-ink-2">
                                    @if ($option === \App\Enums\ImpersonationMode::View)
                                        See exactly what the owner sees. Nothing can be changed,
                                        and it ends after {{ $option->ttlMinutes() }} minutes.
                                    @else
                                        Change settings for them. Everything you change is shown
                                        to the owner, and it ends after {{ $option->ttlMinutes() }} minutes.
                                        @if ($locked) Needs a support lead. @endif
                                    @endif
                                </span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <label class="mt-5 flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Reason</span>
                <textarea
                    wire:model="reason"
                    rows="2"
                    class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    placeholder="Customer says the review page is blank on their phone."
                ></textarea>
            </label>

            @if ($mode === 'act')
                <label class="mt-3 flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Ticket</span>
                    <input
                        type="text"
                        wire:model="ticketRef"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                        placeholder="SUP-1042"
                    />
                </label>
            @endif

            @error('reason')
                <p class="mt-3 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror

            <div class="mt-5 flex flex-wrap gap-3">
                <x-ui.button wire:click="start" type="button">Open account</x-ui.button>

                <button
                    type="button"
                    wire:click="cancel"
                    class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                >
                    Cancel
                </button>
            </div>
        </div>
    @endif
</div>

<div>
    <x-setup.progress :current="\App\Enums\WizardStep::Done" />

    <h1 class="font-display text-3xl font-semibold">You're all set</h1>

    <p class="mt-3 text-lg">
        Your account is running.
        @if ($ownerNotifyPermit)
            We will text you when something needs you.
        @endif
    </p>

    {{--
        10540, PHASE 5 — THIS PANEL IS WHY THE SENTENCE ABOVE CAN NOW SAY "WE
        WILL TEXT YOU" AND MEAN IT. Until the owner's ruling of 2026-08-27
        this promise had nowhere to route: `PlatformTexter` had no
        account-holder send and the schema had no column for the number. See
        `App\Services\Consent\OwnerConsentService` for the whole argument.

        UNCHECKED BY DEFAULT, WITH THE FULL DISCLOSURE (`29` §2) —
        `AutoRenewalDisclosure`'s checkout panel is the pattern this one
        follows.

        ⚠️ SKIPPABLE, AND VISIBLY SO. Leaving this blank does not block the
        wizard — `Done::mount()` completes it either way — and the same
        control is offered again on `/account/settings`, so a skip here is a
        deferral rather than `FindBusiness::skip()`'s kind of door that never
        opens again.
    --}}
    @if (! $ownerNotifyPermit)
        <div class="mt-6 rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Get a text when something needs you</h2>

            <p class="mt-2 text-base text-ink-2">
                {{ $ownerNotifyDisclosure }}
            </p>

            <form wire:submit="saveOwnerNotify" class="mt-4 space-y-3">
                <div>
                    <label for="owner-mobile" class="block text-sm font-medium text-ink">Your mobile number</label>
                    <input
                        type="tel"
                        id="owner-mobile"
                        wire:model="ownerMobile"
                        autocomplete="tel"
                        placeholder="(555) 123-4567"
                        class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                    @error('ownerMobile')
                        <p class="mt-1 text-sm text-ink">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-start gap-3">
                    <input
                        type="checkbox"
                        wire:model="ownerConsent"
                        class="mt-1 size-5 rounded border-rule-strong text-ink"
                    >
                    <span class="text-base text-ink">
                        Text me at this number about my own account.
                    </span>
                </label>
                @error('ownerConsent')
                    <p class="text-sm text-ink">{{ $message }}</p>
                @enderror

                <div>
                    <x-ui.submit size="default" target="saveOwnerNotify" busy="Saving…">Text me</x-ui.submit>
                </div>
            </form>
        </div>
    @endif

    @if ($mayBuyAPlan)
        {{--
            ⛔ THE CARD, ASKED FOR HERE AND NOT AT THE DOOR (9201, 9229).
            Registration opened on a hosted Checkout until 2026-08-24 — on the
            *secondary* gateway, which cannot sell the annual instalment plan at
            all — while the marketing home promised a trial with no card. This is
            "the END of onboarding" in 9201's own words, and the account plan
            page is the other half.

            ⛔ BOTH OF THE PARAGRAPHS THAT USED TO STAND HERE WERE TRUE WHEN
            THEY WERE WRITTEN AND ARE FALSE NOW — KEPT AND DATED, 2026-08-25
            (9328, 9332, 4368's rule). They read:

              "⛔ AND THE TRIAL CLOCK STARTS AT THE CARD, NOT AT REGISTRATION —
               WHICH IS WHY THIS SENTENCE CANNOT SAY 'your trial is running'.
               `Subscriptions::openPendingSignup()` writes no trial date and its
               docblock says why … a panel here reading 'your free trial has
               started' would describe a clock nothing in this application runs.
               The figure is deliberately absent as well (512).

               ⚠️ NOT A GATE, AND THE WORDS SAY SO. Nothing stops when the trial
               ends today: `Subscriptions::isEntitled()` still answers true for a
               tenant who never bought (588, 685), and whether that stays true is
               the owner's open question (9203). So this invites and never warns
               — a sentence about losing access would be a threat this
               application does not carry out."

            ⛔ THE OWNER ANSWERED THAT OPEN QUESTION ON 2026-08-25: a
            `pending_checkout` trial is bounded at `billing.trial_days` from
            registration. **A clock does run**, `isEntitled()` refuses when it
            passes, and the threat this application would not carry out is one it
            now carries out. So the reason for withholding the date has been
            reversed into a reason to state it: **this is the last screen of
            onboarding, and it would otherwise be the last place that knows when
            the product stops and does not say.**

            ⚠️ IT STILL DOES NOT THREATEN, WHICH IS THE HALF THAT SURVIVES. The
            sentence names the date, says nothing is being charged, and says what
            happens if they do nothing — that sending and publishing pause and the
            account stays as it is, which is exactly what happens. `22`'s outcome
            rule: name what the person controls.

            ⚠️ THE FIGURE IS STILL NOT WRITTEN INTO THIS TEMPLATE (512). What is
            rendered is a **date derived from the registry value**, computed in
            `Done::render()`, so moving `billing.trial_days` moves this sentence.

            ⛔ AND `trialEndsOn` IS NULL FOR ANYBODY NOT ON THE NO-CARD TRIAL, so
            a returning owner who has already bought reads no date here — the same
            population `$mayBuyAPlan` already withholds the button from, answered
            by a different service, which is why both conditions are asked.

            ⚠️ IT POINTS AT `billing.index` RATHER THAN AT `billing.card`, WHICH
            IS THE FORM. That screen reads the subscription row and renders the
            right thing for every state, including the two this button cannot
            know about — a plan bought in another tab, and a plan that has ended.
            Pointing straight at the form would be a card field drawn from a
            template that had decided, several seconds earlier, that there was no
            plan.

            OUTCOME LANGUAGE (`22`): the control names what the person gets, and
            the sentence under it names what it costs them today, which is
            nothing. Colour is not the signal — this is the same ink button every
            other step uses. Works at 320px; nothing here is below 16px.
        --}}
        <div class="mt-8 rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">
                @if ($trialEndsOn)
                    Your free trial runs until {{ $trialEndsOn }}
                @else
                    Keep it running after the trial
                @endif
            </h2>

            <p class="mt-2 text-base text-ink-2">
                Nothing is being charged and you do not need a card today.
                @if ($trialEndsOn)
                    Adding one keeps everything running past that date. If you do nothing,
                    sending and publishing pause and your account stays exactly as it is.
                @else
                    Adding one keeps everything running when the free trial ends.
                @endif
            </p>

            <x-ui.button :href="route('billing.index')" size="default" class="mt-4">
                Add a card
            </x-ui.button>
        </div>
    @endif

    {{--
        ⚠️ THE ONLY THING LINKING TO `/account`, AND THAT IS WHY IT IS HERE.
        `SetupController` sends every finished owner to this step — its docblock
        says so, for want of a dashboard to send them to — so this page is the
        post-onboarding home whether or not it was designed as one. A pause
        control nobody can reach would be decision 272's shape inside the slice
        built to fix decision 272's shape, and decision 570 is the same lesson
        at the scale of a screen: a door with no handle.

        ⚠️ AND IT IS RENDERED FOR EVERYBODY, INCLUDING SOMEBODY WHO HAS ALREADY
        BOUGHT (9229). The panel above is withheld from them, so this is the only
        control they get — and it is the way to `Your plan`, which is where the
        card lives for an account that has one.
    --}}
    <div class="mt-8">
        <a
            href="{{ route('account.settings') }}"
            class="inline-flex min-h-11 items-center text-base text-ink-2 underline hover:text-ink"
        >
            Your account
        </a>
    </div>
</div>

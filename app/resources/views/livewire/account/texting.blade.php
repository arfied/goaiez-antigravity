{{--
    Texting from your own number — where the registration has got to (5329,
    built at 5420–5439).

    ⛔ THE HONEST SENTENCE IS NEVER TYPED HERE. `$sentence`, `$label` and
    `$signal` all come from `App\Enums\BrandRegistrationStatus`, which is the
    one place the words naming the ordinary wait live — `BotController`'s
    shape, rendering `RobotsPolicy::userAgentToken()` rather than typing the
    token. `BrandRegistrationScreenTest` fails the build if that phrase is ever
    typed into a template, so a copy here cannot quietly drift from the enum.

    ⛔ NO DATE, NO COUNTDOWN, NO ESTIMATE. Nobody has given us one, and 5329's
    subject is honest waiting: withholding beats inventing, the same rule the
    Limited tier price follows. What this page renders is the range the vendor's
    own document gives, the date the filing went in, and — once the ordinary
    wait has passed — the fact that it has.

    ⛔ BEING REGISTERED IS NOT PERMISSION TO TEXT ANYBODY (T137 R6, decision
    2100). The panel saying so renders in every state, not only once the
    registration is accepted, because the reading it prevents is at its most
    tempting to somebody who has just been told they are ready.

    COLOUR IS NEVER THE SIGNAL (`22`, `29` §5.5). The pill carries an icon and a
    word from `SignalState`; every panel is a bordered card with a heading and a
    sentence, and the page reads identically in monochrome and to a screen
    reader.

    OUTCOME LANGUAGE (`22`, `29` §2 rule 47). No "10DLC", no "TCR", no "brand",
    no "campaign registration", no "carrier", no "lane" and no "broadcast" — a
    person is waiting for the phone networks to accept their business, and that
    is what the page says.

    NO LINK TO ANOTHER OWNER SCREEN. `Architecture/OwnerNavTest` refuses one, and
    its argument is that movement between owner screens comes from `OwnerNav`.
    The other two things a campaign needs are named in words; the shell is what
    carries somebody to them.

    WORKS AT 320px — one stacked column, nothing below 16px (`text-base`) except
    the supporting line, `text-sm` like every other account screen. Nothing here
    moves, so there is no motion for `prefers-reduced-motion` to reduce.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Texting from your own number</h1>
        <p class="mt-1 text-base text-ink-2">
            Before you can text your own customer list from a number in your own name,
            the phone networks have to accept your business. This page is where that has
            got to.
        </p>
    </div>

    <div class="rounded-[--radius-panel] border border-rule-strong bg-card p-5">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display text-lg font-semibold text-ink">Where you are</h2>
            <x-ui.status-pill :state="$signal" :label="$label" />
        </div>

        @if ($status === null)
            {{--
                ⚠️ "WE HAVE NOT FILED ANYTHING", NOT "YOU HAVE NOT FILED
                ANYTHING". There is no self-serve path and there is not meant to
                be: the registration is recorded by us, `26`'s automated filing
                does not exist, and inviting somebody to start something they
                cannot start would be worse than saying nothing.
            --}}
            <p class="mt-3 text-base text-ink-2">
                Nothing has been registered for you yet, so there is nothing to report
                here. There is nothing for you to fill in — ask us if you want to start,
                and this page is where you will watch it move.
            </p>
        @else
            <p class="mt-3 text-base text-ink-2">{{ $sentence }}</p>

            <p class="mt-3 text-sm text-ink-2">
                Sent to the phone networks on {{ $filing->submitted_at->format('j F Y') }}.
            </p>

            @if ($waitingLongerThanUsual)
                {{--
                    ⛔ THE OVERSTATEMENT GUARD. Printing the ordinary wait
                    unqualified to somebody in their sixth week is no longer
                    honest, and this is the sentence that keeps it so.
                    It says the thing that is true and offers the only action
                    that exists — asking us — rather than inventing a date or
                    claiming somebody is chasing it.
                --}}
                <p class="mt-3 text-base text-ink-2">
                    Yours has been waiting longer than that. It happens, and on its own it
                    does not mean anything has gone wrong — ask us if you would like us to
                    find out where it has got to.
                </p>
            @endif

            @if ($filing->rejection_reason !== null)
                {{--
                    ⚠️ THE REASON IS SHOWN, AND THAT IS THE POINT OF RECORDING
                    IT. `BrandRegistrations::reject()` refuses a refusal with no
                    reason because *"the tenant will ask what to fix, and a
                    status with no sentence beside it is a support ticket with no
                    answer"* — this is where that sentence was always going.
                --}}
                <p class="mt-3 text-base text-ink-2">
                    What they said: {{ $filing->rejection_reason }}
                </p>
            @endif
        @endif
    </div>

    {{--
        ⛔ T137 R6 — REGISTRATION IS NOT CONSENT (decision 2100), AND THIS PANEL
        RENDERS IN EVERY STATE. Someone who has just been told the phone networks
        accepted them is exactly the person about to read it as permission to
        text anybody, so the sentence sits on the page rather than being kept for
        the state where it is most needed.
    --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What this does not change</h2>

        {{--
            ⚠️ DELIBERATELY SILENT ON *WHAT* MAKES A PERSON CONTACTABLE. An
            earlier draft of this paragraph explained the two recorded bases —
            consent captured here, and a tenant's attestation at import (2098) —
            and both halves invite a misreading this page must not carry: 2100 is
            explicit that an attestation *"records who carries the basis, it does
            not create one"*, and a sentence describing it on a page about being
            accepted reads as a self-certification route. What is true, safe and
            short is that being accepted widens nothing.
        --}}
        <p class="mt-2 text-base text-ink-2">
            Being accepted is not permission to text somebody. All the phone networks
            decide is that messages leaving your number really are yours. Who you may
            text has not changed at all — every message is still checked against what we
            have on record for that person, exactly as it was before, and being accepted
            never widens it.
        </p>

        <p class="mt-3 text-base text-ink-2">
            Stopping is always immediate too: anybody who replies STOP is taken off your
            list straight away, whichever number the message left on.
        </p>
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What else a campaign needs</h2>

        {{--
            ⛔ NAMED IN WORDS AND NOT PRE-FLIGHTED. `BroadcastPreconditions` is
            explicit that its three facts *"are not fields on a form that a
            screen can pre-flight; they are properties of the message about to be
            sent"* — a registration can be refused at 3am while a campaign that
            started at midnight is still running, a number can be taken back
            mid-run, and a balance runs out on the four-hundredth message. So
            this page never renders a green light, and it says why in the last
            sentence rather than leaving the reader to infer it.
        --}}
        <p class="mt-2 text-base text-ink-2">
            This is one of three things. A campaign to your own list also needs a number
            of your own to send it from, and credit you have bought — the credit your
            plan includes pays for everything else we send and never for this. Credit is
            under Your credit; a number in your own name is something we set up with you,
            so ask us for that one.
        </p>

        <p class="mt-3 text-base text-ink-2">
            All three are checked again for every message as a campaign goes out, so
            nothing on this page is a promise that one will start.
        </p>
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What is not waiting on this</h2>

        {{--
            ⚠️ DECISION 3311 — reactivation and missed-call replies ride the GO
            AI EZ pool *"until clients 10 dlc is ready"*, and review invitations
            always have. A page about a wait that did not say what is NOT waiting
            would leave a tenant believing the whole product is stopped.
        --}}
        <p class="mt-2 text-base text-ink-2">
            Review invitations, replies to people who text you, and the messages we send
            when a call is missed all go out on our numbers and are not waiting on any of
            this. They are running now.
        </p>
    </div>
</div>

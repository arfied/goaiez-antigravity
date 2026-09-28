{{--
    The kill switches — T137 `SL-8`, and the surface decision 2478 recorded as
    owed.

    THREE STOP-STATES SIT ON THIS PAGE AND ONLY TWO ARE THROWN FROM IT. The
    third — the account-wide Pause Everything, which is the owner's — is
    rendered read-only, because an operator who releases a sending pause while
    that one is set sees nothing sent afterwards and has every reason to
    conclude the release failed. Decision 2450 records what confusing two of
    these cost the campaign runner.

    COLOUR IS NOT THE SIGNAL (`22`, `29` §5.5). Every state here is a word and a
    sentence; the pill carries the icon. Nothing on this page is legible by hue
    alone.

    A RATE THAT CANNOT BE MEASURED IS A DASH, NEVER "0.0%". `RateReading`
    carries the denominator beside the rate, so a tenant nothing has been
    delivered for reads as absent rather than as a confident zero. That
    distinction outlives the reason it was built: it was written while the
    counters had no writer at all (2496–2499), and it still matters now they do,
    because a tenant who has simply not been sent to this window is not a tenant
    with a 0% complaint rate.

    ⛔ AND THE DELIVERY CARD HAS A FIFTH STATE THAT IS NOT A THRESHOLD
    COMPARISON — 7562. "Nothing is being reported back" means messages went out
    in numbers and not one outcome came back about any of them, which is the
    receipt blind spot 7480/7543 found: the complaint trip and the platform halt
    both silently disarmed, on a live campaign, with everything else green. It
    is RED where "Not measured yet" is grey, and the two look alike and mean
    opposite things — the pill's words are what tell them apart, never the hue.
    ⚠️ THAT CARD'S RATE IS OVER THE MESSAGES A CARRIER HAS ACTUALLY ADJUDICATED
    AND NOT OVER EVERYTHING SENT (7560), so it does not fall as a campaign goes
    out. What is still outstanding is printed in the sentence beside it.

    ⚠️ THE "NOTHING IS COUNTED YET" PANEL THAT SAT BELOW WAS DELETED AT THE
    MERGE OF `l2-containment-writers`, WHICH IS WHAT IT WAS FOR. The lint in
    `Architecture/MessagingTest` fails the build both ways — no writer without
    the notice, a writer with it — and it fired on the first run after those
    writers landed, naming this file and saying what to delete. A screen that
    went on telling operators nothing was counted, the day something was, is
    2505's shape; the lint is why it lasted minutes instead of months.

    NO PERSONAL DATA. Business names and actor labels appear inside the
    platform-staff gate; nothing here reaches a toast, a URL or a log.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Stop and start sending</h1>
        <p class="mt-1 text-base text-ink-2">
            The two switches that stop outbound messages — everyone at once,
            or one business — and the record of every time either was thrown.
        </p>
    </div>

    {{--
        ⛔ THE FOURTH STOP-STATE, AND THE ONLY ONE NOBODY THREW — 9449(c), 9641.

        `ConsentService::decide()` refuses EVERY send, on every channel, for
        every business, whenever this install cannot read the suppression hashes
        it has stored — and neither switch below is set while it does. This page
        answered "Running for everyone" in that state until today, which is the
        both-halves-right-on-their-own-screen failure decision 3418 records, on
        the one pill 3980–3983 already had to correct once for the same reason.

        IT SITS ABOVE THE SWITCHES AND NOT BELOW THEM. Decision 9448 found this
        exact panel written four paragraphs under the thing it explained, and
        recorded the rule: a person mid-incident does not scroll before
        concluding. Read in order, an operator meets "nothing is going out" and
        then meets the switch that is not what is stopping it.

        AND THE "Everyone" PILL BELOW IS DELIBERATELY UNTOUCHED. It is the state
        of the switch the button beside it operates. A pill reading "stopped"
        for a cause that button cannot clear is decision 2450's confusion one
        level up — the operator throws the release, nothing changes, and they
        report the containment as broken. The account-pause panel further down
        solves the identical problem for one tenant in exactly this shape: its
        own pill, its own words, and a sentence that pre-empts the wrong
        conclusion the neighbouring control invites.
    --}}
    <x-admin.suppression-readability :registers="$registers">
        @unless ($registers->isReadable())
            {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
            <span>Neither switch on this page is set, and neither one starts sending again while this is true. This is a fault rather than a decision somebody made, and what clears it is in the paragraph under this one.</span>
        @endunless
    </x-admin.suppression-readability>

    {{-- ────────────────────────  Everyone  ──────────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-ink">Everyone</h2>

            {{--
                ⛔ THREE ANSWERS, NOT TWO — 9644. This pill said "Running for
                everyone" in a state where nothing was going out at all, three
                lines under a panel saying so, which is decision 3418's
                both-halves-right shape inside one page rather than across two.

                AND THE THIRD LABEL NAMES THE SWITCH RATHER THAN THE WORLD.
                "Stopped for everyone" would be true of the world and would
                read as this switch having been thrown — decision 2450's
                confusion one level up, next to a button offering to throw it.
                "This switch is off" is exactly true, claims nothing this
                section cannot answer, and the alert state and icon are what
                stop it being skimmed as reassurance. The sentence below says
                what is actually stopping sending.
            --}}
            <x-ui.status-pill
                :state="$halted || ! $registers->isReadable() ? \App\Enums\SignalState::Alert : \App\Enums\SignalState::Ok"
                :label="$halted ? 'Stopped for everyone' : ($registers->isReadable() ? 'Running for everyone' : 'This switch is off')"
            />
        </div>

        <p class="mt-3 text-base text-ink-2">
            @if ($halted)
                No message goes out for any business on any channel. This
                outranks every other switch on this page, so a business whose
                own sending is running is still not sending.

                {{--
                    WHICH OF THE TWO SWITCHES IS SET, IN WORDS (3980–3983).
                    The pill above is one answer for both, because "is
                    sending stopped" has one answer. But an operator
                    starting an incident needs to know whether a person did
                    this or whether the platform stopped itself, and the
                    incident table further down only says the second
                    happened at some point — never whether it is what is
                    stopping sending now.
                --}}
                @if ($haltedByMachine)
                    {{-- ⚠️ THE PHRASE STAYS ON ONE SOURCE LINE, because
                        Blade keeps the newlines and a sentence wrapped
                        mid-phrase is one `assertSee()` cannot find. --}}
                    <span class="font-semibold text-ink">The platform stopped itself</span>
                    — the complaint rate across all businesses crossed the
                    line. The two carrier-required replies, the answer to
                    HELP and the confirmation of a STOP, still go out: a
                    machine may stop your messages and may not stop those.
                @else
                    Somebody stopped this deliberately. While it is set, the
                    answer to HELP and the confirmation of a STOP do not go
                    out either.
                @endif
            @elseif ($registers->isReadable())
                Messages go out normally. Stopping here takes effect on the
                very next message, without a deploy.
            @else
                {{--
                    ⛔ "Messages go out normally." WAS FALSE IN THIS STATE AND
                    RENDERED ANYWAY UNTIL 2026-08-25 (9644). It is a claim about
                    the world rather than about the switch, and the world was
                    refusing every send. A page carrying a true panel and a
                    false sentence is not better than one carrying neither.
                --}}
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span>Nobody has stopped sending here, and nothing is going out regardless — the top of this page says what is refusing it.</span>
                Stopping here would still take effect on the very next message,
                and starting again would change nothing while that is true.
            @endif
        </p>

        {{--
            WHETHER THE AUTOMATIC HALT IS ARMED IS READ, NEVER ASSUMED
            (2409, 2410, armed at 2684). Decision 2113 makes the automatic
            trip a precondition of sending at all. It shipped with both
            figures unset and this panel existed to say so out loud; the
            owner set them on 2026-08-12, so the armed branch is what an
            operator sees today. ⚠️ Both branches stay, and neither is
            dead code: either key edited back to zero disarms the trip
            again, and the panel is the only place an operator would find
            that out. `WatchPlatformComplaintRate` warns on every run for
            the same reason — a switched-off safety control must not stay
            switched off for a year, and this page is exactly where
            somebody would otherwise conclude the platform is protected.
        --}}
        <div class="mt-4 rounded-[--radius-control] border border-rule bg-paper p-4">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.status-pill
                    :state="$automaticHalt['armed'] ? \App\Enums\SignalState::Ok : \App\Enums\SignalState::Alert"
                    :label="$automaticHalt['armed'] ? 'Stops itself' : 'Will not stop itself'"
                />
                <span class="text-sm font-semibold text-ink">The automatic stop</span>
            </div>

            @if ($automaticHalt['armed'])
                <p class="mt-2 text-base text-ink-2">
                    Sending stops for everyone by itself once the complaint
                    rate across all businesses reaches
                    {{ number_format($automaticHalt['thresholdBp'] / 100, 1) }}%,
                    measured over at least
                    {{ number_format($automaticHalt['floor']) }} delivered messages.
                    It never starts itself again — that is always somebody's decision.
                </p>
            @else
                <p class="mt-2 text-base text-ink-2">
                    Sending will <span class="font-semibold text-ink">not</span>
                    stop by itself, whatever the complaint rate reaches. Two
                    figures are needed and are not set: the rate at which the
                    platform stops, and how many delivered messages it takes
                    before that rate counts. Both are the owner's to choose
                    and neither may be guessed. Until they are set, only a
                    person can stop sending here.
                </p>
            @endif
        </div>

        {{--
            HOW A STOP IS WORKED OUT, FOR EVERYONE — T176 P9 (4369–4378).

            THE TWO FIGURES ABOVE WERE ALREADY ON THIS PAGE AND WHAT THEY DO
            TOGETHER WAS NOT. An operator could read "1.0% over 50 delivered"
            and had no way to see that one complaint in those 50 is 2.0% and
            already stops every message on the platform. This panel is that
            step, and it is where a pair of figures is argued with rather
            than admired.

            THE STANDING COMES FROM THE SCHEDULED SWEEP AND NOT FROM THIS
            REQUEST. Summing every tenant's counters from a web request
            returns zero rather than failing — the tables are row-level
            secured — so a "0.00%" here would be a healthy-looking platform
            that was simply unreadable. When the sweep has not reported, this
            says so instead.
        --}}
        <div class="mt-4 rounded-[--radius-control] border border-rule bg-paper p-4">
            <div class="flex flex-wrap items-center gap-3">
                <x-ui.status-pill :state="$platformTripMath->state()" :label="$platformTripMath->stateLabel()" />
                {{--
                    A HEADING, AND WITH TEXT THAT IS NOT THE OTHER PANEL'S
                    (4499). Both panels carried the identical string inside a
                    <span>, so a screen-reader user landed on two unheaded
                    regions with the same name on one page and had no way to
                    tell the platform's arithmetic from a tenant's — WCAG
                    2.4.6. The sibling panels on this screen all use
                    `h3.font-display`; these two were the exception.
                --}}
                <h3 class="font-display text-sm font-semibold text-ink">How a stop is worked out for everyone</h3>
            </div>

            <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-ink-2">Stops at</dt>
                    <dd class="font-mono text-lg tabular-nums text-ink">{{ $platformTripMath->thresholdFigure() }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-2">Smallest sample</dt>
                    <dd class="font-mono text-lg tabular-nums text-ink">
                        {{ number_format($platformTripMath->floorDelivered) }} delivered
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-ink-2">Right now</dt>
                    {{-- A dash, never "0.00%" — an unread counter is not a
                         clean one. --}}
                    <dd class="font-mono text-lg tabular-nums text-ink">{{ $platformTripMath->standingFigure() }}</dd>
                </div>
            </dl>

            <p class="mt-3 text-base text-ink-2">{{ $platformTripMath->arithmetic() }}</p>

            @if ($platformTripMath->isMeasured())
                <p class="mt-2 text-sm text-ink-3">
                    Measured {{ $platformTripMath->measuredAt?->diffForHumans() }}@if ($platformTenantCount !== null) across {{ number_format($platformTenantCount).' '.($platformTenantCount === 1 ? 'business' : 'businesses') }}@endif
                    by the check that runs every fifteen minutes. Everything sent since then
                    went out against this reading.
                </p>
            @elseif ($platformTripMath->isArmed())
                <p class="mt-2 text-sm text-ink-3">
                    Nothing has been measured across all businesses yet. The check that works
                    this out runs every fifteen minutes and leaves its reading here; this page
                    cannot add the businesses up by itself.
                </p>
            @endif

            @if ($platformTripMath->noiseWarning() !== null)
                <x-ui.attention-card class="mt-3" state="attention" heading="These two figures fight each other">
                    {{ $platformTripMath->noiseWarning() }}
                </x-ui.attention-card>
            @endif
        </div>

        @if ($halted)
            <div class="mt-5 border-t border-rule pt-5">
                <h3 class="font-display text-base font-semibold text-ink">Start sending again</h3>

                {{--
                    THE CONSEQUENCE SITS ABOVE THE BUTTON, PhiTenants' rule:
                    a consequence explained underneath the control that
                    causes it is read after the decision, if at all. This is
                    also the confirmation step this control has instead of a
                    dialog — decision 826 leaves the *stop* unconfirmed
                    because a confirm is in the way when somebody needs it
                    most, and 1228 gives the rule both screens share:
                    confirm the direction that is hard to notice you took.
                --}}
                <p class="mt-1 text-base text-ink-2">
                    Every business starts sending again immediately, except
                    those stopped individually below. If the complaint rate
                    that caused this has not actually been dealt with, the
                    automatic stop will fire again — and if it is not armed,
                    it will not. Your name is recorded against this.
                </p>

                <x-ui.button size="default" class="mt-3" wire:click="releasePlatform" type="button">
                    Start sending for everyone
                </x-ui.button>
            </div>
        @else
            <div class="mt-5 border-t border-rule pt-5">
                <h3 class="font-display text-base font-semibold text-ink">Stop sending for everyone</h3>

                <p class="mt-1 text-base text-ink-2">
                    Takes effect on the next message. Nothing starts again by
                    itself; somebody has to decide it is safe.
                </p>

                <x-ui.button size="default" variant="secondary" class="mt-3" wire:click="haltPlatform" type="button">
                    Stop sending for everyone
                </x-ui.button>
            </div>
        @endif

        {{-- 2402's evidence table, read here and never written here. --}}
        @if ($haltIncidents !== [])
            <div class="mt-6 border-t border-rule pt-5">
                <h3 class="font-display text-base font-semibold text-ink">
                    When sending stopped by itself
                </h3>

                <ul class="mt-2 space-y-2 text-base text-ink-2">
                    {{--
                        empty-state: absent because the whole block is
                        behind `$haltIncidents !== []` and a platform that
                        has never stopped itself should show an operator
                        nothing at all — a card reading "no automatic stops"
                        on a screen about stopping sending is the kind of
                        reassurance that gets read as a status light.
                    --}}
                    @foreach ($haltIncidents as $incident)
                        <li wire:key="incident-{{ $incident->id }}">
                            {{ $incident->tripped_at?->format('j M Y, H:i') }} —
                            {{ number_format($incident->rate_basis_points / 100, 1) }}%
                            over {{ number_format($incident->delivered_in_window) }} delivered
                            across {{ number_format($incident->tenant_count) }}
                            {{ $incident->tenant_count === 1 ? 'business' : 'businesses' }},
                            against a
                            {{ number_format($incident->threshold_basis_points / 100, 1) }}% threshold.
                        </li>
                    @endforeach
                </ul>

                {{--
                    ⛔ THIS READ "that is the switch above" UNTIL 2026-08-25 AND
                    IT NAMED THE WRONG THING (9643). The switch is one of four
                    answers to "is sending stopped now", and the one an operator
                    is least likely to have thrown is the panel at the top of
                    this page. A pointer that is exactly true of three cases and
                    silent about the fourth is worse here than no pointer, since
                    the fourth is the one nobody caused.
                --}}
                <p class="mt-2 text-sm text-ink-3">
                    These are the measurements behind an automatic stop. They
                    are a record of why, never the answer to whether sending
                    is stopped now — that is the top of this page.
                </p>
            </div>
        @endif

        @if ($haltHistory !== [])
            <div class="mt-6 border-t border-rule pt-5">
                <h3 class="font-display text-base font-semibold text-ink">Who threw this switch</h3>

                <ul class="mt-2 space-y-1 text-base text-ink-2">
                    {{--
                        empty-state: absent because the block above gives the
                        reason and this one shares it — "who threw this
                        switch" with nobody in it is
                        a heading over an answer nobody asked for, and the
                        switch's own state is three centimetres up the page.
                    --}}
                    @foreach ($haltHistory as $change)
                        <li wire:key="halt-change-{{ $change->id }}">
                            {{ $change->created_at?->format('j M Y, H:i') }} —
                            {{ $change->value_after === true ? 'stopped' : 'started again' }}
                            by {{ $change->actor }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- ────────────────────────  One business  ──────────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">One business</h2>

        <p class="mt-1 text-base text-ink-2">
            By its number. There is no list — see the note at the foot of this page.
        </p>

        <form wire:submit="lookUp" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block" for="business-lookup">
                <span class="text-sm text-ink-2">Business number</span>
                <input
                    wire:model="lookup"
                    id="business-lookup"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 w-40 rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
            </label>

            <x-ui.submit size="default" target="lookUp" busy="Looking…">Show this business</x-ui.submit>
        </form>
    </section>

    @if ($business)
        <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-semibold text-ink">{{ $business->name }}</h2>
                    <p class="mt-1 text-sm text-ink-3">Business {{ $business->id }}</p>
                </div>

                <x-ui.status-pill
                    :state="$livePause ? \App\Enums\SignalState::Alert : \App\Enums\SignalState::Ok"
                    :label="$livePause ? 'Sending stopped' : 'Sending running'"
                />
            </div>

            {{--
                THE THIRD SWITCH, READ-ONLY, AND THE MOST IMPORTANT PANEL ON
                THE PAGE FOR ANYBODY DEBUGGING "WHY IS NOTHING SENDING".
                Pause Everything is the owner's own control on a different
                table with a different reader and no automatic writer;
                decision 2450 records a docblock confusing it with this
                screen's switch, and a green suite over a campaign that
                would have run to the end of its list.
            --}}
            @if ($accountPaused)
                <div class="mt-4 rounded-[--radius-control] border border-rule bg-attention-bg p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.status-pill
                            :state="\App\Enums\SignalState::Attention"
                            label="Whole account paused"
                        />
                        <span class="text-sm font-semibold text-ink">A different switch, and not ours</span>
                    </div>

                    <p class="mt-2 text-base text-ink-2">
                        This business has Pause Everything on, set by
                        {{ $accountPausedBy }}. That stops every automation we
                        run for them, not messaging alone — so starting their
                        sending again below will still send nothing until
                        this is lifted, and it is lifted by them or by
                        support, never from this page.
                    </p>
                </div>
            @endif

            {{-- The live incident, and what it was. --}}
            @if ($livePause)
                <div class="mt-5 border-t border-rule pt-5">
                    <h3 class="font-display text-base font-semibold text-ink">Why sending is stopped</h3>

                    <ul class="mt-2 space-y-1 text-base text-ink-2">
                        <li>Reason: <span class="font-medium text-ink">{{ $livePause->reason->label() }}</span></li>
                        <li>
                            Stopped by
                            <span class="font-medium text-ink">{{ $livePause->tripped_by }}</span>
                            @if ($livePause->tripped_by === \App\Services\Messaging\SendingGuard::SYSTEM_ACTOR)
                                — the platform stopped this business by itself, with nobody in the loop.
                            @endif
                        </li>
                        @if ($livePause->observed_rate_bp !== null)
                            <li>
                                Complaint rate when it stopped:
                                <span class="font-medium text-ink">{{ number_format($livePause->observed_rate_bp / 100, 1) }}%</span>
                                — a snapshot taken at the time, which cannot be worked out again afterwards.
                            </li>
                        @endif
                        {{--
                            ⛔ **THE QUESTION AN OPERATOR ASKS SECOND, AND
                            THE ROW CANNOT ANSWER IT** (6664, 6668). The
                            trip is evaluated per channel and the pause is
                            per business — `sending_pauses.business_id` is
                            unique and there is no channel column — so the
                            snapshot above is a rate with no channel beside
                            it. Saying so is better than letting a reader
                            assume it was texts, which is what everything
                            else on this screen was about until today. The
                            honest answer is the two standings further down,
                            and adding a channel column is a schema change
                            this slice did not make.
                        --}}
                        @if ($livePause->reason === \App\Enums\SendingPauseReason::ComplaintRate)
                            <li>
                                Which channel crossed the line:
                                <span class="font-medium text-ink">not recorded</span>
                                — one stop covers texts and email together and the record has no
                                room for the channel. The two standings further down are how you
                                tell; the one over its line is the one to act on.
                            </li>
                        @endif
                        @if ($livePause->note)
                            <li>Note: {{ $livePause->note }}</li>
                        @endif
                        <li>Since {{ $livePause->created_at?->format('j M Y, H:i') }}</li>
                    </ul>

                    <div class="mt-5 border-t border-rule pt-5">
                        <h3 class="font-display text-base font-semibold text-ink">Start sending again</h3>

                        {{--
                            THE CONSEQUENCE BEFORE THE CONTROL. Nothing
                            self-clears (SendingPauseReason::isSelfClearing()
                            is false for every case) and the window has not
                            rolled just because somebody clicked — so a
                            business released into the same complaint rate
                            trips again on the very next message, as a SECOND
                            incident. Saying so here is what stops an
                            operator reading that as the release having
                            failed and clicking again.
                        --}}
                        <p class="mt-1 text-base text-ink-2">
                            If the complaint rate that stopped them has not
                            actually come down, the very next message stops
                            them again — as a new incident, not this one
                            reopening. Your name and this note are kept on
                            the record permanently.
                        </p>

                        <form wire:submit="releaseTenant" class="mt-3 flex flex-wrap items-end gap-3">
                            <label class="block grow" for="release-note">
                                <span class="text-sm text-ink-2">Why it is safe to start again</span>
                                <input
                                    wire:model="releaseNote"
                                    id="release-note"
                                    type="text"
                                    @error('releaseNote') aria-describedby="release-note-error" @enderror
                                    class="mt-1 w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-base text-ink"
                                >
                                @error('releaseNote')
                                    {{-- Text, not colour alone (`29` §2 rule 46). --}}
                                    <span id="release-note-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                                @enderror
                            </label>

                            <x-ui.submit size="default" target="releaseTenant" busy="Starting…">Start sending again</x-ui.submit>
                        </form>
                    </div>
                </div>
            @else
                <div class="mt-5 border-t border-rule pt-5">
                    <h3 class="font-display text-base font-semibold text-ink">Stop this business sending</h3>

                    <p class="mt-1 text-base text-ink-2">
                        Takes effect on their next message, and stops a
                        campaign part-way through rather than waiting for it
                        to finish. Nothing starts again by itself.
                    </p>

                    {{--
                        THE AUTOMATIC REASON IS NOT OFFERED. "Complaint rate"
                        is the one case that happens without a human, and an
                        operator choosing it would forge a machine
                        measurement — the row would claim a threshold was
                        crossed with no rate recorded, and the incident
                        history would stop telling the trip apart from a
                        person. The action refuses it too, because a Livewire
                        action is callable whatever rendered it (391).
                    --}}
                    <form wire:submit="pauseTenant" class="mt-4 space-y-4">
                        <label class="block" for="pause-reason">
                            <span class="text-sm text-ink-2">Reason</span>
                            <select
                                wire:model="pauseReason"
                                id="pause-reason"
                                @error('pauseReason') aria-describedby="pause-reason-error" @enderror
                                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-base text-ink"
                            >
                                @foreach ($reasons as $reason)
                                    <option value="{{ $reason->value }}">{{ $reason->label() }}</option>
                                @endforeach
                            </select>
                            @error('pauseReason')
                                <span id="pause-reason-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                            @enderror
                        </label>

                        <label class="block" for="pause-note">
                            <span class="text-sm text-ink-2">Why</span>
                            <input
                                wire:model="pauseNote"
                                id="pause-note"
                                type="text"
                                @error('pauseNote') aria-describedby="pause-note-error" @enderror
                                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-paper px-3 py-2 text-base text-ink"
                            >
                            @error('pauseNote')
                                <span id="pause-note-error" class="mt-1 block text-sm text-alert">{{ $message }}</span>
                            @enderror
                        </label>

                        <x-ui.submit size="default" variant="secondary" target="pauseTenant" busy="Stopping…">
                            Stop this business sending
                        </x-ui.submit>
                    </form>
                </div>
            @endif
        </section>

        {{-- ────────────────  How their text messages are doing  ──────────────── --}}
        <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
            <h2 class="font-display text-lg font-semibold text-ink">How their text messages are doing</h2>

            <p class="mt-1 text-base text-ink-2">
                Text messages only, over the last
                {{ $this->windowHours() }} hours.
                Email is counted separately and is in the next panel down.
            </p>

            {{--
                ⚠️ **THIS SENTENCE HAS NOW BEEN WRONG IN THREE DIFFERENT
                WAYS AND ALL THREE ARE KEPT AND DATED.** It said *"Email has
                its own reporting"* while nothing counted email at all; 6375
                corrected it to *"Email is counted separately and is not
                shown here"*, which was true for one day and named the gap
                6378(a) recorded as owed; **the email panel exists as of
                2026-08-21 (6660)** and the sentence now points at it.

                ⚠️ **THE TWO GRIDS STAY SEPARATE, WHICH IS THE HALF OF THE
                ORIGINAL ARGUMENT THAT SURVIVED.** `SendingHealth` counts
                per channel and never adds them together, so one merged set
                of cards would put a carrier's delivery receipts and SES's
                bounce vocabulary behind one number — and the complaint rate
                that stops a business is evaluated per channel, so a merged
                rate would be a figure the trip never reads.

                ⚠️ **THE PREVIOUS COMMENT HERE CITED 6377 AND THE DECISION
                IS 6375** — 6377 is the SMTP-versus-API ruling. Corrected in
                passing rather than left, because a wrong citation is the
                cheapest possible way to send the next reader to the wrong
                argument.
            --}}

            <div class="mt-5 grid gap-4 sm:grid-cols-3">
                {{--
                    empty-state: absent because the readings are three fixed
                    measurements built for every business — delivery,
                    complaints, volume — not a query that can come back
                    short. `SendingRates` answers a dash where there is no
                    denominator, which is this grid's real empty state and
                    is per reading rather than per screen.
                --}}
                @foreach ($readings as $reading)
                    <div wire:key="reading-{{ $loop->index }}" class="rounded-[--radius-control] border border-rule bg-paper p-4">
                        <p class="text-sm font-semibold text-ink-2">{{ $reading->name }}</p>

                        {{--
                            A DASH, NEVER "0.0%". `SendingRates` answers 0
                            for a rate with no denominator — correctly, for
                            a threshold comparison — and rendering that as a
                            percentage would tell an operator this business
                            has been measured and is perfect.
                        --}}
                        <p class="mt-1 font-mono text-2xl tabular-nums text-ink">{{ $reading->figure() }}</p>

                        <div class="mt-2">
                            <x-ui.status-pill :state="$reading->state()" :label="$reading->stateLabel()" />
                        </div>

                        <p class="mt-2 text-sm text-ink-2">{{ $reading->sentence() }}</p>
                    </div>
                @endforeach
            </div>

            {{--
                HOW A STOP IS WORKED OUT, FOR THIS BUSINESS — T176 P9.

                The "Complaints" card above already carries the rate, the
                threshold and the significance floor. What it cannot say is
                what those three do together: how many complaints, at the
                volume this business has actually delivered, is a stop. That
                is the number an operator watching a live campaign is
                actually asking for, and it was nowhere on this screen.
            --}}
            @if ($tenantTripMath !== null)
                <div class="mt-5 rounded-[--radius-control] border border-rule bg-paper p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.status-pill :state="$tenantTripMath->state()" :label="$tenantTripMath->stateLabel()" />
                        {{-- The other half of 4499's heading pair. --}}
                        <h3 class="font-display text-sm font-semibold text-ink">How a stop is worked out for this business</h3>
                    </div>

                    <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm text-ink-2">Stops at</dt>
                            <dd class="font-mono text-lg tabular-nums text-ink">{{ $tenantTripMath->thresholdFigure() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-2">Smallest sample</dt>
                            <dd class="font-mono text-lg tabular-nums text-ink">
                                {{ number_format($tenantTripMath->floorDelivered) }} delivered
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-2">Right now</dt>
                            {{-- A dash, never "0.00%" — the same rule the
                                 "Complaints" card above answers with, and
                                 since 4484 the same answer: `TripMath` nulls
                                 the rate on an empty denominator exactly as
                                 `RateReading` does, so the two cannot say
                                 different things about one business. --}}
                            <dd class="font-mono text-lg tabular-nums text-ink">{{ $tenantTripMath->standingFigure() }}</dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-base text-ink-2">{{ $tenantTripMath->arithmetic() }}</p>

                    @if ($tenantTripMath->noiseWarning() !== null)
                        <x-ui.attention-card class="mt-3" state="attention" heading="These two figures fight each other">
                            {{ $tenantTripMath->noiseWarning() }}
                        </x-ui.attention-card>
                    @endif
                </div>
            @endif
        </section>

        {{-- ────────────────  How their email is doing  ──────────────── --}}
        <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
            {{--
                ⛔ **THE EMAIL COMPLAINT RATE COULD PAUSE A BUSINESS AND NO
                SCREEN SHOWED IT** — 6375, recorded as owed at 6378(a) and
                closed here (6660). 6360 gave all four email counters
                writers, so `SendingGuard::refusalFor(OutreachChannel::Email)`
                — which the review-invite sender calls on every email invite
                — reads a real rate and can open a real pause. An operator
                could not see that rate, could not see it approaching, and
                could not tell afterwards which channel had produced a stop.

                ⚠️ **A RATE WITH NO READER IS HOW 2496 SURVIVED TWO SLICES.**
                The counters were permanently zero and every screen agreed
                with them. This panel is the reader, and it is built so that
                a zero is visibly a zero: `RateReading` carries the
                denominator beside the rate and answers a dash where there
                is none, so a business nothing has been emailed for reads as
                *not measured* rather than as a perfect score.

                ⚠️ **NO REAL SES EVENT HAS EVER REACHED THIS APPLICATION**
                (6373). Open question H — an SES account, production access
                and a signed DPA — is the owner's, so every figure here is
                exercised only by tests today. The panel is deliberately
                silent about that: it says what it measured, and *nothing
                measured* is what an operator sees, which is the true
                statement either way.
            --}}
            <h2 class="font-display text-lg font-semibold text-ink">How their email is doing</h2>

            <p class="mt-1 text-base text-ink-2">
                Email only, over the last
                {{ $this->windowHours() }} hours.
                Text messages are counted separately, in the panel above.
            </p>

            {{--
                ⛔ **THE SENTENCE THAT WOULD OTHERWISE BE GUESSED WRONG.**
                `sending_pauses` has no channel column — one live row per
                business — and `SendingGuard` asks "is this tenant paused"
                before it asks "has this channel tripped", so a stop opened
                by an email complaint rate refuses the next TEXT MESSAGE
                too. An operator who read this panel as "email's own switch"
                would release a business and expect texts to resume for a
                reason that has nothing to do with what stopped them.
            --}}
            <x-ui.attention-card class="mt-4" state="attention" heading="Either channel stops both">
                A complaint rate crossing the line on email stops this
                business from sending anything at all — texts included — and
                the same is true the other way round. There is one stop per
                business, not one per channel, so the record of it cannot say
                which channel earned it. These two panels are how you tell.
            </x-ui.attention-card>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                {{--
                    empty-state: absent because these are two fixed
                    measurements built for every business — delivery and
                    complaints — rather than a query that can come back
                    short. There is no state in which this grid renders
                    nothing, and an invitation would be inviting an operator
                    to do something about a list that cannot be empty.

                    ⚠️ **THE REAL EMPTY STATE IS PER READING AND IS THE
                    WHOLE POINT OF THIS PANEL.** A business with no email
                    traffic gets a dash and "Not measured yet" on both cards
                    — `RateReading` answers that from the denominator — and
                    collapsing the section instead would hide the one
                    distinction 2496 turned on, which is that an empty
                    counter is not a zero rate.
                --}}
                @foreach ($emailReadings as $reading)
                    <div wire:key="email-reading-{{ $loop->index }}" class="rounded-[--radius-control] border border-rule bg-paper p-4">
                        <p class="text-sm font-semibold text-ink-2">{{ $reading->name }}</p>

                        {{-- A DASH, NEVER "0.0%" — the grid above's rule,
                             and the one thing this panel could not be built
                             without. --}}
                        <p class="mt-1 font-mono text-2xl tabular-nums text-ink">{{ $reading->figure() }}</p>

                        <div class="mt-2">
                            <x-ui.status-pill :state="$reading->state()" :label="$reading->stateLabel()" />
                        </div>

                        <p class="mt-2 text-sm text-ink-2">{{ $reading->sentence() }}</p>
                    </div>
                @endforeach
            </div>

            {{--
                ⛔ **THERE IS NO "Asked to stop" CARD HERE AND THE SCREEN
                SAYS SO RATHER THAN LEAVING A HOLE** (6662). Nothing in
                `app/` writes the email opt-out counter — the one caller of
                `SendingHealth::recordOptOut()` names `OutreachChannel::Sms`
                as a literal — so the card would divide a permanently-zero
                numerator by a denominator that really is growing, and
                `RateReading` would rightly call that MEASURED: a confident
                0.0% against real volume, sitting in the best-looking tile
                on the screen. That is 2496 rebuilt as a rendering, on the
                screen written to contain it. Saying it out loud costs one
                sentence and is the difference between an absence an
                operator can act on and one they do not notice.
            --}}
            <p class="mt-4 text-sm text-ink-2">
                There is no unsubscribe rate for email. Nothing counts one
                yet, and an uncounted rate would show here as a flawless
                zero over real volume — so it is left out until something
                counts it. Unsubscribes are still honoured; they are not
                measured on this screen.
            </p>

            {{--
                HOW A STOP IS WORKED OUT, ON THIS CHANNEL. Same two settings
                as the panel above — there is no per-channel threshold in
                this application — measured against email volume. The
                configured figures are rendered and nothing is proposed:
                6379(a) asks whether an SMS-derived 3% over 50 delivered is
                right for email, where the industry line is an order of
                magnitude lower, and that is the owner's question.
            --}}
            @if ($emailTripMath !== null)
                <div class="mt-5 rounded-[--radius-control] border border-rule bg-paper p-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <x-ui.status-pill :state="$emailTripMath->state()" :label="$emailTripMath->stateLabel()" />
                        <h3 class="font-display text-sm font-semibold text-ink">How email stops this business</h3>
                    </div>

                    <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-sm text-ink-2">Stops at</dt>
                            <dd class="font-mono text-lg tabular-nums text-ink">{{ $emailTripMath->thresholdFigure() }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-2">Smallest sample</dt>
                            <dd class="font-mono text-lg tabular-nums text-ink">
                                {{ number_format($emailTripMath->floorDelivered) }} delivered
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-ink-2">Right now</dt>
                            <dd class="font-mono text-lg tabular-nums text-ink">{{ $emailTripMath->standingFigure() }}</dd>
                        </div>
                    </dl>

                    {{-- THE NUMERATOR AND THE DENOMINATOR, NOT ONLY THE
                         RATE. Two complaints out of three delivered is 67%
                         and is noise; the same 67% over three hundred is an
                         emergency. An operator shown only the percentage
                         makes the wrong call on one of the two, so
                         `TripMath::arithmetic()` states both counts. --}}
                    <p class="mt-3 text-base text-ink-2">{{ $emailTripMath->arithmetic() }}</p>

                    @if ($emailTripMath->noiseWarning() !== null)
                        <x-ui.attention-card class="mt-3" state="attention" heading="These two figures fight each other">
                            {{ $emailTripMath->noiseWarning() }}
                        </x-ui.attention-card>
                    @endif
                </div>
            @endif
        </section>

        {{-- ────────────────  Every time they were stopped  ──────────────── --}}
        <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
            <h2 class="font-display text-lg font-semibold text-ink">Every time they were stopped</h2>

            <p class="mt-1 text-base text-ink-2">
                Newest first. Nothing here is ever deleted or edited — a
                released incident keeps the reason, the rate at the time and
                both notes.
            </p>

            @if ($history === [])
                {{--
                    No action: the control that would stop them is the one
                    further up this same screen, and offering it from inside
                    a history panel would make "stop this business" the
                    thing an operator reaches for while reading that nothing
                    has ever gone wrong.
                --}}
                <x-ui.empty-state class="mt-4" icon="✓">
                    This business has never been stopped from sending.
                </x-ui.empty-state>
            @else
                <ul class="mt-4 space-y-4">
                    @foreach ($history as $pause)
                        <li wire:key="pause-{{ $pause->id }}" class="border-b border-rule pb-4 last:border-0 last:pb-0">
                            <div class="flex flex-wrap items-center gap-3">
                                <x-ui.status-pill
                                    :state="$pause->isLive() ? \App\Enums\SignalState::Alert : \App\Enums\SignalState::Ok"
                                    :label="$pause->isLive() ? 'Still stopped' : 'Released'"
                                />
                                <span class="text-sm text-ink-2">
                                    {{ $pause->created_at?->format('j M Y, H:i') }}
                                </span>
                            </div>

                            <p class="mt-2 text-base text-ink-2">
                                {{ $pause->reason->label() }} — stopped by {{ $pause->tripped_by }}@if ($pause->observed_rate_bp !== null), at {{ number_format($pause->observed_rate_bp / 100, 1) }}%@endif.
                                @if ($pause->note)
                                    <span class="block">Note: {{ $pause->note }}</span>
                                @endif
                            </p>

                            @if (! $pause->isLive())
                                <p class="mt-1 text-base text-ink-2">
                                    Started again {{ $pause->released_at?->format('j M Y, H:i') }}
                                    by {{ $pause->released_by }}@if ($pause->release_note) — {{ $pause->release_note }}@endif.
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    <p class="text-sm text-ink-3">
        There is no list of businesses on this page because the database
        cannot produce one: a business admits a reader only as that business
        or as its owner, so enumerating them would need another row-level
        security policy and a session flag saying "platform staff". That is a
        decision somebody has to make deliberately.
    </p>
</div>

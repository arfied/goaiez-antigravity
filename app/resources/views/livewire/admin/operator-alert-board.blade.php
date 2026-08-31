{{--
    Every bell this platform has rung at itself — the reader `operator_alerts`
    never had. See App\Livewire\Admin\OperatorAlertBoard for the whole argument.

    READ-ONLY, AND THERE IS NO ACKNOWLEDGE BUTTON. R25: a bell, never a brake.
    The row IS the de-duplication, so a "dismiss" here would RE-ARM the alert
    rather than quieten it, and "how long a bell stays quiet" is already one
    field on the Ops settings screen. Whether an operator may mark an alert as
    handled is the owner's question and is raised rather than guessed at.

    THE THIRD SECTION IS WHY THE FIRST TWO BEING EMPTY MEANS ANYTHING. Nothing
    has ever rung in production, and "nothing rang" and "nothing is being
    checked" look identical on a blank page. Several kinds hang off a registry
    threshold, and both alert channels ship blank.

    THIS PARAGRAPH READ "FOUR KINDS ... WHERE 0 TURNS THE CHECK OFF" UNTIL
    2026-08-22, AND 7740-7759 HAD ALREADY CORRECTED THAT CLAIM ONE SECTION
    FURTHER DOWN THIS SAME FILE. There were five in the map when it said four,
    three further kinds had a threshold this screen said nothing about, and on
    none of those three does a zero turn the check off. A stale sentence in a
    file header is the one a reader trusts most, because it reads as the summary.

    NO PERSONAL DATA. Across every raiser the subject is a process, an endpoint,
    a provider, a pixel build token, a business number, or nothing at all. The
    one value on this table written by a stranger is context.origin — the Origin
    request header — so context is rendered as escaped, clipped data in a mono
    face rather than as prose.

    A DELIVERY STAMP MEANS "HANDED OVER", NEVER "DELIVERED", and its absence
    means no record rather than "not sent" — the creating migration's rule.
    SINCE 2026-08-22 THERE IS A THIRD OUTCOME AND IT IS RENDERED: a push
    deliberately withheld because the bell had spent its day's allowance. Two
    nulls used to mean "nobody was listening, or both channels failed", which is
    the opposite diagnosis, and push_withheld_at is what tells them apart.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What has alerted us</h1>
        <p class="mt-1 text-base text-ink-2">
            Every time this platform has woken somebody — a queue that stopped, a
            provider failing, a tenant's pixel being refused. The same alert stays
            quiet for {{ $quietMinutes }} {{ \Illuminate\Support\Str::plural('minute', $quietMinutes) }}
            after it rings, so something that stays broken appears here once per
            window rather than once per check.
        </p>
        <p class="mt-2 text-base text-ink-2">
            Nothing on this page changes anything.
            There is no way to silence, acknowledge or delete an alert from here
            — these rows are what stops the same bell ringing every few minutes, so
            removing one would start it ringing again. Old alerts are cleared out
            automatically once they are long past that, never while one is still
            keeping a bell quiet.
        </p>
    </div>

    {{-- ──────────────────────────  Still ringing  ────────────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">Still ringing — the last {{ $ringingHours }} hours</h2>

        <p class="mt-1 text-base text-ink-2">
            Grouped the same way the platform decides whether to ring at all: by
            what went wrong, and what it went wrong with. One line that rang eight
            times is one thing that has been broken all night. Eight lines that
            rang once each is a different kind of night.
        </p>

        {{--
            SAYS WHY THE ORDER IS THE ORDER. An operator who cannot see why one
            line is above another reads the list as arbitrary and falls back to
            scanning it, which is the thing the ordering exists to save them.
        --}}
        <p class="mt-1 text-base text-ink-2">
            Needs action first, then most recent. Something needs action when
            it is still costing us while nobody is looking, or when it has gone
            on ringing through more than one quiet window. Everything else needs
            a look rather than a night.
        </p>

        {{--
            SAYS WHERE THE LIST STOPS *BEFORE* IT STOPS, and says the one
            thing a reader has to be able to rely on: the counts are whole.
            A line is a group and its count is taken over the whole window,
            so a bounded list understates how many LINES there were and
            never how many times one of them rang — which is the trade
            7756 refused when the bound was on the rows instead.
        --}}
        <p class="mt-1 text-sm text-ink-3">
            At most {{ $ringingLines }} lines, and every count on them is a whole
            count — nothing inside a line is cut short. If more than that rang,
            what is missing is summarised underneath rather than dropped.
        </p>

        @if ($ringing->isEmpty())
            <x-ui.empty-state class="mt-4" icon="✓">
                Nothing has rung in the last {{ $ringingHours }} hours. Read "Every
                bell this platform can ring" below before treating that as good news:
                a check that has been switched off is quiet in exactly the same way.
            </x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($ringing as $entry)
                    <li wire:key="ringing-{{ $entry['latest']->id }}" class="border-b border-rule pb-4 last:border-0 last:pb-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-base font-semibold text-ink">{{ $entry['kind']->headline() }}</p>

                                @if ($entry['subject'] !== '')
                                    <p class="text-sm text-ink-3">
                                        About <code class="font-mono text-sm">{{ $entry['subject'] }}</code>
                                    </p>
                                @endif
                            </div>

                            {{--
                                TWO FACTS, TWO ELEMENTS. The pill says what
                                this needs — its own icon and its own word,
                                so colour is never the sole indicator — and
                                the count sits beside it as plain text.

                                The pill used to carry the count as its label
                                and derive its colour from it, which was the
                                only ranking this screen had. The kind's own
                                urgency is the floor now and repetition still
                                raises it, so the two can disagree and the
                                label can no longer stand for both.
                            --}}
                            <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                <x-ui.status-pill :state="$entry['state']" />

                                <span class="text-sm text-ink-3">
                                    rang {{ $entry['times'] }} {{ \Illuminate\Support\Str::plural('time', $entry['times']) }}
                                </span>
                            </div>
                        </div>

                        <p class="mt-2 text-base text-ink-2">{{ $entry['latest']->summary }}</p>

                        <p class="mt-2 text-sm text-ink-3">
                            @if ($entry['times'] > 1)
                                Last rang {{ $entry['lastAt']->format('j M Y, H:i') }}
                                ({{ $entry['lastAt']->diffForHumans() }}), first at
                                {{ $entry['firstAt']->format('j M Y, H:i') }}.
                            @else
                                Rang {{ $entry['lastAt']->format('j M Y, H:i') }}
                                ({{ $entry['lastAt']->diffForHumans() }}).
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif

        {{--
            WHAT STOPPED REACHING THE OPERATOR, AT EVERY VOLUME AND BEHIND
            NO GATE. This block used to be one clause inside the summary
            below, which only renders when MORE LINES rang than fit on the
            page — a statement about breadth. A spent push budget is a
            statement about depth: at the seeded quiet window one account on
            one kind rings twenty-four times a day and spends that kind's
            whole daily allowance by itself, and the screen for that night
            draws ONE line and printed nothing about the pager at all.

            IT IS FIRST BECAUSE IT OUTRANKS THE TRUNCATION NOTICE. "More rang
            than fits here" is a fact about this page. "You stopped being
            told" is a fact about the last twenty-four hours, and it is the
            one that changes what somebody does next.

            A LIMIT, NEVER A CHANNEL THAT FAILED. Both delivery stamps are
            null on these rows for a completely different reason from a
            broken address, and saying which is what push_withheld_at was
            minted for.
        --}}
        @if ($withheld !== null)
            <div class="mt-5 border-t border-rule pt-4">
                <h3 class="text-base font-semibold text-ink">You stopped being told about some of this</h3>

                {{--
                    EACH SENTENCE THAT CARRIES A FIGURE IS ON ONE SOURCE
                    LINE — Blade keeps the newline between two expressions,
                    so a number wrapped away from its words renders as
                    "30\n alerts" and stops being assertable.
                --}}
                <p class="mt-1 text-base text-ink-2">
                    {{ number_format($withheld['alerts']) }} of the alerts below were recorded and deliberately not emailed or texted,
                    because the bell they belong to had already been sent as many times as it may be in a day.
                    Nothing failed: this platform stopped sending them on purpose, and it is still recording every one.
                </p>

                <ul class="mt-3 space-y-2">
                    @foreach ($withheld['kinds'] as $row)
                        <li wire:key="withheld-{{ $row['kind']->value }}" class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="text-base font-semibold text-ink">{{ $row['kind']->headline() }}</span>
                            <span class="w-full text-base text-ink-2 sm:w-auto sm:flex-1">
                                Stopped being sent at {{ $row['since']->format('j M Y, H:i') }}.
                                {{ number_format($row['alerts']) }} {{ \Illuminate\Support\Str::plural('alert', $row['alerts']) }} held back since,
                                about {{ number_format($row['subjects']) }} {{ \Illuminate\Support\Str::plural('thing', $row['subjects']) }}.
                            </span>
                        </li>
                    @endforeach
                </ul>

                <p class="mt-3 text-sm text-ink-3">
                    A bell starts being sent again as its earlier ones roll out of the last 24 hours.
                    The limit is fixed in the code and there is nothing on any screen that turns it up or off.
                </p>
            </div>
        @endif

        {{--
            WHAT THE LIST HAD NO ROOM FOR, AS A QUANTITY RATHER THAN A
            SILENCE. On the night this appears at all, the quantity is the
            diagnosis: one kind across nine hundred accounts and nine hundred
            different things going wrong draw the same twenty-five lines and
            are completely different nights. Absent when the list above is
            the whole truth, which is every ordinary night.

            IT SAID "IT IS ALSO THE ONLY PLACE A WITHHELD PUSH IS VISIBLE
            IN AGGREGATE", AND THAT WAS THE DEFECT RATHER THAN THE FEATURE.
            It was true, and this block only renders when more LINES rang
            than fit — so the one figure saying the pager had gone deaf sat
            behind a gate about breadth, while a pager goes deaf on depth.
            The block above owns it now and renders whenever it is true.
        --}}
        @if ($spread !== null)
            <div class="mt-5 border-t border-rule pt-4">
                <h3 class="text-base font-semibold text-ink">More rang than fits here</h3>

                {{--
                    EACH SENTENCE THAT CARRIES A FIGURE IS ON ONE SOURCE LINE.
                    Blade keeps the newline and the indentation between two
                    expressions, so a number wrapped away from the words
                    around it renders as "30\n things" — readable on screen
                    and unassertable, which is how a rendered claim stops
                    being pinned by anything.
                --}}
                <p class="mt-1 text-base text-ink-2">
                    {{ number_format($spread['groups']) }} different things rang {{ number_format($spread['alerts']) }} times
                    in the last {{ $ringingHours }} hours, so {{ number_format($spread['hidden']) }} of them are not listed above.
                    The counts on the lines that are listed are still whole counts.
                </p>

                <ul class="mt-3 space-y-2">
                    @foreach ($spread['kinds'] as $row)
                        <li wire:key="spread-{{ $row['kind']->value }}" class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="text-base font-semibold text-ink">{{ $row['kind']->headline() }}</span>
                            <span class="w-full text-base text-ink-2 sm:w-auto sm:flex-1">
                                Went wrong with {{ number_format($row['subjects']) }} {{ \Illuminate\Support\Str::plural('thing', $row['subjects']) }},
                                {{ number_format($row['alerts']) }} {{ \Illuminate\Support\Str::plural('time', $row['alerts']) }} in all.
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- ─────────────────────  Everything, newest first  ───────────────────── --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">Everything, newest first</h2>

        <p class="mt-1 text-base text-ink-2">
            What fired, when, what it said, and the figures behind it. The figures
            are kept because by the time anybody looks, the measurement window has
            rolled and the sample is gone.
        </p>

        {{--
            SAYS WHERE THE LIST STOPS. Alerts are removed after this long, so
            "everything" above means everything still kept — and a reader who
            finds nothing here for last spring has to be able to tell that
            from nothing having happened. Nobody can remove one by hand.
        --}}
        <p class="mt-2 text-sm text-ink-3">
            Alerts are kept for {{ $retentionDays }} days and then removed automatically.
            Nothing older than that is here, and nobody can remove one before then.
        </p>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Show</span>
                <select
                    wire:model.live="kind"
                    class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                >
                    <option value="">Everything</option>
                    @foreach ($kinds as $case)
                        <option value="{{ $case->value }}">{{ $case->headline() }}</option>
                    @endforeach
                </select>
            </label>

            @if ($kind !== '')
                <button
                    type="button"
                    wire:click="clearKind"
                    class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                >
                    Show everything
                </button>
            @endif
        </div>

        {{--
            SAYS WHERE THE FILTER STOPS, so nobody reads a still-visible line
            above as a filter that did not work. Narrowing the incident view
            would hide the thing somebody opened this page to see.

            THE REASON GIVEN HERE USED TO BE THAT THE SECTION ABOVE IS "SMALL
            BY CONSTRUCTION — the de-duplication bounds it to one line per
            kind and subject per quiet window", AND THAT WAS THE FALSE
            PREMISE THIS SCREEN WAS BUILT ON (7960-7979). The dedupe key is
            right and the conclusion is not: the quiet window's floor is one
            minute and four kinds take a tenant's id as their subject. The
            second sentence below said "always shows everything", which the
            bound makes untrue, so it now says the thing that is still true —
            this filter never touches it.
        --}}
        <p class="mt-2 text-sm text-ink-3">
            This narrows the list below only.
            Still ringing, above, covers the last {{ $ringingHours }} hours whatever this is set to.
        </p>

        @if ($alerts->isEmpty())
            <x-ui.empty-state class="mt-4" icon="○">
                @if ($kind !== '')
                    No alert of this kind is on record. Other kinds may be —
                    switch back to Everything to see them.
                @else
                    {{--
                        MAKES NO CLAIM ABOUT *EVER*, SINCE 2026-08-22. This
                        read "This platform has never raised an alert", and
                        once rows expire at the retention horizon the screen
                        cannot know that: the evidence for "never" is exactly
                        what was removed. `ops:alert-channels` reaches the
                        same answer for the same reason.
                    --}}
                    No alert is on record. That is the state a new install
                    starts in, so it is not yet evidence of anything — and
                    alerts are removed after {{ $retentionDays }} days, so it is
                    not a claim that none was ever raised either. The section
                    below says which checks are switched on, and where an
                    alert would go if one fired.
                @endif
            </x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-5">
                @foreach ($alerts as $alert)
                    <li wire:key="alert-{{ $alert->id }}" class="border-b border-rule pb-5 last:border-0 last:pb-0">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-base font-semibold text-ink">{{ $alert->kind->headline() }}</p>

                                @if ($alert->subject !== '')
                                    <p class="text-sm text-ink-3">
                                        About <code class="font-mono text-sm">{{ $alert->subject }}</code>
                                    </p>
                                @endif
                            </div>

                            <p class="text-sm text-ink-3">
                                {{ $alert->fired_at->format('j M Y, H:i') }}
                                ({{ $alert->fired_at->diffForHumans() }})
                            </p>
                        </div>

                        <p class="mt-2 text-base text-ink-2">{{ $alert->summary }}</p>

                        {{--
                            THE ONE PLACE A STRANGER'S TEXT REACHES THIS SCREEN.
                            context.origin is the Origin request header, which the
                            collector's own comment calls trivially settable by
                            anything that is not a browser. Rendered as escaped,
                            clipped data in a mono face rather than as a sentence,
                            so it reads as evidence somebody has to judge and never
                            as something this platform is asserting.
                        --}}
                        @if ($alert->context !== [])
                            <dl class="mt-3 grid gap-x-6 gap-y-1 sm:grid-cols-2">
                                @foreach ($alert->context as $label => $value)
                                    <div wire:key="alert-{{ $alert->id }}-{{ $label }}" class="flex flex-wrap items-baseline gap-2">
                                        <dt class="text-sm text-ink-3">{{ \Illuminate\Support\Str::headline((string) $label) }}</dt>
                                        <dd class="font-mono text-sm break-all text-ink-2">
                                            @if ($value === null)
                                                <span class="font-sans text-ink-3">not recorded</span>
                                            @elseif (is_bool($value))
                                                {{ $value ? 'yes' : 'no' }}
                                            @else
                                                {{ \Illuminate\Support\Str::limit((string) $value, 120) }}
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif

                        {{--
                            A TIMESTAMP HERE MEANS THE CHANNEL ACCEPTED THE
                            MESSAGE, NEVER THAT IT ARRIVED — neither channel
                            confirms anything back to this row. Its absence is
                            "no record", not "not sent": the commonest cause is
                            that no address or number is configured at all, which
                            the section below reports.

                            THIS SAID "HANDED THE MESSAGE OVER" AND THE SENTENCES
                            BELOW SAID "HANDED OVER TO THE MAIL SYSTEM" UNTIL
                            2026-08-25 — BOTH READINGS KEPT AND DATED (4368's
                            rule). That was written when `OperatorAlerts::email()`
                            was a bare dispatch, so the stamp genuinely meant "a
                            row was written to `jobs`" and nothing more; 9371
                            moved it to `deliverNow()` and levelled the meaning UP,
                            so both columns now mean the transport or the carrier
                            accepted it.

                            THE STALENESS RAN IN THE SAFE DIRECTION AND IS STILL
                            WORTH THE EDIT. It understated both columns rather
                            than over-claiming either, so it was not 314–316 — it
                            was the distinction 9371 was built to give an operator,
                            thrown away on the one screen where they read it. "We
                            put it in our own queue" and "somebody else's server
                            took it" are different answers to *did anybody hear the
                            bell*, and only one of them is now true.
                        --}}
                        {{--
                            THE WITHHELD BRANCH IS FIRST AND IT IS NOT AN
                            ORDERING PREFERENCE. A withheld alert has both
                            stamps null, so without this arm it fell into the
                            "no record" sentence below — which reads as a
                            channel that failed, and is the exact conflation
                            push_withheld_at was minted to end. The column
                            landed one wave ago and no blade rendered it.
                        --}}
                        <p class="mt-3 text-sm text-ink-3">
                            @if ($alert->push_withheld_at !== null)
                                Deliberately not emailed or texted at
                                {{ $alert->push_withheld_at->format('H:i') }} — this bell had
                                already pushed as much as it may in a day. It is kept here and in
                                the application log. A limit stopped it, not a channel that is failing.
                            @elseif ($alert->emailed_at === null && $alert->texted_at === null)
                                No record of this being emailed or texted. It reached the application log and, as far as this row knows, nowhere else.
                            @elseif ($alert->emailed_at !== null && $alert->texted_at !== null)
                                Accepted by the mail transport at {{ $alert->emailed_at->format('H:i') }}
                                and by the text provider at {{ $alert->texted_at->format('H:i') }} — accepted is not the same as arriving.
                            @elseif ($alert->emailed_at !== null)
                                Accepted by the mail transport at {{ $alert->emailed_at->format('H:i') }} — accepted is not the same as arriving.
                            @else
                                Accepted by the text provider at {{ $alert->texted_at?->format('H:i') }} — accepted is not the same as arriving.
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-4">{{ $alerts->links() }}</div>
    </section>

    {{-- ──────────────────  Every bell this platform can ring  ────────────────── --}}
    {{--
        NOT DECORATION, AND NOT THE HEALTH BOARD OF `28` §9.8 EITHER. It exists
        because the two sections above will be empty on the day this ships, and an
        empty alert board has two readings a reader cannot tell apart. Several
        kinds have a threshold in Ops, and both channels ship blank.

        THIS COMMENT SAID "FOUR KINDS … WHERE 0 DISABLES THE CHECK OUTRIGHT" AND
        WAS WRONG TWICE OVER (7740-7759): the map had five entries, and three
        further kinds have a threshold on which 0 does something other than
        disable. No count is written here now — that is the stale-count failure
        `bells()`' own docblock warns about, arriving in the file beside it.

        THE LIST COUNTS ITSELF. It is OperatorAlertKind::cases(), so a kind added
        tomorrow appears here without anybody remembering to add it, and a kind
        this screen knows no threshold for says so rather than guessing.
    --}}
    <section class="rounded-[--radius-card] border border-rule bg-card p-5 shadow-[--shadow-card]">
        <h2 class="font-display text-lg font-semibold text-ink">Every bell this platform can ring</h2>

        <p class="mt-1 text-base text-ink-2">
            A quiet board and a switched-off check look exactly the same from the
            outside. This is the difference between them.
        </p>

        <h3 class="mt-5 text-base font-semibold text-ink">Where an alert goes</h3>

        <ul class="mt-2 space-y-3">
            {{--
                empty-state: absent because this loop cannot come back empty. The
                three channels are written out in `channels()` and one of them —
                the log — is unconditional, so an invitation here would be dead
                markup. What each row can say is "not set", which is the state
                that matters and is rendered above.
            --}}
            @foreach ($channels as $channel)
                <li wire:key="channel-{{ $channel['label'] }}" class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                    <span class="text-base font-semibold text-ink">{{ $channel['label'] }}</span>
                    <x-ui.status-pill :state="$channel['state']" :label="$channel['status']" />
                    <span class="w-full text-base text-ink-2 sm:w-auto sm:flex-1">{{ $channel['detail'] }}</span>
                </li>
            @endforeach
        </ul>

        <h3 class="mt-6 text-base font-semibold text-ink">What is being watched</h3>

        <ul class="mt-2 space-y-3">
            {{--
                empty-state: absent because this loop is `OperatorAlertKind::cases()`
                mapped one-for-one, and a PHP enum cannot have no cases. It is the
                same exclusion the lint already makes for a bare `::cases()` loop;
                the mapping is what hides it, not the possibility of emptiness.
            --}}
            @foreach ($bells as $bell)
                <li wire:key="bell-{{ $bell['kind']->value }}" class="border-b border-rule pb-3 last:border-0 last:pb-0">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <span class="text-base font-semibold text-ink">{{ $bell['kind']->headline() }}</span>
                        <x-ui.status-pill :state="$bell['state']" :label="$bell['status']" />
                    </div>
                    <p class="mt-1 text-base text-ink-2">{{ $bell['detail'] }}</p>
                </li>
            @endforeach
        </ul>

        {{--
            SAYS WHAT IT CANNOT SEE, rather than letting a reader infer that a kind
            with no threshold is one nobody is watching for.

            IT USED TO SAY THE OPPOSITE OF SOMETHING TRUE (7740-7759): "only the
            platform-health checks have a threshold that can be turned down to
            zero, and those are the ones marked above." Three of the kinds marked
            as having no threshold had one, and on two of them a zero makes the
            bell structurally unreachable while doing something else besides. Each
            row above now states its own zero; this paragraph makes the general
            claim it can still stand behind, which is that a zero is not one thing.
        --}}
        <p class="mt-4 text-sm text-ink-3">
            A threshold of zero does not mean the same thing on every row above, so
            each row says what its own does — on some it switches the check off, on
            one it is a hair trigger, and on one it stops a whole feature for every
            account. For the kinds with no threshold at all, whether a bell can ring
            is a fact about the work it hangs off — an erasure, a webhook, the
            nightly Google sweep — and this screen cannot tell you whether that work
            ran.
        </p>
    </section>
</div>

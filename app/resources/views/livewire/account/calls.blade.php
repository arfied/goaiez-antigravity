{{--
    Your phone — T137 R7 (forwarding setup) and R8 (the number it forwards to).

    ⚠️ THE NUMBER COMES FIRST AND THAT IS THE WHOLE ORDERING ARGUMENT. Every code
    below contains it, and until this screen existed no surface in the
    application told an owner what their own number was — so the instruction
    "forward your line to your GO AI EZ number" was unfollowable. The number is
    the answer to the first question somebody arriving here has.

    ⚠️ NOTHING ON THIS PAGE CLAIMS A FORWARD IS WORKING. We cannot see a carrier's
    settings — that needs a test call through a voice API that is not activated
    (decision 2109) — so `forwarding_verified_at` is unwritten (2913) and the copy
    says so in words. A green tick here would be the exact "protection layer
    asserted before it is true" `CLAUDE.md` warns about, on a page whose whole
    subject is whether the phone rings.

    ⚠️ THE CODES ARE `26` §3.2's, VERBATIM, AND NO CARRIER IS NAMED. The document
    carries four generic GSM strings and an instruction to verify per carrier; it
    carries no per-carrier table, so none is invented here. The caveat below is
    the document's own sentence rather than a softened version of it.

    COLOUR IS NEVER THE SIGNAL (`22`). The chosen mode is carried by the radio and
    by words, never by a hue. WORKS AT 320px — the codes are a stacked list rather
    than a table, and the dial strings are `IBM Plex Mono` at `text-base`, because
    an owner is going to read one character at a time off this page.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your phone</h1>
        <p class="mt-1 text-base text-ink-2">
            We can pick up the calls you miss, take a message and text the caller
            straight back. You decide how much of that we do, and you can change it
            whenever you like.
        </p>
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Your GO AI EZ number</h2>

        @if ($number === null)
            {{--
                Named rather than blank. A tenant can legitimately have no number
                — `TenantNumbers::claimForTenant()` returns null, loudly in the
                log, on a platform with no pool loaded — and an empty panel here
                reads as a broken page rather than as a thing to ask us about.
            --}}
            <p class="mt-2 text-base text-ink-2">
                You do not have one yet. Ask us and we will sort it out — nothing below
                will work until you have it.
            </p>
        @else
            <p class="mt-2 font-mono text-base text-ink" data-testid="tenant-number">{{ $number }}</p>
            <p class="mt-2 text-base text-ink-2">
                This is yours. Calls, texts and pictures all use it, and it is where you
                point your own line below.
            </p>
        @endif
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">How your calls are handled</h2>

        @if ($mayChoose)
            <form wire:submit="save" class="mt-4 space-y-5">
                <fieldset>
                    <legend class="text-lg font-medium text-ink">What we do with a call</legend>

                    <div class="mt-3 space-y-3">
                        @foreach ($modes as $option)
                            <label class="flex gap-3 rounded-[--radius-card] border border-rule p-4">
                                <input
                                    type="radio"
                                    wire:model="mode"
                                    value="{{ $option->value }}"
                                    class="mt-1"
                                >
                                <span>
                                    <span class="block text-base font-medium text-ink">{{ $option->label() }}</span>
                                    <span class="block text-base text-ink-2">{{ $option->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('mode') <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p> @enderror
                </fieldset>

                <x-ui.submit target="save" busy="Saving…">Save</x-ui.submit>
            </form>
        @else
            {{--
                Named rather than hidden, and never a disabled radio. A greyed-out
                control reads as a bug in our page; a sentence naming who can do
                this reads as the truth and tells them who to ask.
                `Account\Knowledge` makes the same call for the same reason.
            --}}
            <p class="mt-2 text-base text-ink-2">
                Someone else sets this. Changing it decides whether a customer's call
                reaches your phone, so only an owner or a manager can move it — the
                setting below is what you have now.
            </p>
            <p class="mt-3 text-base font-medium text-ink">{{ $current->label() }}</p>
            <p class="mt-1 text-base text-ink-2">{{ $current->description() }}</p>
        @endif
    </div>

    {{--
        Owner ruling D-6 (2026-10-05): who picks up first — the AI receptionist straight away (the default) or the owner's
        own phone first. ⚠️ Nothing here claims the receptionist is answering: while the platform switch is off the panel
        says so, and the ring-me-first option says plainly that it takes a message until ringing the owner is built.
    --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Who picks up first</h2>

        @unless ($receptionistLive)
            <p class="mt-2 text-base text-ink-2" data-testid="receptionist-not-live">
                Your AI receptionist is not answering calls yet — we are still switching it on. What you choose here is
                kept for when it is.
            </p>
        @endunless

        @if ($mayChoose)
            <form wire:submit="saveLiveAnswer" class="mt-4 space-y-5">
                <fieldset>
                    <legend class="text-lg font-medium text-ink">When a call comes in</legend>

                    <div class="mt-3 space-y-3">
                        @foreach ($liveAnswerModes as $option)
                            <label class="flex gap-3 rounded-[--radius-card] border border-rule p-4">
                                <input
                                    type="radio"
                                    wire:model="liveAnswer"
                                    value="{{ $option->value }}"
                                    class="mt-1"
                                >
                                <span>
                                    <span class="block text-base font-medium text-ink">{{ $option->label() }}</span>
                                    <span class="block text-base text-ink-2">{{ $option->description() }}</span>
                                    @if ($option === \App\Enums\LiveAnswerMode::OwnerFirst)
                                        <span class="block text-base text-ink-2" data-testid="owner-first-not-ready">
                                            Not ready yet: until it is, we take a message instead of answering.
                                        </span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>

                    @error('liveAnswer') <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p> @enderror
                </fieldset>

                <x-ui.submit target="saveLiveAnswer" busy="Saving…">Save</x-ui.submit>
            </form>
        @else
            <p class="mt-2 text-base text-ink-2">
                Someone else sets this. Only an owner or a manager can change who picks up first — this is what you have
                now.
            </p>
            <p class="mt-3 text-base font-medium text-ink">{{ $currentLiveAnswer->label() }}</p>
            <p class="mt-1 text-base text-ink-2">{{ $currentLiveAnswer->description() }}</p>
        @endif
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Sending us the calls you miss</h2>

        @if ($codes === [])
            {{--
                No action, and that is the honest version rather than a missing
                one: the number arrives with the account and there is no screen
                anybody can press to make it arrive sooner. An invitation here
                would be a button that says "wait".
            --}}
            <x-ui.empty-state class="mt-3" icon="☎">
                These appear once you have a number. We are getting one — nothing for you
                to do.
            </x-ui.empty-state>
        @else
            <p class="mt-2 text-base text-ink-2">
                Tap one on the phone you want to forward, or dial it by hand. The first
                one sends us a call nobody picks up after {{ $ringSeconds }} seconds; the
                second sends us a call that arrives while you are on the line. The last
                two undo them.
            </p>

            <ul class="mt-4 space-y-3">
                @foreach ($codes as $entry)
                    <li class="flex flex-col gap-1 border-b border-rule pb-3 last:border-0 last:pb-0">
                        <span class="text-base text-ink">{{ $entry['label'] }}</span>
                        <a
                            href="{{ $entry['tel'] }}"
                            class="min-h-11 font-mono text-base text-ink underline focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                        >{{ $entry['code'] }}</a>
                    </li>
                @endforeach
            </ul>

            {{--
                `26` §3.2's own caveat, not a softened one: "Verify per carrier —
                behaviour varies, and some carriers and most VoIP/PBX systems need
                a settings change instead."
            --}}
            <p class="mt-4 text-base text-ink-2">
                These are the standard mobile codes. Some networks use different ones,
                and most office phone systems need a setting changed instead of a code
                dialled — if one of these does nothing, ask us and we will do it with
                you.
            </p>
        @endif

        {{--
            ⚠️ OUTSIDE THE `@if`, AND THAT IS THE POINT. This is the sentence that
            keeps the page from claiming something it cannot know, so it has to be
            true for a tenant with a number and for one without. Inside the branch
            it would disappear on exactly the account that has set nothing up yet.
        --}}
        <p class="mt-4 text-base text-ink-2">
            We cannot see your carrier's settings, so we cannot tell you from here
            whether a forward is on. Once you have set one, ring your own number from
            another phone, let it go unanswered, and see whether the message reaches
            you.
        </p>
    </div>

    {{--
        T176 P2 — the calls themselves.

        ⛔ THE EXTERNAL GATE IS SHOWN, NOT HIDDEN. Until Infobip activates
        Voice/Calls on the account (T176 §7 item 3) nothing above this line
        answers a call, and a page that teaches somebody to forward their phone
        while quietly not picking it up is the worst version of this feature.
        The panel gives them nothing to press because there is nothing they can
        do — which is the honest shape, and the same one the forwarding caveat
        above already takes.

        ⛔ THE RECORDING ANNOUNCEMENT IS NO LONGER PRINTED AS A FACT, AND THAT
        WAS THE FINDING (4506). This paragraph read "every call we pick up is
        recorded, and every caller is told so before they can speak"
        unconditionally, two lines below another that says we are not answering
        calls at all — and nothing in this application enforced the announcement,
        which is configured on the vendor's number setup by hand. An operator who
        skipped that step produced a California §632 violation **the tenant's own
        screen had told them could not happen**, and the penalty lands on the
        tenant. It is now conditioned on `RecordingAnnouncement::isAttested()`,
        which is the same fact `IngestVoiceEventJob` refuses on.

        ⚠️ THE WORDS STAY VISIBLE IN BOTH BRANCHES, in the future tense when
        nothing is being recorded yet. An owner is entitled to know what their
        customers will hear — some of them will be asked — and hiding the
        sentence until activation would mean the first person to read it is a
        caller. They come from `VoiceGreeting::ANNOUNCEMENT`, never from a second
        copy here.

        ⚠️ A CALLER'S NUMBER APPEARS ON THIS PAGE AND NOWHERE ELSE. It is behind
        their login, under the tenant boundary, with row-level security beneath —
        which is exactly why the owner's notification email carries no number at
        all.

        COLOUR IS NEVER THE SIGNAL (`22`): an outcome is a word, never a hue.
        WORKS AT 320px — each call is a stacked block rather than a table row.
    --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Calls that came to us</h2>

        @unless ($answeringLive)
            <p class="mt-2 text-base text-ink-2" data-testid="voice-not-live">
                We are not answering calls yet — the phone side of your account is still
                being switched on with the carrier. Your own line is untouched and rings
                exactly as it does today. Nothing for you to do; we will tell you when it
                is live.
            </p>
        @endunless

        @if ($recordingLive)
            <p class="mt-2 text-base text-ink-2" data-testid="recording-announcement">
                Every call we pick up is recorded, and every caller is told so before they
                can speak. They hear: “{{ $announcement }}”
            </p>
        @else
            <p class="mt-2 text-base text-ink-2" data-testid="recording-not-live">
                We are not recording anything yet. When we start, every caller will hear this
                before they can speak: “{{ $announcement }}”
            </p>
        @endif

        @if ($calls->isEmpty())
            <x-ui.empty-state class="mt-4" icon="☎">
                Nothing yet. Calls you miss will show up here with the caller's number and
                any message they left.
            </x-ui.empty-state>
        @else
            <ul class="mt-4 space-y-4">
                @foreach ($calls as $call)
                    <li
                        class="flex flex-col gap-1 border-b border-rule pb-4 last:border-0 last:pb-0"
                        data-testid="call-row"
                    >
                        <span class="font-mono text-base text-ink">{{ $call->from_e164 }}</span>

                        <span class="text-base text-ink-2">
                            {{ $call->outcome->label() }}@if ($call->started_at) · {{ $call->started_at->diffForHumans() }}@endif
                        </span>

                        {{--
                            The message the caller left with the AI receptionist (wave 3c). Labelled as what the
                            receptionist heard, for the transcript's reason below: it is a machine's hearing of a phone line.
                        --}}
                        @if ($call->message_text)
                            <span class="text-base text-ink-2">
                                Your AI receptionist took this message, written as it heard it:
                            </span>
                            @if ($call->message_name)
                                <span class="text-base text-ink-2">From {{ $call->message_name }}</span>
                            @endif
                            @if ($call->message_callback)
                                <span class="text-base text-ink-2">Ring back on {{ $call->message_callback }}</span>
                            @endif
                            <span class="text-base text-ink" data-testid="call-message">{{ $call->message_text }}</span>
                        @endif

                        {{--
                            What was said, when the AI receptionist answered. Folded, because twenty calls of conversation
                            would bury the list an owner opened to see who rang; labelled as a machine's hearing of a phone
                            line, for the transcript's reason below.
                        --}}
                        @if (! empty($turns[$call->id]))
                            <details class="mt-1" data-testid="call-turns">
                                <summary class="text-base text-ink-2">What was said, as your AI receptionist heard it</summary>
                                <ul class="mt-1 space-y-1">
                                    @foreach ($turns[$call->id] as $turn)
                                        @if ($turn['caller'] !== '')
                                            <li class="text-base text-ink"><span class="text-ink-2">Caller:</span> {{ $turn['caller'] }}</li>
                                        @endif
                                        @if ($turn['agent'] !== '')
                                            <li class="text-base text-ink"><span class="text-ink-2">Receptionist:</span> {{ $turn['agent'] }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            </details>
                        @endif

                        @if ($call->voicemail)
                            <span class="text-base text-ink-2">
                                {{ $call->voicemail->audio_state->label() }}@if ($call->voicemail->recording_seconds) · {{ $call->voicemail->recording_seconds }}s @endif
                            </span>

                            {{--
                                ⛔ THE PLAYER THE MAIL PROMISED (4519). The owner
                                notification said "the recording is on your calls
                                page" and there was nothing on this page that
                                could play one — no route and no reader — so every
                                recording fetched was write-only personal data
                                and the mail named a place that did not exist.

                                ⚠️ NOT A SIGNED URL. `InboundMediaController`'s
                                argument, one channel over: this is opened from a
                                screen by somebody signed in to the business the
                                voicemail belongs to, and a signature would be
                                the weaker control because it keeps working after
                                they leave.
                            --}}
                            @if ($call->voicemail->isPlayable())
                                <audio
                                    controls
                                    preload="none"
                                    class="mt-1 w-full"
                                    data-testid="voicemail-player"
                                    src="{{ route('account.voicemail.recording', $call->voicemail) }}"
                                >
                                    <a href="{{ route('account.voicemail.recording', $call->voicemail) }}">
                                        Listen to this message
                                    </a>
                                </audio>
                            @endif

                            @if ($call->voicemail->transcript)
                                {{--
                                    ⚠️ LABELLED AS A MACHINE READING, NOT AS A
                                    RECORD OF WHAT WAS SAID. `AssistantThreadClosed`
                                    makes the same distinction for the same reason:
                                    an owner who acts on a mis-transcribed sentence
                                    acted on something nobody said. The recording is
                                    the record.
                                --}}
                                <span class="text-base text-ink-2">
                                    Written up automatically, so it may be wrong — the recording is what
                                    they actually said:
                                </span>
                                <span class="text-base text-ink" data-testid="voicemail-transcript">{{ $call->voicemail->transcript }}</span>
                            @else
                                <span class="text-base text-ink-2">{{ $call->voicemail->transcript_state->label() }}</span>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

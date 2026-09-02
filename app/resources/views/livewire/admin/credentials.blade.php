{{--
    Ops → Platform → Credentials — the Credentials Manager (`38` Part 1).

    ⚠️ NO CREDENTIAL VALUE IS RENDERED HERE, EVER, AND THERE IS NO CODE PATH
    THAT COULD. The board comes from CredentialStore::board(), which returns
    four stored characters and a handful of booleans. `38`: "paste, never
    displayed again".

    ⛔ THE SECOND HALF OF THAT SENTENCE READ "the encrypted column is never
    opened by anything this screen touches" AND IT IS NO LONGER TRUE —
    CORRECTED 2026-08-25 (9442). board() now opens every stored ciphertext, in
    order to answer whether it CAN be opened, and discards the result. The old
    property was real and it cost the incident: a row this install can no
    longer read was reported here as "Managed here", with no degradation
    sentence and a Rotate button, on the one screen an operator opens during an
    APP_KEY rotation. What survives unchanged is the half that matters — no
    value, and no fragment of one, reaches this template.

    COLOUR IS NOT THE SIGNAL (`22`). "Managed here", "From the environment
    file" and "Not configured" are words. The test-account badge is a word too,
    because a colour alone cannot tell an operator which of two working keys is
    pointing at a sandbox. So is the unreadable-row panel below: it is a
    paragraph, not a red dot.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Credentials</h1>
        <p class="mt-1 text-base text-ink-2">
            The keys this platform uses to reach its vendors. Paste one to
            set it; it is stored encrypted and is never shown again. Each
            entry says what stops working while it is missing.
        </p>
    </div>

    {{--
        ⛔ WHETHER THE PLATFORM IS STILL SENDING — 9449(c), and it is not a
        credentials fact at all.

        An `APP_KEY` rotation does two unrelated things and only one of them is
        on the board below. Every ciphertext in `platform_credentials` becomes
        unopenable — that is the rows, and a paste fixes each one. And every
        suppression this platform stores becomes unmatchable, because those are
        keyed hashes rather than encrypted values, so `ConsentService::decide()`
        refuses **every** send on every channel for every tenant until it is
        resolved. **No paste on this page reaches the second one.**

        IT IS FIRST ON THE PAGE AND NOT LAST. 9448 found the same panel written
        four paragraphs below the thing it explained and recorded why that was
        wrong: a person mid-incident does not scroll before concluding. This one
        outranks every row below it — the rows are keys, and this is whether the
        product is running.

        IT IS PAGE-LEVEL AND NOT PER-ROW, FOR TWO REASONS. It is one fact about
        the install rather than a fact about a key, so per-row it would print
        once per unreadable row; and the `unattributed` state arrives with **no
        credential fault at all** — an install that upgraded with suppressions
        already stored has every ciphertext readable and every send refused — so
        a per-row panel would be silent in one of the two states it exists for.

        IT RENDERS IN ALL FOUR STATES, WHICH IS DELIBERATE. A panel that appears
        only during an incident is one nobody has ever seen, on the day they
        most need it to work. Rendering the readable arm means every operator
        who opens this board exercises the check.
    --}}
    <x-admin.suppression-readability :registers="$registers">
        @unless ($registers->isReadable())
            {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
            <span>Nothing on the board below fixes this, and pasting a key again does not — a suppression is a keyed hash and not an encrypted value, so there is no ciphertext here to re-write.</span>
        @endunless
    </x-admin.suppression-readability>

    {{--
        empty-state: absent because the board is the credential manifest
        rendered, not a query — `CredentialBoard` walks a fixed list of
        vendor keys, so an empty branch here is dead code, and dead code
        that satisfies a lint is worse than the gap it closes.
    --}}
    @foreach ($board as $vendor => $rows)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">{{ $vendor }}</h2>

            <ul class="mt-4 space-y-5 max-h-[600px] overflow-y-auto pr-2">
                @foreach ($rows as $row)
                    <li wire:key="credential-{{ $row['key'] }}" class="border-t border-rule pt-5 first:border-0 first:pt-0">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <p class="font-display text-base font-semibold text-ink">{{ $row['label'] }}</p>
                            <p class="font-mono text-sm text-ink-3">{{ $row['key'] }}</p>
                        </div>

                        <p class="mt-1 text-base text-ink-2">{{ $row['description'] }}</p>

                        {{--
                            The three states, in words. `38`: the board
                            "shows exactly which key is absent — never a
                            stack trace, never a silent stall".
                        --}}
                        <div class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <span class="text-base text-ink">{{ $row['source']->label() }}</span>

                            @if ($row['lastFour'] !== null)
                                <span class="font-mono text-sm text-ink-2">••••{{ $row['lastFour'] }}</span>
                            @endif

                            @if ($row['environment'] !== null)
                                <span class="text-sm text-ink-2">{{ $row['environment']->label() }}</span>
                            @endif
                        </div>

                        {{--
                            A STORED ROW THIS SERVER CANNOT OPEN — 9440, 9443.
                            The state the board reported as "Managed here" until
                            2026-08-25, which is the single most reassuring thing
                            it could have said in the one incident that produces
                            it.

                            THE ORDER OF THE TWO REMEDIES IS THE LOAD-BEARING
                            PART AND IT IS NOT THE OBVIOUS ONE. Pasting the key
                            works — set() writes through the current APP_KEY and
                            the row becomes readable again — and it is the WRONG
                            first move, because it re-encrypts under the new key
                            and takes away the restore that would have fixed
                            every other key at once. The old key first; the paste
                            only when the old key is genuinely gone. That is the
                            same ordering `.claude/skills/deploying/` gives for
                            the suppression registers, for the same reason.

                            AND IT DOES NOT ENUMERATE WHAT ELSE IS BROKEN (8861).
                            A list of the other tables a rotation reaches would
                            be a copy of somebody else's rule, stale the first
                            time that rule moved, on a screen an operator trusts
                            during an incident. It names the command whose output
                            is the authority instead.

                            AND THE DEGRADATION SENTENCE IS NOT REPEATED. The
                            "While this is unset:" line renders
                            $row['degradation'] immediately below this panel, and
                            a first draft of this arm rendered it a second time
                            inside it — one manifest sentence, printed twice, in
                            a panel about a screen saying the wrong thing.

                            AND IT SITS HERE RATHER THAN AFTER THE DEGRADATION
                            LINE, WHICH IS WHERE IT WAS WRITTEN AND WAS WRONG.
                            Read as an operator scanning the board, the source
                            line renders "Not configured" beside a last-four and
                            a Live-account badge, which is a contradiction until
                            this panel resolves it. Four paragraphs of separation
                            is a scroll, and a person mid-incident does not
                            scroll before concluding.
                        --}}
                        @if ($row['unreadable'])
                            <div class="mt-3 rounded-[--radius-panel] border border-rule bg-paper p-4">
                                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                                <p class="text-base text-ink"><span class="font-semibold">A key is stored here and this server cannot read it.</span></p>

                                <p class="mt-2 text-base text-ink-2">
                                    @if ($row['source']->isConfigured())
                                        {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                                        <span>The platform is running on the copy in this server's environment file instead, which may not be the key you last set here.</span>
                                    @else
                                        {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                                        <span>Nothing is answering for this key at all.</span>
                                    @endif
                                </p>

                                <p class="mt-2 text-base text-ink-2">
                                    {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                                    <span>This is what a changed APP_KEY does, and it does it to every stored credential at once — so if other keys here say the same thing, one key changed rather than these.</span>
                                </p>

                                <p class="mt-2 text-base text-ink-2">
                                    {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                                    <span>Put the previous APP_KEY back in the environment file first, then run</span>
                                    <span class="font-mono text-sm text-ink">php artisan config:clear</span>.
                                    Pasting the key again also works and is the
                                    second choice, not the first: it re-encrypts
                                    under the key this server has now, and the
                                    old key then no longer restores it.
                                </p>

                                {{--
                                    ⛔ THE SECOND HALF OF THIS PARAGRAPH READ
                                    "and whether the platform is refusing to
                                    send" AND WAS REMOVED ON 2026-08-25 (9642).
                                    It was true — the command does report it —
                                    and it sent an operator to a terminal for
                                    an answer that is now the first thing on
                                    this page. A pointer at a slower authority
                                    for a question already answered above is
                                    9445's shape: a true sentence that has
                                    become an instruction pointing the wrong
                                    way.

                                    WHAT SURVIVES IS THE HALF 8861 IS ABOUT.
                                    The command stays named as the authority
                                    for what ELSE a rotation reaches, because
                                    an inventory written on this screen goes
                                    stale the first time that list moves.
                                --}}
                                <p class="mt-2 text-base text-ink-2">
                                    {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                                    <span>More than credentials is stored under that key, and what is at the top of this page is not all of it. Run</span>
                                    <span class="font-mono text-sm text-ink">php artisan consent:hash-epoch</span>
                                    before deciding anything — it is the
                                    authority for what else this server can no
                                    longer read.
                                </p>
                            </div>
                        @endif

                        @if (! $row['source']->isConfigured())
                            <p class="mt-2 text-base text-ink-2">
                                While this is unset: {{ $row['degradation'] }}
                            </p>
                        @endif

                        @if ($row['rotatedAt'] !== null)
                            <p class="mt-2 text-sm tabular-nums text-ink-3">
                                Set {{ $row['rotatedAt']->format('j M Y H:i') }} by {{ $row['rotatedBy'] }} ·
                                @if ($row['lastUsedAt'] === null)
                                    not used yet
                                @else
                                    last used {{ $row['lastUsedAt']->format('j M Y') }}
                                @endif
                            </p>
                        @endif

                        @if ($rotating === $row['key'])
                            {{--
                                The confirmation comes BEFORE the paste, and
                                the field lives inside it. See the component:
                                a value pasted before a confirmation step
                                would have to survive a round trip, and a
                                Livewire public property round-trips through
                                the page's own snapshot.
                            --}}
                            <div class="mt-4 rounded-[--radius-panel] border border-rule bg-paper p-4">
                                <p class="text-base text-ink">
                                    This replaces the key in use. Anything running
                                    with the old one starts using the new one
                                    immediately, and the old value is not kept.
                                </p>

                                <div class="mt-3 flex flex-wrap items-end gap-3">
                                    <label class="block">
                                        <span class="text-sm text-ink-2">Paste the key</span>
                                        <input
                                            wire:model="draft"
                                            type="password"
                                            autocomplete="off"
                                            spellcheck="false"
                                            class="mt-1 w-80 max-w-full rounded-[--radius-field] border border-rule bg-card px-3 py-2 font-mono text-ink"
                                        >
                                    </label>

                                    <label class="block">
                                        <span class="text-sm text-ink-2">This key opens</span>
                                        <select
                                            wire:model="environment"
                                            class="mt-1 rounded-[--radius-field] border border-rule bg-card px-3 py-2 text-ink"
                                        >
                                            @foreach ($environments as $environment)
                                                <option value="{{ $environment->value }}">{{ $environment->label() }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <button wire:click="save" type="button" class="min-h-11 rounded-[--radius-field] bg-ink px-4 py-2 text-paper">
                                        Save this key
                                    </button>

                                    <button wire:click="cancel" type="button" class="min-h-11 rounded-[--radius-field] border border-rule px-4 py-2 text-ink">
                                        Cancel
                                    </button>
                                </div>
                            </div>

                            @if ($history !== [])
                                <ul class="mt-4 space-y-1 text-sm text-ink-2">
                                    @foreach ($history as $entry)
                                        <li wire:key="credential-change-{{ $entry->id }}" class="tabular-nums">
                                            {{ $entry->created_at->format('j M Y H:i') }} ·
                                            {{ $entry->action->label() }} ·
                                            {{ $entry->actor }}
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        @elseif ($clearing === $row['key'])
                            <div class="mt-4 rounded-[--radius-panel] border border-rule bg-paper p-4">
                                <p class="text-base text-ink">
                                    Clearing removes the stored key. {{ $row['degradation'] }}
                                </p>

                                <p class="mt-2 text-base text-ink-2">
                                    If the same key is also in the environment file,
                                    that copy keeps working — clearing here does not
                                    stop it being used.
                                </p>

                                <div class="mt-3 flex flex-wrap gap-3">
                                    <button wire:click="clear" type="button" class="min-h-11 rounded-[--radius-field] bg-ink px-4 py-2 text-paper">
                                        Clear this key
                                    </button>

                                    <button wire:click="cancel" type="button" class="min-h-11 rounded-[--radius-field] border border-rule px-4 py-2 text-ink">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        @else
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <button
                                    wire:click="confirmRotate('{{ $row['key'] }}')"
                                    type="button"
                                    class="min-h-11 rounded-[--radius-field] border border-rule px-4 py-2 text-ink"
                                >
                                    {{ $row['source'] === \App\Services\Config\CredentialSource::Store ? 'Rotate' : 'Set' }}
                                </button>

                                @if ($row['source'] === \App\Services\Config\CredentialSource::Store)
                                    <button
                                        wire:click="confirmClear('{{ $row['key'] }}')"
                                        type="button"
                                        class="min-h-11 rounded-[--radius-field] border border-rule px-4 py-2 text-ink"
                                    >
                                        Clear
                                    </button>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    {{--
        ────────────  What this board does not list  ────────────

        9374's OWED COMPANION LINE, AND THE QUESTION IT ANSWERS IS THIS
        SCREEN'S RATHER THAN THE MAIL SCREEN'S (9444). Wave 27 put the finding
        on Admin\MailSending, argued: the operator holding it is asking "why is
        no mail going out". The operator standing HERE is asking a different
        question — "is this the list?" — and reads twenty rows as the answer.
        MAIL_USERNAME and MAIL_PASSWORD are read by config/mail.php straight
        from the environment, so on an SMTP transport the credential that
        decides whether ANY email leaves, a sign-in link included, is not on
        this board and cannot be put on it.

        DERIVED, NOT RE-TYPED. CredentialManifest::mailerCredential() is the
        same call the mail screen makes; a second reading of the same config
        keys here would be 8460's shape, two copies agreeing until one moved.
        The SENTENCES differ because the question does — that is a companion,
        not a copy.

        THREE ARMS, BECAUSE A TRANSPORT THAT SIGNS IN WITH NOTHING IS NOT ONE
        THAT SIGNS IN WITH SOMETHING WE CANNOT SEE, AND ONE WHOSE CREDENTIAL
        THIS BOARD DOES HOLD IS NEITHER. Telling an operator a key is missing
        from a list it is on is the wasted hour with the sign reversed.
    --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What this board does not list</h2>

        @if ($mailerCredential['key'] !== null)
            <p class="mt-1 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span>Everything this platform signs in with is above, including the account it sends email from — that one is</span>
                <span class="font-mono text-sm text-ink">{{ $mailerCredential['key'] }}</span>.
            </p>
        @elseif ($mailerCredential['authenticates'])
            <p class="mt-1 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span class="font-semibold text-ink">The credential this platform sends email with is not on this board and cannot be put on it.</span>
                The transport signs in with a username and password read from
                this server's environment file before any of this application
                runs. Nothing here can show them, test them, or tell you they
                have stopped working — and while they are wrong, no email
                leaves at all, including the link somebody signs in with.
            </p>

            <p class="mt-3 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span>They are</span>
                <span class="font-mono text-sm text-ink">MAIL_USERNAME</span> and
                <span class="font-mono text-sm text-ink">MAIL_PASSWORD</span>,
                changed on the server and followed by
                <span class="font-mono text-sm text-ink">composer deploy</span>.
                <a href="{{ route('admin.mail-sending') }}" class="font-semibold text-ink underline">Email sending</a>
                is the screen that says what the transport is doing with them.
            </p>
        @else
            <p class="mt-1 text-base text-ink-2">
                {{-- THE PHRASE STAYS ON ONE SOURCE LINE. --}}
                <span>Everything this platform signs in with is above. The transport it sends email through presents no credentials at all, so there is nothing missing from this list.</span>
            </p>
        @endif
    </section>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Checking a key works</h2>

        <p class="mt-1 text-base text-ink-2">
            There is no test button here yet. Every check would call the
            vendor for real — a lookup that costs money, or tokens against a
            monthly ceiling — and a check written from memory of an
            interface can report success against the wrong endpoint. Until
            each vendor's own probe is built, this board reports what it can
            prove: whether a key is here, where it came from, and what stops
            working without it.
        </p>
    </section>
</div>

{{--
    What this platform texted one business's own account holder, and what they
    said back — wave 41 lane E, decision 11110.

    THE THREE SENTENCES THIS PAGE MUST NOT LET A READER INVENT, all of them
    rendered rather than assumed:

      1. A row is a SEND and never a DELIVERY (10823, 9371). The row is written
         after the carrier accepts the message, and nothing in this application
         reads an owner-channel delivery receipt.
      2. The link between a reply and a send is an INFERENCE FROM RECENCY
         (10829). No message we send them carries a reply token or a numbered
         option — 10832 refuses that grammar — so timing is the only signal.
      3. NOTHING ACTS ON A REPLY (10722). If it needs an answer, a person has
         to write one.

    THE SCREEN OFFERS NO ACTION. Both tables are append-only in the model, and
    the one control that would matter — texting them back — belongs to a sender
    holding a permit, not to a page.

    COLOUR IS NOT THE SIGNAL (`22`). Every row says "We sent" or "They replied"
    in words; the tint is decoration and the label is the information.

    THIS PAGE SHOWS AN ACCOUNT HOLDER'S OWN WORDS, PLAINTEXT, BEHIND THE
    PLATFORM-STAFF GATE ONLY. Never in a toast, never in a URL, never in a log.

    LISTS ARE JOINED IN PHP, NEVER WITH AN INLINE @foreach INSIDE A SENTENCE —
    that renders "…and this ." with a space before the stop, measured in a real
    browser and invisible to every assertion in this suite.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Texts with an account holder</h1>

        <p class="mt-1 text-base text-ink-2">
            Every text {{ config('app.name') }} has sent a business's own
            account holder about their account, and every text they sent back.
        </p>

        <p class="mt-2 text-base text-ink-2">
            You need the business number. If all you have is a phone number,
            start at
            <a class="underline text-ink" href="{{ route('admin.number-lookup') }}">What we know about a number</a>,
            which finds the business — or businesses — from the number itself.
        </p>
    </div>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Find a business</h2>

        <p class="mt-1 text-base text-ink-2">
            By its number. There is no list — see the note at the foot of this
            page.
        </p>

        <form wire:submit="lookUp" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block" for="owner-channel-texts-lookup">
                <span class="text-sm text-ink-2">Business number</span>
                <input
                    wire:model="lookup"
                    id="owner-channel-texts-lookup"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 w-40 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
            </label>

            <x-ui.submit size="default" target="lookUp" busy="Looking…">Show what was said</x-ui.submit>
        </form>
    </section>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What this page cannot tell you</h2>

        <ul class="mt-2 space-y-2 text-base text-ink-2">
            <li>
                <span class="text-ink">A message here was accepted by a carrier. That is not proof a phone received it.</span>
                Nothing in {{ config('app.name') }} reads a delivery receipt for
                these texts, so a row means the message left and nothing more.
            </li>
            <li>
                <span class="text-ink">A reply is matched to a message by timing alone.</span>
                Within {{ $windowHours }} hours, the most recent thing we texted
                them is recorded as what their reply appears to answer. It is our
                guess, never a claim about what they meant — no message we send
                them offers a numbered option or a reply code.
            </li>
            <li>
                <span class="text-ink">Nothing reads these replies.</span>
                No work restarts and no automation resumes because somebody
                answered. If a reply needs an answer, a person has to write one.
            </li>
            <li>
                <span class="text-ink">What we sent is described, not quoted.</span>
                The record keeps what each text was about; it does not keep the
                sentence that was sent.
            </li>
        </ul>
    </section>

    @if ($business)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">{{ $business->name }}</h2>

            <p class="mt-1 text-base text-ink-2">Business {{ $business->id }}</p>

            @if ($truncated)
                <p class="mt-3 text-base text-ink">
                    Showing the {{ $limit }} most recent of each. There are older
                    ones this page does not reach.
                </p>
            @endif

            @forelse ($timeline as $row)
                <article
                    wire:key="{{ $row['key'] }}"
                    class="mt-4 border-t border-rule pt-4 first:border-0 first:mt-3"
                >
                    <p class="text-sm text-ink-2">
                        {{-- Colour is never the signal: this word is the signal. --}}
                        {{ $row['direction'] === 'sent' ? 'We sent' : 'They replied' }}
                        @if ($row['when'])
                            — {{ $row['when'] }}
                        @else
                            — time not recorded
                        @endif
                    </p>

                    @if ($row['headline'])
                        <h3 class="mt-1 font-display text-base font-semibold text-ink">{{ $row['headline'] }}</h3>
                    @endif

                    @if ($row['body'])
                        <blockquote class="mt-2 border-l-2 border-rule pl-3 text-base text-ink">
                            {{ $row['body'] }}
                        </blockquote>
                    @endif

                    @if ($row['detail'])
                        <p class="mt-2 text-base text-ink-2">{{ $row['detail'] }}</p>
                    @endif

                    @if ($row['reference'])
                        {{--
                            LABELLED, BECAUSE AN UNLABELLED STRING IS NOISE.
                            Read as rendered text, this was a bare
                            "mt-carrier-77" under a sentence about an event,
                            and nothing on the page said what it was for.
                        --}}
                        <p class="mt-1 break-all text-sm text-ink-3">
                            The carrier's own reference for the message:
                            <span class="font-mono">{{ $row['reference'] }}</span>
                        </p>
                    @endif
                </article>
            @empty
                <x-ui.empty-state class="mt-4" heading="Nothing has been said either way">
                    This platform has never texted this business's account
                    holder, and they have never texted us. Whether they have
                    agreed to be texted at all is on
                    <a class="underline text-ink" href="{{ route('admin.owner-notify-consents') }}">Owner-channel consent</a>.
                </x-ui.empty-state>
            @endforelse
        </section>
    @endif

    {{--
        NO BACKTICKS IN RENDERED PROSE. A Markdown-quoted table name in a
        paragraph renders as a literal backtick — read as text, it is noise in
        the one sentence explaining why the page has no list. Said here without
        the table name at all.

        BOTH FILES THIS NAMED WERE WRONG AND BOTH HALVES ARE NOW FALSE — 11455.
        It named `owner-notify-consents.blade.php`, which was repaired in wave
        43, and `number-lookup.blade.php`, which never carried the defect at all
        (all six of its backticks are inside Blade comments). Decision 11119 had
        the same wrong membership; 11396 corrects it. The three that really
        carried it were phi-tenants, terms-acceptances and owner-notify-consents,
        derived comment-stripped rather than by grep.
    --}}
    <p class="text-sm text-ink-3">
        There is no list of businesses on this page because the database will
        not produce one: it admits a reader only as a single business or as that
        business's owner. How long these records are kept is a period an
        operator sets; until it is set, nothing here is ever deleted.
    </p>
</div>

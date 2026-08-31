{{--
    What one business's own account holder agreed to, and the proof of it
    (wave 39 lane A, decision 10660).

    Deliberately plain, like the signup-agreements and health-information
    screens. It is the read path for `owner_notification_consents`, which had
    a writer since wave 38 and no way at all to get an answer out of it.

    THE SCREEN OFFERS NO ACTION AND THAT IS THE DESIGN. A consent record is
    append-only in the model and in the schema. An owner corrects their own
    number from their own account settings screen; there is nothing here to
    press.

    COLOUR IS NOT THE SIGNAL (`22`). Every state here is a word.

    ⚠️ THIS PAGE SHOWS A MOBILE NUMBER, PLAINTEXT, BEHIND THE PLATFORM-STAFF
    GATE ONLY. It is never in a toast, a URL, or a log. The address on the
    consent proof is a hash and never a raw IP (`29` §2 rule 21) — the record
    cannot hold one, so this page cannot render one.

    ⚠️ THE WORDING IS RENDERED FROM THE STORED BLOB, NEVER TYPED HERE — the
    words on this page must be the words that were shown, and a copy is a
    copy that drifts.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Owner-channel consent</h1>
        <p class="mt-1 text-base text-ink-2">
            Whether — and at what number — a business's own account holder has
            agreed to be texted about their account, and every disclosure they
            were ever shown.
        </p>

        {{--
            ⛔ THIS PARAGRAPH SAID "THIS IS THE ANSWER TO A CARRIER'S QUESTION:
            THIS NUMBER NEVER AGREED TO BE TEXTED" AND THE BOX BELOW TAKES A
            BUSINESS NUMBER (10882). A carrier gives you a phone number and
            nothing else, and typing one here answered "Enter a business
            number."
        --}}
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
            By its number. There is no list — see the note at the foot of
            this page.
        </p>

        <form wire:submit="lookUp" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block" for="owner-notify-lookup">
                <span class="text-sm text-ink-2">Business number</span>
                <input
                    wire:model="lookup"
                    id="owner-notify-lookup"
                    type="text"
                    inputmode="numeric"
                    class="mt-1 w-40 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
            </label>

            <x-ui.submit size="default" target="lookUp" busy="Looking…">Show what they agreed to</x-ui.submit>
        </form>
    </section>

    @if ($business)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">{{ $business->name }}</h2>

            <p class="mt-1 text-base text-ink-2">Business {{ $business->id }}</p>

            <h3 class="mt-5 font-display text-base font-semibold text-ink">Where it stands today</h3>

            @if ($number)
                <p class="mt-1 text-base text-ink-2">
                    Number on file: <span class="font-mono text-ink">{{ $number->e164 }}</span>.
                    @if ($number->stopped_at)
                        <span class="text-ink">Stopped {{ $number->stopped_at->diffForHumans() }}.</span> Not
                        currently sendable.
                    @else
                        <span class="text-ink">Live.</span> This is the number
                        {{ config('app.name') }} would text.
                    @endif
                </p>
            @else
                {{--
                    ⚠️ THE ABSENCE IS THE FINDING, SO IT IS STATED RATHER
                    THAN LEFT BLANK — `TermsAcceptances`' own rule.
                --}}
                <p class="mt-1 text-base text-ink-2">
                    No owner-notify number on file. This business has never
                    consented, or the current row was removed.
                </p>
            @endif

            <h3 class="mt-5 font-display text-base font-semibold text-ink">Every disclosure on record</h3>

            <p class="mt-1 text-base text-ink-2">
                Newest first. A corrected number is a new row; nothing here is
                ever edited or removed.
            </p>

            @forelse ($history as $consent)
                <article wire:key="owner-consent-{{ $consent->id }}" class="mt-4 border-t border-rule pt-4 first:border-0">
                    <h4 class="font-display text-base font-semibold text-ink">
                        {{ $consent->e164 ?? 'Number not recorded on this row' }} — version {{ $consent->disclosure_version }}
                    </h4>

                    <dl class="mt-2 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-ink-2">Shown</dt>
                            <dd class="text-base text-ink">{{ $consent->created_at?->format('j F Y, H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm text-ink-2">By</dt>
                            <dd class="font-mono text-base text-ink">{{ $consent->accepted_by }}</dd>
                        </div>

                        @if ($consent->e164 === null)
                            <div class="sm:col-span-2">
                                <dt class="text-sm text-ink-2">Number</dt>
                                {{--
                                    ⚠️ A ROW FROM BEFORE 10660'S MIGRATION —
                                    see that migration's own docblock for
                                    why this cannot be recovered here.
                                --}}
                                <dd class="text-base text-ink">
                                    Not recorded — this disclosure was shown before this
                                    column existed, and the number it was for cannot be
                                    recovered from this row.
                                </dd>
                            </div>
                        @endif

                        <div>
                            <dt class="text-sm text-ink-2">Box state</dt>
                            <dd class="text-base text-ink">{{ $consent->proof['checkbox_state'] ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">On this page</dt>
                            <dd class="break-all font-mono text-base text-ink">{{ $consent->proof['url'] ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">Address, hashed</dt>
                            <dd class="break-all font-mono text-base text-ink">{{ $consent->proof['ip_hash'] ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">Browser</dt>
                            <dd class="break-all font-mono text-base text-ink">{{ $consent->proof['user_agent'] ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">The words they were shown</dt>
                            <dd class="text-base text-ink">{{ $consent->proof['disclosure_text'] ?? '—' }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <x-ui.empty-state class="mt-4" heading="Nothing on record">
                    This business has no owner-channel consent recorded. Nobody
                    here can add one — a consent is made by the account holder
                    on their own account settings screen.
                </x-ui.empty-state>
            @endforelse
        </section>
    @endif

    {{--
        NO BACKTICKS IN RENDERED PROSE (11119, corrected at 11396). A
        Markdown-quoted table name prints as a literal backtick, and this
        paragraph is the one sentence explaining why the page has no list.
        `owner-channel-texts.blade.php` said it without the table name at all
        and that is the wording taken here.
    --}}
    <p class="text-sm text-ink-3">
        There is no list of businesses on this page because the database will
        not produce one: it admits a reader only as a single business or as that
        business's owner. Reading them all would mean widening that
        deliberately, and nobody has.
    </p>
</div>

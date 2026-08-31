{{--
    What one business agreed to at signup, and the proof of it (T176 P22, 3995).

    Deliberately plain, like the health-information screen and the legal
    documents index. It is the read path for `terms_acceptances`, which had a
    writer at both signup doors and no way at all to get an answer out of it —
    the near-mirror of decision 272: not a control nothing writes, but evidence
    nobody could produce.

    THE SCREEN OFFERS NO ACTION AND THAT IS THE DESIGN. An acceptance is
    append-only in the model and in the schema, so there is nothing here to
    press. The one thing a correction control could add is agreement nobody
    gave.

    THREE DOCUMENTS ARE ANSWERED SEPARATELY. Counsel versions the Terms of
    Service, the SMS & Communications Terms and the Privacy Policy
    independently, so one line saying "accepted" could only be honest about one
    of them. The standing panel names each, and a document with no record says
    so rather than rendering a blank where a version should be.

    COLOUR IS NOT THE SIGNAL (`22`). Every state here is a word.

    ⚠️ THE PROOF FIELDS ARE THE ACCOUNT HOLDER'S OWN DATA and appear only behind
    the platform-staff gate. They are never in a toast, a URL or a log. The
    address is a hash and never a raw IP (`29` §2 rule 21) — the record cannot
    hold one, so this page cannot render one.

    ⚠️ THE WORDING IS RENDERED FROM THE STORED BLOB, NEVER TYPED HERE. An
    architecture lint refuses the acceptance notice written out in a template,
    for the reason it exists: the words on this page must be the words that were
    stored, and a copy is a copy that drifts.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Signup agreements</h1>
        <p class="mt-1 text-base text-ink-2">
            Which version of each document a business agreed to when it
            opened its account, when, and what it was shown at the time.
            This is the answer to a carrier's question and to a subpoena.
        </p>
    </div>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Find a business</h2>

        <p class="mt-1 text-base text-ink-2">
            By its number. There is no list — see the note at the foot of
            this page.
        </p>

        <form wire:submit="lookUp" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block" for="terms-lookup">
                <span class="text-sm text-ink-2">Business number</span>
                <input
                    wire:model="lookup"
                    id="terms-lookup"
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

            <h3 class="mt-5 font-display text-base font-semibold text-ink">Where each document stands</h3>

            <p class="mt-1 text-base text-ink-2">
                Each is versioned separately by counsel, so each is answered
                separately here.
            </p>

            <ul class="mt-3 space-y-3">
                {{--
                    empty-state: absent because this list is
                    `SignupTerms::DOCUMENTS`, a constant of three — it cannot
                    come back empty, and an invitation branch would be dead
                    code. A document a business never accepted is a row in
                    this list saying so, which is the absence a reader is
                    actually looking for.
                --}}
                @foreach ($standing as $entry)
                    <li wire:key="standing-{{ $entry['type']->value }}" class="border-t border-rule pt-3 first:border-0 first:pt-0">
                        <p class="text-base font-medium text-ink">{{ $entry['type']->title() }}</p>

                        @if ($entry['acceptance'])
                            <p class="mt-1 text-base text-ink-2">
                                Accepted version <span class="font-medium text-ink">{{ $entry['acceptance']->version }}</span>
                                on {{ $entry['acceptance']->created_at?->format('j F Y, H:i') }}
                                by {{ $entry['acceptance']->accepted_by }}.
                            </p>
                        @else
                            {{--
                                ⚠️ THE ABSENCE IS THE FINDING, SO IT IS
                                STATED RATHER THAN LEFT BLANK. A business
                                provisioned before this record existed has no
                                row, and a page that rendered nothing here
                                would read as a page that had not loaded.
                            --}}
                            <p class="mt-1 text-base text-ink-2">
                                No record of this document being accepted. That is not
                                the same as a refusal — an account opened before this
                                record existed has none.
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Every acceptance on record</h2>

            <p class="mt-1 text-base text-ink-2">
                Newest first. A new version of a document is a new row; nothing
                here is ever edited or removed.
            </p>

            @forelse ($history as $acceptance)
                <article wire:key="acceptance-{{ $acceptance->id }}" class="mt-4 border-t border-rule pt-4 first:border-0">
                    <h3 class="font-display text-base font-semibold text-ink">
                        {{ $acceptance->doc_type->title() }} — version {{ $acceptance->version }}
                    </h3>

                    <dl class="mt-2 grid gap-x-6 gap-y-2 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-ink-2">Accepted</dt>
                            <dd class="text-base text-ink">{{ $acceptance->created_at?->format('j F Y, H:i') }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm text-ink-2">By</dt>
                            <dd class="font-mono text-base text-ink">{{ $acceptance->accepted_by }}</dd>
                        </div>

                        <div>
                            <dt class="text-sm text-ink-2">How</dt>
                            {{--
                                ⚠️ THE TWO METHODS ARE NOT EQUALLY STRONG AND
                                THIS LINE IS WHERE A READER SEES IT. A ticked
                                box is a deliberate affirmative act; continuing
                                through a sign-on button is acceptance of a
                                notice rendered beside it. Both are recorded and
                                a reader can tell them apart, which is the
                                honest arrangement.
                            --}}
                            <dd class="text-base text-ink">
                                @if ($acceptance->method === \App\Enums\TermsAcceptanceMethod::Checkbox)
                                    A box they ticked
                                @else
                                    A notice beside the button they pressed
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm text-ink-2">Box state</dt>
                            <dd class="text-base text-ink">
                                {{ $acceptance->proof['checkbox_state'] ?? 'No box — acceptance was by notice' }}
                            </dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">On this page</dt>
                            <dd class="break-all font-mono text-base text-ink">{{ $acceptance->proof['url'] ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">Address, hashed</dt>
                            <dd class="break-all font-mono text-base text-ink">{{ $acceptance->proof['ip_hash'] ?? '—' }}</dd>
                            {{--
                                ⚠️ WHAT A READER MAY DO WITH THIS VALUE, said
                                on the screen where they are looking at it.
                                Signup agreements hash the address under
                                their own scope (7888), so two agreements
                                recorded from one place still match each
                                other and match nothing anywhere else — and
                                a row recorded before that changed does not
                                match a row recorded after it. Without this
                                line the honest answer to "same person?"
                                across that boundary is a silent no.
                            --}}
                            @if ($acceptance->proof['ip_hash'] ?? null)
                                <p class="mt-1 text-sm text-ink-2">
                                    @if (($acceptance->proof[\App\Services\Consent\ProofHash::DOMAIN_KEY] ?? null) !== null)
                                        Compare this with other signup agreements. It will not match the same address recorded anywhere else.
                                    @else
                                        Recorded before agreements were scoped, so it will not match a newer agreement from the same address — and it may match the same address elsewhere.
                                    @endif
                                </p>
                            @endif
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">Browser</dt>
                            <dd class="break-all font-mono text-base text-ink">{{ $acceptance->proof['user_agent'] ?? '—' }}</dd>
                        </div>

                        <div class="sm:col-span-2">
                            <dt class="text-sm text-ink-2">The words they were shown</dt>
                            <dd class="text-base text-ink">{{ $acceptance->proof['disclosure_text'] ?? '—' }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                {{--
                    No action: there is genuinely nothing a staff member can
                    do here. An acceptance is written at signup by the person
                    opening the account and by nothing else, so a button
                    offering to add one would be offering to manufacture
                    evidence.
                --}}
                <x-ui.empty-state class="mt-4" heading="Nothing on record">
                    This business has no acceptance recorded. Accounts opened before
                    this record existed have none, and nobody here can add one — an
                    acceptance is made by the person opening the account.
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

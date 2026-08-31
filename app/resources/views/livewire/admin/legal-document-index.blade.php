{{--
    The legal library: every document, where each stands, and what is
    blocking a launch.

    ⚠️ COLOUR IS NOT THE SIGNAL (`22`). Every state is a sentence — "Published
    1.0", "Draft 0.9, unpublished", "placeholder text" — and the blocking
    summary is a count and a list, never a red dot. A reviewer scanning this
    page is deciding what to read next, and a colour they cannot name does not
    help them do it.

    THE TEXT IS NOT RENDERED HERE. Bodies are thousands of words; this is the
    index and the document screen is one click away.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Legal documents</h1>
        <p class="mt-1 text-base text-ink-2">
            The terms every tenant is bound by. A published version can never be edited —
            a change is a new version, and the old text stays readable forever.
        </p>
    </div>

    {{--
        `39` step 5: "wide launch stays locked while any served doc is a
        placeholder". This states the input; the launch checklist that
        consumes it is `38` Part 7 and is not built, which the wording says
        out loud rather than implying a gate that would refuse anything.
    --}}
    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Before a public launch</h2>

        {{--
            ⚠️ THE COPY NAMES TWO CAUSES BECAUSE THERE ARE TWO. A document
            blocks when it still serves placeholder text, and the SMS terms
            block as well when they have fallen behind the opt-in notice
            this application shows — finished text that has gone out of
            date, which is not the same problem and is not fixed the same
            way. `label()` names the cause per row; these two sentences may
            not claim only the first, or the screen would report "nothing to
            do" over a document a carrier reviewer would read against a page
            that no longer matches it.
        --}}
        @if ($blocking === [])
            <p class="mt-2 text-base text-ink-2">
                Every publicly served document is published, none is marked a placeholder,
                and the SMS terms quote the opt-in notice this application shows.
            </p>
        @else
            <p class="mt-2 text-base text-ink-2">
                {{ count($blocking) }} of the {{ $publicCount }} publicly served documents
                {{ count($blocking) === 1 ? 'is' : 'are' }} not ready:
            </p>

            <ul class="mt-3 list-disc space-y-1 pl-5 text-base text-ink-2">
                {{--
                    empty-state: absent because an empty $blocking is the
                    good news, and the sentence above already says it in the
                    sibling branch. An invitation card here would offer a
                    reader something to do about a launch that is not being
                    blocked.
                --}}
                @foreach ($blocking as $state)
                    <li>{{ $state->type->title() }} — {{ $state->label() }}</li>
                @endforeach
            </ul>
        @endif

        <p class="mt-4 border-t border-rule pt-4 text-sm text-ink-2">
            This is the input to the launch checklist (<code>38</code> Part 7), not the
            checklist itself — nothing here refuses a launch.
        </p>
    </section>

    {{--
        The BAA is counted separately because it is never served at
        /legal/{doc} and gates a different thing: onboarding a covered
        entity, which BaaRecords::recordExecution() refuses against
        placeholder text on its own.
    --}}
    @if ($baa)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Before the first health-information tenant</h2>

            <p class="mt-2 text-base text-ink-2">
                @if ($baa->servesPlaceholder())
                    The Business Associate Agreement is {{ $baa->label() }}. No covered entity can
                    be onboarded against it — an execution is refused until a reviewed,
                    non-placeholder version is published.
                @else
                    Business Associate Agreement {{ $baa->published?->version }} is published and
                    can be executed with a covered entity.
                @endif
            </p>

            <p class="mt-4 border-t border-rule pt-4 text-sm text-ink-2">
                Publication records a named reviewer. It is not, and cannot be, proof that
                healthcare counsel reviewed the template — that gate (<code>29</code> §12.2)
                is a human one.
            </p>
        </section>
    @endif

    <section>
        <h2 class="sr-only">Every document</h2>

        <table class="w-full text-left text-base">
            <thead>
                <tr class="border-b border-rule text-sm text-ink-2">
                    <th scope="col" class="py-2 pr-4 font-medium">Document</th>
                    <th scope="col" class="py-2 pr-4 font-medium">State</th>
                    <th scope="col" class="py-2 pr-4 font-medium">Next step</th>
                    <th scope="col" class="py-2 font-medium">Versions</th>
                </tr>
            </thead>

            <tbody>
                {{--
                    empty-state: absent because the library is
                    LegalDocumentType::cases() with a state attached — every
                    document this application has exists whether or not
                    anybody has drafted it, so this table cannot come back
                    empty and an empty branch would never render.
                --}}
                @foreach ($library as $state)
                    <tr class="border-b border-rule align-top">
                        <td class="py-3 pr-4">
                            <a
                                href="{{ route('admin.legal-documents', ['doc' => $state->type->value]) }}"
                                class="text-ink underline underline-offset-2"
                            >{{ $state->type->title() }}</a>

                            @unless ($state->type->isPublic())
                                <span class="block text-sm text-ink-2">Not served publicly</span>
                            @endunless
                        </td>

                        <td class="py-3 pr-4 text-ink-2">{{ $state->label() }}</td>
                        <td class="py-3 pr-4 text-ink-2">{{ $state->nextStep() }}</td>
                        <td class="py-3 text-ink-2">{{ $state->versions }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</div>

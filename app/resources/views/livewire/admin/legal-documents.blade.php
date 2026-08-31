{{--
    Drafting, review and publication for one legal document.

    ⚠️ THE TWO STATES READ DIFFERENTLY ON PURPOSE. A draft shows a textarea and a
    Save button; a published version shows its text and a "New version" control
    and no way to edit. The screen never offers an action the service will
    refuse, because a button that exists and then explains why it cannot work is
    how somebody concludes the rule is a bug.

    ⚠️ AND THE THREE ACTS ARE SHOWN AS ONE SEQUENCE (5387–5399). Save, Record
    review and Publish were three buttons in three panels, each appearing and
    disappearing on its own, which reads as three unrelated screens rather than
    the one checklist `39` step 3 actually describes. The step list at the top
    says where the reader is and what is still required; it describes and never
    gates, so the rule above is untouched.

    COLOUR IS NOT THE SIGNAL (`22`). Draft and Published are words, every step
    carries its state as a word and a mark, and every diff row carries a marker
    and a screen-reader label beside its tint.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">{{ $type->title() }}</h1>
        <p class="mt-1 text-base text-ink-2">
            @if ($current)
                Published version {{ $current->version }}.
            @else
                Nothing published yet — the public page shows a placeholder notice.
            @endif
        </p>
    </div>

    @if ($draft)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">
                Draft · version {{ $draft->version }}
            </h2>

            {{--
                THE SEQUENCE, NOT A PROGRESS BAR. Each step names an act a
                person performs and says what is true about it right now —
                `22`'s outcome language — so somebody who opened this screen
                for the first time can read three lines and know what is left
                to do without pressing anything to find out.
            --}}
            <ol class="mt-4 space-y-2">
                {{--
                    empty-state: absent because these are the three acts of
                    publishing a version, built from a literal list in
                    `flowSteps()`. There is no state in which a draft has
                    none of them, so an empty branch would be dead code.
                --}}
                @foreach ($steps as $step)
                    <li
                        wire:key="step-{{ $step['number'] }}"
                        class="flex flex-col gap-1 rounded-[--radius-field] border border-rule px-3 py-2 sm:flex-row sm:items-baseline sm:gap-3 {{ $step['state']->backgroundClass() }}"
                    >
                        <span class="flex items-baseline gap-2 {{ $step['state']->textClass() }}">
                            <span aria-hidden="true" class="font-mono">{{ $step['state']->marker() }}</span>
                            <span class="text-base font-semibold">
                                Step {{ $step['number'] }} · {{ $step['state']->label() }}
                            </span>
                        </span>

                        <span class="text-base text-ink">
                            <span class="font-medium">{{ $step['title'] }}.</span>
                            <span class="text-ink-2">{{ $step['detail'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ol>

            <label class="mt-5 block">
                <span class="text-sm text-ink-2">Document text</span>
                {{--
                    ⛔ THE VALUE GOES BETWEEN THE TAGS AND A TEXTAREA IS THE
                    ONE CONTROL WHERE IT MUST. An `<input>` carries its value
                    in an attribute Livewire fills; a textarea's value IS its
                    content, so a template that omits it renders an empty box
                    over a component whose $body holds the whole document.

                    That is what shipped, and it was found by the owner
                    trying to use the screen rather than by the suite: every
                    test here `set('body', …)` and none asserted it was
                    rendered, so the assertions passed against a box nobody
                    could read (411's shape). The danger is not the blank
                    display — it is that typing into a blank box replaces the
                    document with whatever was typed, and `legal:seed`
                    deliberately never restores a document that already has a
                    version.

                    ⚠️ THE TEST THAT GUARDS IT NOW READS THE RENDERED HTML
                    AND NOT ONLY THE WORDS ON THE PAGE (5391). `assertSee`
                    passes just as happily against `value="…"` on a textarea
                    — the exact defect — so *"the draft body is rendered
                    between the textarea tags"* matches the tag pair itself.

                    TEXT-BASE, NOT TEXT-SM: this is body text somebody reads
                    closely, and `22`'s floor is 16px.
                --}}
                <textarea
                    wire:model="body"
                    rows="18"
                    class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper p-3 font-mono text-base text-ink"
                >{{ $body }}</textarea>
            </label>

            <label class="mt-4 flex items-center gap-2">
                <input type="checkbox" wire:model="isPlaceholder" class="rounded border-rule">
                <span class="text-base text-ink-2">
                    This text is a working draft, not final
                </span>
            </label>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <x-ui.button
                    size="default"
                    wire:click="saveDraft"
                    wire:loading.attr="disabled"
                    wire:target="saveDraft"
                >
                    <span wire:loading.remove wire:target="saveDraft">Save draft</span>
                    <span wire:loading wire:target="saveDraft">Saving…</span>
                </x-ui.button>
            </div>

            {{--
                WHAT CHANGED, BESIDE THE BOX IT CHANGED IN.

                ⚠️ IT DIFFS THE BOX AGAINST THE PUBLISHED TEXT, NOT THE
                STORED DRAFT AGAINST IT. The question this answers is "what
                am I about to publish that is different from what is live",
                and the answer has to include what the reader has just typed
                — `wire:model` is deferred, so the text arrives on the next
                round trip and the diff is current from that moment.

                ⚠️ ONLY THE CHANGED LINES ARE PRINTED. A legal document is
                thousands of words and almost all of them are unchanged;
                printing them would bury the three that matter. The line
                number is what sends a reader to the place in the box.
            --}}
            @if ($current)
                <div class="mt-6 border-t border-rule pt-5">
                    <h3 class="font-display text-base font-semibold text-ink">
                        What this draft changes
                    </h3>

                    @if ($diff === [])
                        <p class="mt-2 text-base text-ink-2">
                            This draft matches published version {{ $current->version }} word for word.
                            Publishing it now would change nothing a reader sees.
                        </p>
                    @else
                        <p class="mt-2 text-base text-ink-2">
                            Compared with published version {{ $current->version }}:
                            {{ $diffTotals['added'] }}
                            {{ \Illuminate\Support\Str::plural('line', $diffTotals['added']) }} added,
                            {{ $diffTotals['removed'] }} removed.
                            Unchanged lines are not shown.
                        </p>

                        <ul class="mt-3 divide-y divide-rule overflow-hidden rounded-[--radius-field] border border-rule">
                            {{--
                                empty-state: absent because the sibling
                                branch above is this list's empty state — a
                                draft with nothing to show is good news and
                                an invitation card would offer a reader
                                something to do about it.
                            --}}
                            @foreach ($diff as $line)
                                <li class="flex gap-2 px-3 py-1 {{ $line->kind->backgroundClass() }}">
                                    <span aria-hidden="true" class="w-3 shrink-0 font-mono text-base text-ink-2">
                                        {{ $line->kind->marker() }}
                                    </span>
                                    <span class="sr-only">{{ $line->kind->label() }}, line {{ $line->number() }}:</span>
                                    <span aria-hidden="true" class="w-10 shrink-0 text-right font-mono text-sm text-ink-3">
                                        {{ $line->number() }}
                                    </span>
                                    <span class="min-w-0 flex-1 whitespace-pre-wrap break-words font-mono text-base text-ink">{{ $line->text }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if ($diffHidden > 0)
                            <p class="mt-2 text-sm text-ink-2">
                                And {{ $diffHidden }} more changed
                                {{ \Illuminate\Support\Str::plural('line', $diffHidden) }}, not shown here.
                            </p>
                        @endif
                    @endif
                </div>
            @endif

            <div class="mt-6 border-t border-rule pt-5">
                @if ($draft->reviewed_at)
                    <p class="text-base text-ink-2">
                        Reviewed by {{ $draft->reviewed_by }} on
                        {{ $draft->reviewed_at->format('j F Y') }}.
                    </p>

                    <x-ui.button
                        class="mt-3"
                        size="default"
                        wire:click="publish"
                        wire:loading.attr="disabled"
                        wire:target="publish"
                    >
                        <span wire:loading.remove wire:target="publish">Publish version {{ $draft->version }}</span>
                        <span wire:loading wire:target="publish">Publishing…</span>
                    </x-ui.button>

                    <p class="mt-2 text-sm text-ink-2">
                        Publishing freezes this text permanently. Later changes
                        are published as a new version.
                    </p>
                @else
                    {{--
                        Review before publication, per `39`'s checklist step 3.
                        The reviewer is named because the name is the record
                        that the review happened.

                        ⛔ THE BOX IS PRE-FILLED WITH THE SIGNED-IN ADMIN AND
                        THAT IS NOT THE RECORD (5389). Nothing is stored
                        until somebody presses Record review, and the name
                        stays editable because the reviewer is frequently
                        counsel, who has no account here — which is why
                        `reviewed_by` is a string rather than a foreign key.
                    --}}
                    <label class="block">
                        <span class="text-sm text-ink-2">Reviewed by</span>
                        <input
                            wire:model="reviewer"
                            type="text"
                            placeholder="Name of the reviewer"
                            class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                        >
                    </label>

                    <x-ui.button
                        class="mt-3"
                        size="default"
                        variant="secondary"
                        wire:click="recordReview"
                        wire:loading.attr="disabled"
                        wire:target="recordReview"
                    >
                        <span wire:loading.remove wire:target="recordReview">Record review</span>
                        <span wire:loading wire:target="recordReview">Recording…</span>
                    </x-ui.button>

                    <p class="mt-2 text-sm text-ink-2">
                        A version is published only after a named reviewer has read it.
                    </p>
                @endif
            </div>
        </section>
    @else
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Start a new version</h2>

            <p class="mt-1 text-base text-ink-2">
                @if ($current)
                    The new draft starts from the published text so nothing is lost.
                @else
                    The first version of this document.
                @endif
            </p>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                {{--
                    ⚠️ THE BOX ARRIVES FILLED IN (5388). It used to be blank
                    with a `1.0` placeholder, so the first thing this screen
                    asked of an admin was to invent a version string — and a
                    version string is the pointer every stored consent record
                    resolves through (decision 330). The suggestion is the
                    next one on from what is published and is never one the
                    service would refuse as taken; it stays editable, because
                    whether a change is a 1.1 or a 2.0 is counsel's call.
                --}}
                <label class="block">
                    <span class="text-sm text-ink-2">Version</span>
                    <input
                        wire:model="newVersion"
                        type="text"
                        placeholder="1.0"
                        class="mt-1 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                </label>

                <x-ui.button
                    size="default"
                    wire:click="startDraft"
                    wire:loading.attr="disabled"
                    wire:target="startDraft"
                >
                    <span wire:loading.remove wire:target="startDraft">Start draft</span>
                    <span wire:loading wire:target="startDraft">Starting…</span>
                </x-ui.button>
            </div>

            <p class="mt-3 text-sm text-ink-2">
                @if ($current)
                    Suggested from published version {{ $current->version }}. Change it if this
                    revision is numbered differently.
                @else
                    Change it if counsel numbers the first version differently.
                @endif
            </p>
        </section>
    @endif

    <section>
        <h2 class="font-display text-lg font-semibold text-ink">Every version</h2>

        @if ($history->isEmpty())
            {{--
                No action: "Start a version" is the button at the top of
                this same screen, and repeating it here would be two
                controls for one act, three centimetres apart.
            --}}
            <x-ui.empty-state class="mt-3" icon="§">
                No versions yet. Starting one opens a draft nobody else can see until
                it is published.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-2">
                @foreach ($history as $version)
                    <li
                        wire:key="version-{{ $version->id }}"
                        class="rounded-[--radius-panel] border border-rule bg-card px-4 py-3"
                    >
                        <span class="font-medium text-ink">{{ $version->version }}</span>
                        <span class="text-ink-2">
                            @if ($version->isPublished())
                                · Published {{ $version->published_at->format('j F Y') }}
                                by {{ $version->published_by }}
                            @else
                                · Draft
                            @endif
                            @if ($version->is_placeholder)
                                · Working draft, not final
                            @endif

                            {{--
                                ⚠️ THE COUNSEL-APPROVAL RECORD, ON EVERY
                                VERSION RATHER THAN ONLY THE OPEN DRAFT.

                                CC-4 asked for a nullable
                                `counsel_approved_at` column here. The
                                column is refused: `reviewed_at` and
                                `reviewed_by` already are that record, a
                                CHECK constraint keeps them consistent with
                                each other, `LegalDocuments::publish()`
                                refuses a version without them, and a second
                                timestamp for one act would be a second
                                source of truth on a row the publish trigger
                                makes unrepairable. What was genuinely
                                missing is this line — the screen showed the
                                reviewer for the draft being edited and for
                                nothing else, so "which versions has counsel
                                approved, and when" had no answer anywhere.
                                Decision 5168.

                                ⚠️ NULL READS AS "NOT YET", NEVER AS
                                NOTHING. R53 ships every text live and
                                stamped DRAFT-FOR-COUNSEL, so an unreviewed
                                row is the ordinary state on a fresh install
                                and printing no words at all would read as a
                                screen that forgot to say.
                            --}}
                            @if ($version->reviewed_at)
                                · Counsel approved
                                {{ $version->reviewed_at->format('j F Y') }}
                                by {{ $version->reviewed_by }}
                            @else
                                · Draft for counsel — not yet approved
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

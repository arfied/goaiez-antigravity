{{--
    What we changed on your website — `28` §3.5's Normal surface, row 10 U1b.

    THE COPY IS THE PRODUCT HERE. This is the screen that tells somebody what a
    machine did to their own website without asking them, so every sentence
    names what they control and none names how the system is built (`22`,
    `29` §2 rule 47). The words come from `SiteChangeUndoState::sentence()`
    rather than from this template, so this screen and any later one cannot
    describe one event in two vocabularies — `win-back.blade.php`'s rule.

    NOTHING IS HIDDEN AND NOTHING IS SORTED AWAY. Undone changes stay in the
    list, in place, saying who undid them. A change we can no longer reach stays
    too, saying so. A history that quietly dropped its awkward rows would be
    worth nothing on the one screen whose whole subject is that we tell you.

    COLOUR IS NEVER THE SIGNAL (`22`). Every card carries `<x-ui.status-pill>`,
    which is a word and an icon that happen to be tinted; remove the colour and
    every state is still readable. The "still on your website" case is
    `Attention` rather than `Alert` — it is a thing to decide about, not an
    emergency.

    NO MOTION. Nothing here animates, so there is nothing for
    `prefers-reduced-motion` to reduce; `app.css` carries the global rule.

    320px: one column throughout, the action row wraps, tap targets are 44px
    (`min-h-11` on `size="default"`), and body text never drops below
    `text-base`.

    THE STANDFIRST SAID "WE NEVER DELETE ANYTHING YOU WROTE" AND IT WAS TRUE OF
    ONE ARM ONLY — CORRECTED 2026-08-20 (6042, 6142). Undoing a page we created
    is an unpublish and never a delete, so it was true there. Undoing an *edit*
    wrote our stored snapshot back over the page fourteen to thirty days later
    with no comparison of any kind, and an owner who disliked our edit is the
    most likely person to have rewritten that page in between — so the one
    sentence on this screen promising their words were safe was the sentence that
    was false. `WordPressAdapter::edit()` now refuses rather than writes, and this
    copy says the thing the code actually does. 314-316: the claim came after the
    mechanism, not before it.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What we changed on your website</h1>
        <p class="mt-1 max-w-2xl text-base text-ink-2">
            Every change we have made, newest first. If you do not like one, undo it — we put
            the page back the way it was. If you have written on that page yourself since,
            we leave it exactly as it is and tell you, rather than writing over your words.
        </p>
    </div>

    @forelse ($changes as $change)
        <section
            wire:key="site-change-{{ $change->id }}"
            aria-labelledby="site-change-heading-{{ $change->id }}"
            class="rounded-[--radius-card] border border-rule bg-card p-5 sm:p-6"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="site-change-heading-{{ $change->id }}" class="font-display text-lg font-semibold text-ink">
                        {{ $change->heading() }}
                    </h2>
                    <p class="mt-1 break-all text-base text-ink-2">{{ $change->displayUrl() }}</p>
                </div>

                <x-ui.status-pill :state="$change->state->signal()" :label="$change->state->label()" />
            </div>

            <p class="mt-4 text-base text-ink">
                {{ $change->state->sentence($change->createdThePage) }}
            </p>

            {{--
                WHY WE UNDID IT, IN THE WORDS IT WAS MEASURED IN — and only on
                the arm where we were the one who decided. On an owner's own
                undo the stored reason is our sentence about their press, which
                would read as us explaining their decision back to them.
            --}}
            @if ($change->state === \App\Enums\SiteChangeUndoState::UndoneByUs && $change->undoneReason)
                <p class="mt-2 text-base text-ink-2">{{ $change->undoneReason }}</p>
            @endif

            {{--
                WHAT WE FOUND OUT AFTERWARDS (6060). `site_changes` has carried
                a baseline document and a measured document on every change we
                have judged since slice H, and until this line nothing anywhere
                read either of them back — so this platform measured its own work
                on somebody's website, concluded it helped, and never told them.

                ONE SENTENCE AND NEVER A PANEL. 5849 refused a metrics panel here
                on the grounds that this screen is a control rather than a report,
                which is right about numbers and wrong about outcomes: `29` §2
                rule 47 asks for what changed and whether it was good, and that is
                a sentence. The numbers themselves are for support, through
                `php artisan actuation:evidence`.

                THE PAGE'S OWN SIGNAL ONLY. Google Search Console reads a whole
                property, so the search figure is about the site rather than this
                page (5808) — printing it here would be read as this page's
                result whatever caveat sat beside it.
            --}}
            @php($resultSentence = $change->resultSentence())

            @if ($resultSentence !== null)
                <p class="mt-3 text-base text-ink-2">{{ $resultSentence }}</p>
            @endif

            {{--
                WHAT WE COULD NOT DO, WHERE THE OWNER CAN SEE IT (5836). The
                change set records the fields it had to leave alone and why; a
                refusal nothing surfaces is a promise quietly unkept with a green
                suite behind it (1222). The sentences name the thing that is
                missing from their page, never the field name and never the
                adapter's reason.
            --}}
            @if ($change->withheld !== [])
                <ul class="mt-3 space-y-2 border-l-2 border-rule pl-4">
                    @foreach ($change->withheld as $sentence)
                        <li class="text-base text-ink-2">{{ $sentence }}</li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-4 text-sm text-ink-3">
                {{ $change->locationName }} &middot;
                @if ($change->undoneAt)
                    changed {{ $change->appliedAt->toFormattedDayDateString() }},
                    undone {{ $change->undoneAt->toFormattedDayDateString() }}
                @else
                    changed {{ $change->appliedAt->toFormattedDayDateString() }}
                @endif
            </p>

            @if ($change->state->offersUndo())
                <div class="mt-5">
                    @if ($confirmingChangeId === $change->id)
                        {{--
                            THE SECOND PRESS, AND THE SENTENCE IT EXISTS FOR
                            (5839). An undo cannot be undone — there is no redo
                            in this application — so the one thing this panel
                            must say is the thing that is true only before the
                            press.
                        --}}
                        <div class="rounded-[--radius-card] border border-rule-strong bg-paper p-4">
                            <p class="text-base text-ink">
                                @if ($change->createdThePage)
                                    Take this page off your website? We cannot put it back for you
                                    afterwards — but nothing is deleted, so you can publish it again
                                    yourself whenever you like.
                                @else
                                    Put this page back the way it was? We cannot make the change again
                                    for you afterwards.
                                @endif
                            </p>

                            <div class="mt-4 flex flex-wrap items-center gap-3">
                                <x-ui.button
                                    size="default"
                                    wire:click="undo"
                                    wire:loading.attr="disabled"
                                    wire:target="undo"
                                >
                                    <span wire:loading.remove wire:target="undo">Undo this change</span>
                                    <span wire:loading wire:target="undo">Undoing…</span>
                                </x-ui.button>

                                <x-ui.button variant="quiet" size="default" wire:click="cancel">
                                    Leave it as it is
                                </x-ui.button>
                            </div>
                        </div>
                    @else
                        <x-ui.button
                            variant="secondary"
                            size="default"
                            wire:click="confirm({{ $change->id }})"
                        >
                            Undo this change
                        </x-ui.button>
                    @endif
                </div>
            @endif
        </section>
    @empty
        {{--
            empty-state: no action, and that is the honest shape here rather
            than a gap (`x-ui.empty-state`'s own rule, and `win-back`'s). There
            is nothing for an owner to press: this list fills itself when we
            improve something, and offering a button would turn a reassuring
            screen into a chore about a thing they do not do.
        --}}
        <x-ui.empty-state icon="◇" heading="We have not changed anything yet">
            When we improve a page on your website, it turns up here with a way to undo it.
            You will always see what we did before you have to ask.
        </x-ui.empty-state>
    @endforelse
</div>

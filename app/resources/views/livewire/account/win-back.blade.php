{{--
    Win back customers — the recovery queue decision 114 implies and 2689 built.

    COLOUR IS NEVER THE SIGNAL (`22`). Every state is a word inside a chip and
    every chip carries a text mark ("Needs you", "Won back"); nothing on this
    screen is distinguishable by hue alone, and the two sections are separated by
    headings rather than by tint.

    OUTCOME LANGUAGE ONLY. The buttons and the chips share their wording — "Mark
    as won back" leaves the card reading "Won back" — so an owner never has to
    check whether the click did what they asked. The words come from
    `TriageStatus::label()`, not from this template, so the screen and the feed
    cannot end up describing one event in two vocabularies.

    ⛔ NOTHING HERE HIDES, HOLDS OR DELETES ANYTHING. Worked conversations move
    to the second section and stay readable; the rating and the customer's words
    are rendered in both. 2075's rule is that every rating is captured and kept.

    320px: cards are single-column, the action row wraps, tap targets are 44px
    (`min-h-11`), and body text never drops below `text-base`.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Win back customers</h1>
        <p class="mt-1 text-base text-ink-2">
            These customers told you privately that something went wrong. Put it right,
            then say what you did — that is what counts towards
            <span class="whitespace-nowrap">&ldquo;customers won back&rdquo;</span> on your home screen.
        </p>
    </div>

    <section aria-labelledby="win-back-needs-you" class="space-y-4">
        <h2 id="win-back-needs-you" class="font-display text-lg font-semibold text-ink">
            Needs you
        </h2>

        @if ($needsYou->isEmpty())
            {{--
                ⚠️ NO ACTION, AND THIS IS THE ONE SCREEN WHERE THAT IS A RELIEF
                RATHER THAN A GAP. An empty "Needs you" is the good outcome —
                nobody is unhappy and waiting — so a button would turn the best
                state this screen can show into a chore.
            --}}
            <x-ui.empty-state icon="◎">
                Nobody is waiting on you. Customers who rate you low land here so you can
                reach out before they go public.
            </x-ui.empty-state>
        @else
            <ul class="space-y-4">
                @foreach ($needsYou as $conversation)
                    <x-account.win-back-card
                        :conversation="$conversation"
                        :outcomes="$outcomes"
                        :worked="false"
                    />
                @endforeach
            </ul>
        @endif
    </section>

    @if ($worked->isNotEmpty())
        {{--
            empty-state: absent because an owner who has never had to win
            anybody back should not be shown a heading for it. The section is a
            record of work done, and a card announcing that no work has been
            done reads as a reproach on the screen whose empty state above is
            already the good news.
        --}}
        <section aria-labelledby="win-back-worked" class="space-y-4">
            <h2 id="win-back-worked" class="font-display text-lg font-semibold text-ink">
                Already dealt with
            </h2>

            {{--
                Kept on the screen rather than cleared away. This is the only place
                an owner can read the feedback that never went public, and a list
                that emptied itself as it was worked would show them less than was
                left — see the component docblock.
            --}}
            <ul class="space-y-4">
                @foreach ($worked as $conversation)
                    <x-account.win-back-card
                        :conversation="$conversation"
                        :outcomes="$outcomes"
                        :worked="true"
                    />
                @endforeach
            </ul>
        </section>
    @endif
</div>

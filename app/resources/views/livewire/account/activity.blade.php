{{--
    What we have been doing — `28` §85's Activity entry in the Normal
    navigation, and the screen decision 6073 says did not exist.

    THE SHARED LABEL IS THE SUBJECT OF THIS TEMPLATE. Every `OwnerActionNeeded`
    on the platform is filed under one title — "Something needs your attention"
    — with its specifics in a metadata bag nothing read. A feed that printed
    that sentence a hundred times would be the defect with a URL, so every row's
    heading comes from `OwnerAttention::headlineFor()`, which says which of the
    thirteen things happened. A bag it does not recognise falls back to the
    shared title, which reads as the gap it is.

    METADATA IS NEVER RENDERED, IN WHOLE OR IN PART. It is a free-form jsonb bag
    written by thirty-one callers; nothing on this page echoes a value out of
    it. Every sentence is a constant in `OwnerAttention`, chosen by the bag —
    see that file for what is deliberately not printed (a page URL, and a
    third-party website's error text).

    COLOUR IS NEVER THE SIGNAL (`22`). The only pill here is
    `<x-ui.status-pill>`, which is a word and an icon that happen to be tinted;
    remove the colour and "Needs you" is still the whole message. Which rows get
    it is `AutopilotActionType::needsOwner()`'s answer, not this template's.

    NOTHING IS PRESSABLE AND NOTHING IS HIDDEN. `activity_feed` is append-only
    at the model layer, so there is nothing to dismiss or resolve and no control
    that could honestly appear to. Rows are in one list, newest first, with no
    filter — `28` §106 puts filters and search in Advanced and this is Normal.

    NO MOTION. Nothing here animates, so there is nothing for
    `prefers-reduced-motion` to reduce; `app.css` carries the global rule.

    320px: one column throughout, the header row wraps, body text never drops
    below `text-base`.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What we have been doing</h1>
        <p class="mt-1 max-w-2xl text-base text-ink-2">
            Everything that has happened to your business, newest first — what we did for you,
            what your customers did, and anything that is waiting on you. Nothing here is ever
            removed or edited.
        </p>
    </div>

    @if ($nothingYet)
        {{--
            "Nothing yet" is a different statement from "this page is empty",
            and this branch exists so the two never share wording. Every tenant
            is in this state on their first day, and one who reads "nothing
            found" reasons that something was lost.

            No action: nothing on this screen starts anything, and the work this
            list will fill up with is the system's to begin. A button here would
            be an invitation to do our job for us — `messages.blade.php`'s rule
            at the same fork.
        --}}
        <x-ui.empty-state heading="Nothing has happened yet" icon="◷">
            As soon as we start working on your business, everything we do will appear here.
        </x-ui.empty-state>
    @else
        <ul class="space-y-3">
            @forelse ($entries as $entry)
                <li
                    wire:key="activity-{{ $entry->id }}"
                    class="rounded-[--radius-panel] border border-rule bg-card p-5"
                    data-activity-item="{{ $entry->id }}"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <p class="max-w-xl text-base font-medium text-ink">
                            {{ \App\Services\Activity\OwnerAttention::headlineFor($entry) }}
                        </p>

                        @if ($entry->action_type->needsOwner())
                            <x-ui.status-pill :state="\App\Enums\SignalState::Attention" label="Needs you" />
                        @endif
                    </div>

                    <p class="mt-2 text-sm text-ink-2">
                        {{ \App\Services\Activity\ActivityFeed::when($entry) }}
                        @if ($entry->location?->name)
                            {{-- The tenant's own place, not a customer. --}}
                            <span>· {{ $entry->location->name }}</span>
                        @endif
                    </p>
                </li>
            @empty
                <li>
                    {{--
                        A SECOND EMPTY STATE, FOR THE CASE THE FIRST ONE CANNOT
                        SEE. `$nothingYet` is the whole history being empty; this
                        list is one page of it, so ?page=9 on a two-page history
                        renders nothing with `$nothingYet` false — a blank screen
                        under a heading, caused by the reader's own address bar.
                        The day-one sentence would be a lie there.

                        The paginator's own first-page URL rather than this
                        screen's route name: this is a link to itself, and naming
                        the route would put a literal in a template that
                        `OwnerNavTest` reads — correctly — as a hand-written link
                        between owner screens. That lint reads the whole file,
                        comments included, so even writing the call here to
                        explain why it is not written reddens it.
                    --}}
                    <x-ui.empty-state
                        icon="◷"
                        action="Back to the first page"
                        :href="$entries->url(1)"
                    >
                        There is nothing on this page. There is more further back.
                    </x-ui.empty-state>
                </li>
            @endforelse
        </ul>

        <div>{{ $entries->links() }}</div>
    @endif

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">About this list</h2>
        <ul class="mt-2 space-y-1 text-base text-ink-2">
            <li>Nothing here can be edited or removed, by us or by you.</li>
            <li>Where something is waiting on you, it is marked and says what it is about.</li>
            <li>There is no search or filtering here, by design — everything stays in one place.</li>
        </ul>
    </div>
</div>

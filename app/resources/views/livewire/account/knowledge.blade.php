{{--
    What we answer from (`29` §11.2 row 5).

    COLOUR IS NEVER THE SIGNAL (`22`). Every source's state is a word an owner
    can read — "Ready to answer from", "We could not read this file" — never a
    dot or a hue. The list survives a colourblind reader, a monochrome print and
    a screen reader, because the status is text in the row rather than a title
    attribute on a shape.

    WORKS AT 320px. The list is a stacked block rather than a table: a table of
    three columns at 320px is a horizontal scrollbar, and nothing here is below
    16px (`text-base`) except the two supporting lines, which are `text-sm` for
    the same reason every other account screen uses it.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What we answer from</h1>
        <p class="mt-1 text-base text-ink-2">
            Give us a price list, an opening-hours page, a list of the questions people
            ask most — anything you would hand a new member of staff. We use it to
            answer customers in your words.
        </p>
    </div>

    @if ($mayUpload)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <form wire:submit="upload" class="space-y-5">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Your document</span>
                    <input
                        type="file"
                        wire:model="file"
                        accept=".txt,.md,.markdown,text/plain,text/markdown"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                    <span class="text-sm text-ink-2">
                        A plain text or Markdown file, up to 2MB. We cannot read PDFs or
                        Word documents yet — copy the text into a .txt file instead.
                    </span>
                </label>

                <div wire:loading wire:target="file" class="text-base text-ink-2">Reading your file…</div>

                @error('file')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit">
                    <span wire:loading.remove wire:target="upload">Add this document</span>
                    <span wire:loading wire:target="upload">Adding…</span>
                </x-ui.button>
            </form>
        </div>
    @else
        {{--
            Named rather than hidden, and never a disabled button. A greyed-out
            control reads as a bug in our page; a sentence naming who can do this
            reads as the truth and tells them who to ask.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Someone else adds these</h2>
            <p class="mt-2 text-base text-ink-2">
                Documents shape what we say to your customers in your name, so only an
                owner or a manager can add them. You can see everything below.
            </p>
        </div>
    @endif

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What we have so far</h2>

        @if ($sources->isEmpty())
            {{--
                No action: the form that adds a document is on this same screen,
                a few centimetres above, and a button that scrolls somebody to
                something already in front of them is furniture.
            --}}
            <x-ui.empty-state class="mt-3" icon="◫">
                Nothing yet. Until you add something, we answer from what you have told
                us elsewhere and hand anything else to you.
            </x-ui.empty-state>
        @else
            <ul class="mt-3 space-y-3">
                @foreach ($sources as $source)
                    <li class="flex flex-col gap-0.5 border-b border-rule pb-3 last:border-0 last:pb-0">
                        <span class="text-base text-ink">{{ $source->title ?? 'Untitled document' }}</span>
                        <span class="text-sm text-ink-2">
                            {{ $source->type->label() }} — {{ $source->status->label() }}
                        </span>
                    </li>
                @endforeach
            </ul>

            <p class="mt-4 text-sm text-ink-2">
                Reading a document takes a minute or two. Refresh this page to see
                where it has got to.
            </p>
        @endif
    </div>
</div>

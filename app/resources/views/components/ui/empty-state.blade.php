@props([
    'heading' => null,
    'action' => null,
    'href' => null,
    'target' => null,
    'icon' => '○',
])

{{--
    Empty state (`29` §5.4) — "illustration + one sentence + one action".

    `29` §5.7 settles the tone in four words: **"Empty states are invitations."**
    So this never says "No results found", which is a report on a query rather
    than a sentence to a person, and it never leaves someone on a screen with
    nothing to do — the action slot is what makes it an invitation instead of a
    dead end.

    THE "ILLUSTRATION" IS A MARK, NOT ARTWORK. `29` §6 bans stock photography
    outright and the marketing gate is LCP under 1.5 seconds; an SVG scene per
    empty state is weight on a page that sells site speed, in service of a screen
    the person should be leaving. A single glyph in the ink scale does the
    compositional job — it gives the eye somewhere to land above the sentence —
    at no bytes and no cache miss.
--}}

<div
    {{ $attributes->merge([
        'class' => 'flex flex-col items-center gap-3 rounded-[--radius-card] border border-dashed border-rule px-6 py-10 text-center',
    ]) }}
>
    <span aria-hidden="true" class="text-3xl leading-none text-ink-3">{{ $icon }}</span>

    @if ($heading)
        <h2 class="font-display text-lg font-semibold text-ink">{{ $heading }}</h2>
    @endif

    <p class="max-w-[28rem] text-base text-ink-2">{{ $slot }}</p>

    {{--
        ⚠️ AN ACTION WITH NOWHERE TO GO IS NOT RENDERED, AND THAT IS THE SAFE
        DIRECTION. This slot used to emit a bare <button> whenever `action` was
        set, so a label with no `href` and no `target` drew a control that did
        nothing when pressed — the dead-retry defect, on the screen where
        somebody has already found nothing. No lint in this repository refuses an
        `action` with no `href` and no `target`; the sibling project has one
        (`Architecture/ScreenStatesTest`, imported comments still cite it); this
        `@if` is the only defence in this tree, so do not remove it. See
        `tests/Feature/Architecture/README.md`.
    --}}
    @if ($action && ($href || $target))
        <div class="mt-1">
            @if ($href)
                <x-ui.button :href="$href" size="default">{{ $action }}</x-ui.button>
            @else
                <x-ui.button
                    size="default"
                    wire:click="{{ $target }}"
                    wire:loading.attr="disabled"
                    wire:target="{{ $target }}"
                >{{ $action }}</x-ui.button>
            @endif
        </div>
    @endif
</div>

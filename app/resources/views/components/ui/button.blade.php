@props([
    'variant' => 'primary',
    'href' => null,
    'type' => 'button',
    'size' => 'giant',
])

@php
    // `29` §5.4: "giant primary button (min 44px targets, lower-third on
    // mobile)". 44px is the WCAG 2.2 target-size floor and also roughly a
    // thumb — this is used one-handed, outdoors, by someone between jobs.
    $sizes = [
        'giant' => 'min-h-14 px-6 text-lg',
        'default' => 'min-h-11 px-4 text-base',
    ];

    $variants = [
        'primary' => 'bg-ink text-paper hover:opacity-90',
        'secondary' => 'border border-rule-strong bg-card text-ink hover:bg-paper',
        'quiet' => 'text-ink-2 hover:text-ink',
    ];

    $classes = implode(' ', [
        'inline-flex items-center justify-center gap-2 rounded-[--radius-control]',
        'font-semibold transition-[opacity,background-color] duration-150 ease-out',
        'focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        $sizes[$size] ?? $sizes['giant'],
        $variants[$variant] ?? $variants['primary'],
    ]);
@endphp

{{--
    The one button in the application.

    THE PRIMARY VARIANT IS INK, NOT A SIGNAL COLOUR. `22` reserves saturated
    colour for meaning — running, attention, action — so a green "Publish" button
    would be spending the vocabulary the instrument needs on a control that is
    just the obvious next step. Ink on paper is the strongest contrast available
    and costs nothing from that budget.

    THE VERB SURVIVES THE FLOW. `29` §5.7: "an action keeps its name through the
    whole flow" — Publish becomes Published, never "Success". The component
    cannot enforce that, but the toast that follows is
    `masmerise/livewire-toaster` (decision 104) and it is the same rule there.

    Renders an <a> when given an href and a <button> otherwise, because a link
    styled as a button that is not a link breaks middle-click, right-click and
    every assistive technology that lists a page's links.
--}}

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </button>
@endif

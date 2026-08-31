@props([
    'target',
    'busy',
])

{{--
    The submit button, with the loading state `29` §9.1 requires attached to it.

    ⚠️ ONE COMPONENT RATHER THAN FIFTEEN HAND-WRITTEN LABEL SWAPS. A submit is
    the one action a person triggered deliberately and is waiting on, and the one
    place a second press sends the thing twice — so the affordance is not
    optional, and an affordance that is retyped at every call site is one that is
    eventually forgotten at one of them. `Architecture/ScreenStatesTest` reads
    this tag as satisfying the requirement for exactly that reason.

    THE LABEL SWAPS, IT DOES NOT SPIN. `22`: the verb survives the flow — Save →
    Saving… — so the person reads what is happening rather than watching an
    animation that could mean anything. `busy` is required and has no default,
    because "Loading…" is the generic word this design system does not use, and a
    default would be the sentence nobody rewrites.

    The disabled attribute is what actually stops the double send; the swap is
    what explains it.
--}}

<x-ui.button
    type="submit"
    {{ $attributes }}
    wire:loading.attr="disabled"
    wire:target="{{ $target }}"
>
    <span wire:loading.remove wire:target="{{ $target }}">{{ $slot }}</span>
    <span wire:loading wire:target="{{ $target }}">{{ $busy }}</span>
</x-ui.button>

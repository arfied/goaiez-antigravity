@props([
    'state' => 'ok',
    'label' => null,
])

@php
    use App\Enums\SignalState;

    $signal = $state instanceof SignalState ? $state : SignalState::from($state);
    $label ??= $signal->label();
@endphp

{{--
    Status pill (`29` §5.4) — a state, in a word, with its icon.

    THERE IS NO COLOUR-ONLY VARIANT AND THERE WILL NOT BE ONE. The icon and the
    word are not garnish on the colour; they are the signal, and the colour is
    the thing that makes them fast to find. Removing either leaves a component
    that fails `29` §5.5 for about one man in twelve.

    Sentence case per `29` §5.7, so this reads as a state rather than shouting.
--}}

<span
    {{ $attributes->merge([
        'class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-sm font-semibold '
            .$signal->backgroundClass().' '.$signal->textClass(),
    ]) }}
>
    <span aria-hidden="true">{{ $signal->icon() }}</span>
    <span>{{ $label }}</span>
</span>

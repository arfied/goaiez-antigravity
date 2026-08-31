@props([
    'state' => 'attention',
    'heading' => null,
    'action' => null,
    'href' => null,
])

@php
    use App\Enums\SignalState;

    $signal = $state instanceof SignalState ? $state : SignalState::from($state);
@endphp

{{--
    Attention card (`29` §5.4) — "one issue, one action".

    THE CONSTRAINT IS THE COMPONENT. One issue and one action is not a layout
    suggestion; it is what stops a findings list becoming a backlog nobody
    starts. A card with three actions is a card whose first action is "decide",
    and deciding is the work the owner is paying not to do — the whole product
    promise is that they do nothing but reply to occasional text messages.

    So there is one `action` slot, not a list. A second action means a second
    card, which is honest about there being two things to do.

    The body is the plain sentence a check produced (row 2 slice E's Finding), so
    it is already outcome language and already free of vocabulary from inside the
    system. Nothing here reformats it.
--}}

<article
    {{ $attributes->merge([
        'class' => 'flex flex-col gap-3 rounded-[--radius-card] border border-rule bg-card p-4 shadow-[--shadow-card] sm:p-5',
    ]) }}
>
    <div class="flex items-start gap-3">
        {{-- Icon before colour, always. --}}
        <span aria-hidden="true" class="mt-0.5 text-lg leading-none {{ $signal->textClass() }}">
            {{ $signal->icon() }}
        </span>

        <div class="flex flex-col gap-1">
            <span class="sr-only">{{ $signal->label() }}.</span>

            @if ($heading)
                <h3 class="font-display text-lg font-semibold text-ink">{{ $heading }}</h3>
            @endif

            <p class="text-base leading-relaxed text-ink-2">{{ $slot }}</p>
        </div>
    </div>

    @if ($action)
        <div class="flex sm:pl-8">
            @if ($href)
                <x-ui.button :href="$href" variant="secondary">{{ $action }}</x-ui.button>
            @else
                <x-ui.button variant="secondary">{{ $action }}</x-ui.button>
            @endif
        </div>
    @endif
</article>

@props([
    'systems' => [],
])

@php
    use App\Enums\SignalState;
@endphp

{{--
    The systems strip beneath the gauge (`29` §5.3): ● Reviews ● Website
    ● Profile ● Leads — "tap a dot to open that subsystem".

    EACH DOT IS A REAL CONTROL WHEN IT HAS SOMEWHERE TO GO, and a plain span when
    it does not. A div with a click handler is unreachable by keyboard and
    unannounced by a screen reader, and `29` §5.5 requires full keyboard
    operability with visible focus. The 44px minimum target is the same rule the
    giant button follows, for the same reason: this is used one-handed on a phone.

    Expects a list of ['label' => string, 'state' => SignalState|string,
    'href' => ?string].
--}}

<ul {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-center gap-x-5 gap-y-2']) }}>
    @foreach ($systems as $system)
        @php
            $raw = $system['state'] ?? SignalState::Unknown;
            $signal = $raw instanceof SignalState ? $raw : SignalState::from($raw);
            $href = $system['href'] ?? null;
            $inner = 'inline-flex min-h-11 items-center gap-2 rounded-[--radius-control] px-1 text-sm text-ink-2';
        @endphp

        <li>
            @if ($href)
                <a
                    href="{{ $href }}"
                    class="{{ $inner }} hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2"
                >
                    <span aria-hidden="true" class="{{ $signal->textClass() }}">{{ $signal->icon() }}</span>
                    <span>{{ $system['label'] }}</span>
                    <span class="sr-only">— {{ $signal->label() }}</span>
                </a>
            @else
                <span class="{{ $inner }}">
                    <span aria-hidden="true" class="{{ $signal->textClass() }}">{{ $signal->icon() }}</span>
                    <span>{{ $system['label'] }}</span>
                    <span class="sr-only">— {{ $signal->label() }}</span>
                </span>
            @endif
        </li>
    @endforeach
</ul>

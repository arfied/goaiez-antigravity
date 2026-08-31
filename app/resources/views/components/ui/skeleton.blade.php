@props([
    'label',
    'lines' => 2,
])

{{--
    Skeleton loader with an honest label (`29` §5.4 — *"Checking your reviews…"*).

    THE LABEL IS REQUIRED, WITH NO DEFAULT, AND THAT IS THE ENTIRE POINT. A
    skeleton without one is a grey rectangle that says "something is happening";
    with one it says which of four checks is running and therefore how much of
    the answer is still to come. On the public audit page the checks complete
    seconds apart (row 2 slice E persists as it goes), so this is the difference
    between a page that feels like it is working and a page that feels stuck.

    "Honest" is doing work in that sentence too. The label must name the check
    that is actually running — a skeleton claiming "Checking your reviews" while
    the site fetch is what is outstanding is a small lie that becomes visible the
    moment the wrong card fills in first.

    aria-busy and the live region mean a screen reader is told the same thing,
    rather than being read an empty box.
--}}

<div
    {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-[--radius-card] border border-rule bg-card p-4 sm:p-5']) }}
    aria-busy="true"
    role="status"
>
    <p class="flex items-center gap-2 text-sm font-medium text-ink-2">
        {{-- Three dots that animate, and stop animating under reduced motion
             because app.css kills the duration globally. --}}
        <span aria-hidden="true" class="goaiez-pulse text-ink-3">●</span>
        {{ $label }}
    </p>

    <div class="flex flex-col gap-2" aria-hidden="true">
        @for ($line = 0; $line < (int) $lines; $line++)
            <div
                class="goaiez-pulse h-3 rounded-full bg-gauge-track"
                style="width: {{ $line === (int) $lines - 1 ? '60%' : '100%' }}; animation-delay: {{ $line * 120 }}ms"
            ></div>
        @endfor
    </div>
</div>

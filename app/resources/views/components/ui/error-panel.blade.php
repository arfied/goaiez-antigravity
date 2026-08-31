@props([
    'heading',
    'retry' => '$refresh',
    'retryLabel' => 'Try again',
])

{{--
    A section that could not be loaded (`34` §1.2's third state: *error = what
    happened + retry*).

    ⚠️ **IT GUARDS A SECTION, NOT A PAGE, AND THAT IS WHAT KEEPS IT HONEST.** The
    tempting version wraps a whole screen in a catch and renders "Something went
    wrong — try again" over everything. Two things break at once when it does: a
    programming error is caught alongside a transient one, so a `TypeError`
    becomes a retry button that can never work; and the rest of the page, which
    was fine, disappears with it. So the caller catches narrowly, around one
    read, and the parts of the screen that loaded stay on screen.

    **The heading names what failed, never what the system is.** *"We couldn't
    load their history"* tells the owner which part of the page is missing and
    therefore whether they can carry on. "An error occurred" tells them to stop
    and ask somebody. `22`'s outcome-language rule, applied to the one string a
    person reads on their worst visit.

    `role="alert"` because this replaces content the reader was waiting for —
    a screen reader that silently gets a shorter page has no way to know a
    section is missing.
--}}

<div
    {{ $attributes->merge([
        'class' => 'flex flex-col gap-3 rounded-[--radius-card] border border-rule bg-card p-4 sm:p-5',
    ]) }}
    role="alert"
>
    <div class="flex items-start gap-3">
        {{-- Icon before colour, always (`22`) — and the same icon and words the
             rest of the product uses for this state, taken from the enum rather
             than typed here, so a screen reader hears one vocabulary. --}}
        @php($signal = \App\Enums\SignalState::Attention)

        <span aria-hidden="true" class="mt-0.5 text-lg leading-none {{ $signal->textClass() }}">
            {{ $signal->icon() }}
        </span>

        <div class="flex flex-col gap-1">
            <span class="sr-only">{{ $signal->label() }}.</span>

            <h3 class="font-display text-lg font-semibold text-ink">{{ $heading }}</h3>

            <p class="text-base leading-relaxed text-ink-2">{{ $slot }}</p>
        </div>
    </div>

    <div class="flex sm:pl-8">
        <button
            type="button"
            wire:click="{{ $retry }}"
            class="flex min-h-11 items-center rounded-[--radius-field] border border-rule bg-paper px-4 text-base font-medium text-ink hover:bg-card"
        >{{ $retryLabel }}</button>
    </div>
</div>

@props(['task'])

{{--
    One follow-up: the contact, the one line, when — and `44` §2's three
    actions. The slot is the "when" sentence, worded by the section that knows
    which group this row is in.
--}}

<li class="rounded-[--radius-panel] border border-rule bg-card p-5" data-task="{{ $task->id }}">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <p class="text-base font-medium text-ink">{{ $task->title }}</p>
        <p class="text-sm text-ink-2">{{ $slot }}</p>
    </div>

    <p class="mt-1 text-base text-ink-2">
        {{ $task->customer?->name ?: $task->customer?->email ?: $task->customer?->phone ?: 'A customer' }}
    </p>

    <div class="mt-3 flex flex-wrap gap-3">
        {{--
            ⚠️ `id="follow-up-done-{{ $task->id }}"` IS FOR THE TEST HARNESS.
            Multiple rows share the label "Done", and the browser suite's
            `>>`-chained locator syntax (`"[data-task='…'] >> text=Done"`) was
            found to hang the whole Playwright server rather than fail fast
            — a mid-wave finding, wave 36 lane B. A stable per-row id is a
            single, unchained, explicit CSS selector.
        --}}
        <x-ui.button id="follow-up-done-{{ $task->id }}" size="default" wire:click="complete({{ $task->id }})">Done</x-ui.button>
        <x-ui.button size="default" variant="secondary" wire:click="snooze({{ $task->id }}, 'day')">
            Snooze 1 day
        </x-ui.button>
        <x-ui.button size="default" variant="secondary" wire:click="snooze({{ $task->id }}, 'week')">
            Snooze 1 week
        </x-ui.button>
        @if ($task->customer)
            <a
                href="{{ route('account.customers.show', $task->customer) }}"
                class="flex min-h-11 items-center text-base font-medium text-ink underline"
            >Open contact</a>
        @endif
    </div>
</li>

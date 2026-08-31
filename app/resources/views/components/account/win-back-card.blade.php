@props(['conversation', 'outcomes', 'worked' => false])

@php
    $review = $conversation->review;
    $customer = $conversation->customer;

    // `24`'s own habit on a first-party row: an anonymous submission has no
    // customer at all (`ReviewRouter::openTriage()`'s docblock), so the card
    // names the person it can and says "A customer" rather than rendering a gap
    // the owner has to interpret.
    $who = $customer?->name ?: $customer?->email ?: $customer?->phone ?: ($review?->reviewer_name ?: 'A customer');
@endphp

{{--
    One recovery conversation.

    COLOUR IS NEVER THE SIGNAL (`22`). The state is a word — "Needs you", "Won
    back", "Couldn't reach them" — inside a bordered chip, and the rating is a
    number followed by the word "out of 5". Nothing here is legible only by hue,
    and a screen reader hears the same sentence a sighted reader does.

    ⛔ NOTHING ON THIS CARD HIDES OR REMOVES THE RATING OR THE WORDS. A worked
    conversation renders both exactly as an open one does; the only difference is
    which actions are offered. 2075's rule.
--}}

<li
    wire:key="triage-{{ $conversation->id }}"
    data-triage="{{ $conversation->id }}"
    class="space-y-4 rounded-[--radius-panel] border border-rule bg-card p-5"
>
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <p class="font-display text-lg font-semibold text-ink">
            {{ $who }}
        </p>

        <p class="text-base text-ink-2">
            <span class="font-data">{{ $review?->rating }}</span> out of 5
        </p>
    </div>

    <p>
        <span
            data-triage-state="{{ $conversation->status->value }}"
            class="inline-flex min-h-7 items-center rounded-full border border-rule-strong px-3 text-sm font-medium text-ink"
        >{{ $conversation->status->label() }}</span>

        @if ($conversation->ai_paused)
            {{--
                ⛔ SAYS WHO IS HANDLING IT AND NOTHING MORE (2706). `ai_paused`
                has no reader in `app/` — the AI triage loop is unbuilt — so a
                sentence like "nothing automated will contact this customer"
                would be a protection asserted before it is true, which is the
                CLAUDE.md failure (314–316) that stops the next reviewer looking.
            --}}
            <span
                data-triage-takeover="1"
                class="ms-1 inline-flex min-h-7 items-center rounded-full border border-rule px-3 text-sm text-ink-2"
            >You're handling this one</span>
        @endif
    </p>

    @if ($review?->comment)
        <blockquote class="border-l-2 border-rule pl-3 text-base text-ink-2">
            {{ $review->comment }}
        </blockquote>
    @endif

    @if ($conversation->outreach_draft)
        {{--
            The win-back message, drafted for the owner to send themselves
            (T176 P15).

            ⛔ THERE IS NO SEND BUTTON HERE AND THAT IS THE POSTURE, NOT AN
            OMISSION. Nothing in `app/` sends from `triage_conversations`: no
            channel is derived, no consent record is read, no suppression list is
            checked. The copy says so in the owner's own words — "we haven't sent
            it" — because a box of text beside four action buttons is exactly the
            shape somebody assumes has already gone out, and 314-316 is about a
            claim outrunning its mechanism in either direction.

            Read-only rather than a textarea bound to a property: an edit would
            need somewhere to be stored, and the place the owner edits this is
            their own phone or mail client, which is where they send it from.
        --}}
        <div>
            <p class="text-sm font-medium text-ink">A message you could send</p>

            <p
                data-triage-draft="{{ $conversation->id }}"
                class="mt-1 rounded-[--radius-control] border border-rule bg-canvas px-3 py-2 text-base text-ink"
            >{{ $conversation->outreach_draft }}</p>

            <p class="mt-1 text-sm text-ink-2">
                We wrote this for you and we haven&rsquo;t sent it. Copy it, change anything
                you like, and send it however you normally reach this customer.
            </p>
        </div>
    @endif

    @if ($conversation->resolution)
        <div>
            <p class="text-sm font-medium text-ink">What you did</p>
            <p class="mt-1 text-base text-ink-2">{{ $conversation->resolution }}</p>
        </div>
    @endif

    @unless ($worked)
        <label class="block">
            <span class="text-sm font-medium text-ink">What did you do about it?</span>
            {{--
                ⚠️ `id="win-back-note-{{ $conversation->id }}"` IS FOR THE TEST
                HARNESS. Multiple cards each carry their own note textarea, and
                the browser suite's `>>`-chained locator syntax was found to
                hang the whole Playwright server rather than fail fast — a
                mid-wave finding, wave 36 lane B, the same one
                `follow-up-row.blade.php` records. A stable per-card id is a
                single, unchained, explicit CSS selector.
            --}}
            <textarea
                id="win-back-note-{{ $conversation->id }}"
                wire:model="notes.{{ $conversation->id }}"
                rows="3"
                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-canvas px-3 py-2 text-base text-ink focus-visible:outline-2 focus-visible:outline-offset-2"
            ></textarea>
            {{--
                Said before the click rather than only after it. The refusal
                lives in `ReviewRouter::recordTriageOutcome()`, which is where it
                can actually hold — this line is so the owner is not first told
                by an error.
            --}}
            <span class="mt-1 block text-sm text-ink-2">
                Needed to mark someone won back. The others are fine without it.
            </span>
        </label>
    @endunless

    <div class="flex flex-wrap gap-3">
        @if ($worked)
            <x-ui.button
                size="default"
                variant="secondary"
                wire:click="reopen({{ $conversation->id }})"
            >{{ \App\Enums\TriageStatus::Open->actionLabel() }}</x-ui.button>
        @else
            @foreach ($outcomes as $outcome)
                {{--
                    `id="win-back-outcome-{{ $conversation->id }}-{{ $outcome->value }}"`,
                    the same reason as the note textarea above — a stable,
                    unchained id per card per outcome.
                --}}
                <x-ui.button
                    id="win-back-outcome-{{ $conversation->id }}-{{ $outcome->value }}"
                    size="default"
                    :variant="$outcome->isRecovery() ? 'primary' : 'secondary'"
                    wire:click="record({{ $conversation->id }}, '{{ $outcome->value }}')"
                >{{ $outcome->actionLabel() }}</x-ui.button>
            @endforeach

            @if ($conversation->ai_paused)
                <x-ui.button
                    size="default"
                    variant="quiet"
                    wire:click="takeOver({{ $conversation->id }}, false)"
                >Hand back</x-ui.button>
            @else
                <x-ui.button
                    size="default"
                    variant="quiet"
                    wire:click="takeOver({{ $conversation->id }}, true)"
                >I'll handle this one</x-ui.button>
            @endif
        @endif
    </div>
</li>

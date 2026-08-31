{{--
    The follow-ups list (`44` §2): due today on top, overdue plainly marked,
    snoozed rows out of the way at the bottom.

    COLOUR IS NOT THE SIGNAL (`22`): "Overdue" is a word, never a hue alone.
    Each row is [Done] [Snooze 1d/1w] [Open contact] and nothing else — §2's
    "Never" list refuses everything a task screen usually grows.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Follow-ups</h1>
        <p class="mt-1 text-base text-ink-2">
            The things you asked us to remind you about.
        </p>
    </div>

    {{--
        ONE INVITATION FOR THREE LISTS, AND THE SECTIONS BELOW VANISH RATHER
        THAN EACH SAYING "none". A screen where nothing is due, nothing is
        coming up and nothing is snoozed has one thing to say; three dashed
        cards stacked down the page would be three ways of saying it, on the
        screen a person opened hoping for work rather than furniture.
    --}}
    @if ($dueNow->isEmpty() && $later->isEmpty() && $snoozed->isEmpty())
        {{--
            ⛔ NO ACTION, AND THE NAV LINT IS RIGHT TO HAVE REFUSED THE ONE THAT
            WAS HERE. "Open your customers" is a cross-link between two top-level
            owner screens, and movement between those belongs to `OwnerNav` — a
            hand-written one here is how the sixth screen ends up inventing a
            seventh convention. The sentence already names where a reminder is
            made; Customers is one press away in the shell, on every screen.
        --}}
        <x-ui.empty-state icon="◷">
            Nothing to follow up. Add a reminder from any customer’s page — open a
            customer and choose “Remind me”.
        </x-ui.empty-state>
    @else
        @if ($dueNow->isNotEmpty())
            <section>
                <h2 class="font-display text-lg font-semibold text-ink">Due now</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($dueNow as $task)
                        <x-account.follow-up-row :task="$task">
                            @if ($task->due_at?->isBefore(now()->startOfDay()))
                                Overdue — was due {{ $task->due_at->diffForHumans() }}
                            @else
                                Due today
                            @endif
                        </x-account.follow-up-row>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($later->isNotEmpty())
            <section>
                <h2 class="font-display text-lg font-semibold text-ink">Coming up</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($later as $task)
                        <x-account.follow-up-row :task="$task">
                            {{-- "No date" rather than a blank: a blank reads as a rendering fault. --}}
                            {{ $task->due_at ? 'Due '.$task->due_at->diffForHumans() : 'No date' }}
                        </x-account.follow-up-row>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($snoozed->isNotEmpty())
            <section>
                <h2 class="font-display text-lg font-semibold text-ink">Snoozed</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($snoozed as $task)
                        <x-account.follow-up-row :task="$task">
                            Snoozed until {{ $task->snoozed_until?->diffForHumans() }}
                        </x-account.follow-up-row>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>

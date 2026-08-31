<div class="w-full max-w-3xl space-y-8">
    <header>
        <h1 class="font-display text-2xl font-semibold text-ink">Support requests</h1>
        <p class="mt-2 text-base text-ink-2">
            What accounts have asked us, oldest first. The list carries an account
            number and a channel and nothing anybody wrote — open a request to read it.
        </p>
    </header>

    @error('queue')
        <p class="text-base text-alert" role="alert">{{ $message }}</p>
    @enderror

    @if ($thread === null)
        <section aria-label="Waiting on us">
            @if ($waiting->isEmpty())
                {{--
                    No action: an agent reads this queue, they do not fill
                    it. The only thing that puts a row here is a customer
                    writing in.
                --}}
                <x-ui.empty-state icon="◇">
                    Nothing waiting. Every question a customer has asked us has an
                    answer.
                </x-ui.empty-state>
            @else
                <ul class="space-y-3">
                    @foreach ($waiting as $entry)
                        <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <button
                                    type="button"
                                    wire:click="open({{ $entry->support_ticket_id }})"
                                    class="text-left font-mono text-base text-ink underline decoration-rule underline-offset-4 hover:decoration-ink"
                                >
                                    Account {{ $entry->business_id }}
                                </button>
                                <p class="font-mono text-sm text-ink-2">
                                    Raised {{ $entry->opened_at->diffForHumans() }}
                                </p>
                            </div>
                            <p class="mt-1 text-base text-ink-2">
                                {{ $entry->channel->label() }}
                                ·
                                {{-- Words, never a pill on its own (`22`). --}}
                                @if ($entry->first_response_at === null)
                                    Nobody here has replied
                                @else
                                    First reply {{ $entry->first_response_at->diffForHumans() }}
                                @endif
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @else
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5" aria-label="One request">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-ink">{{ $thread->subject }}</h2>
                <p class="font-mono text-sm text-ink-2">Account {{ $thread->businessId }}</p>
            </div>
            <p class="mt-1 text-base text-ink" data-thread-status>
                {{ $thread->status->label() }}
                <span class="text-ink-2">· {{ $thread->channel->label() }}</span>
            </p>

            <ol class="mt-4 space-y-4">
                @foreach ($thread->messages as $entry)
                    <li class="border-l-2 border-rule pl-4">
                        <p class="text-sm font-medium text-ink-2">
                            {{ $entry->author->labelForStaff() }}
                            <span class="font-normal">· {{ $entry->writtenAt->diffForHumans() }}</span>
                        </p>
                        <p class="mt-1 whitespace-pre-line text-base text-ink">{{ $entry->body }}</p>
                    </li>
                @endforeach
            </ol>

            @if ($thread->status->isLive() && $mayAnswer)
                {{--
                    The macro library — T308 §A2's *"edits in the library, renders in
                    the composer"*. Each button pastes one macro into the reply box,
                    already carrying the sentences counsel owns and still carrying the
                    holes a person fills. A macro whose bound sentence is unset is not
                    listed at all, rather than listed and refusing when pressed.

                    ⚠️ THE INSERT IS A SERVER ROUND TRIP AND NOT AN ALPINE STRING. The
                    body is resolved from the registry at insert time, so pasting it
                    client-side would mean shipping every macro to the browser and
                    deciding there which of them are complete.
                --}}
                @if ($macros !== [])
                    <div class="mt-6">
                        <p class="text-sm font-medium text-ink-2">Start from a macro</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            {{--
                                empty-state: absent because a macro row is a shortcut,
                                not a list somebody came here to read. An agent's work
                                is the reply box below; a panel announcing "no macros"
                                on every thread would be noise on the one screen that
                                is meant to be about the tenant's words. The library is
                                only ever empty before `macros:sync` has run, and that
                                command says so on every run — including which macro is
                                held back because counsel's sentence is not on record
                                yet.
                            --}}
                            @foreach ($macros as $macro)
                                <x-ui.button
                                    type="button"
                                    variant="secondary"
                                    wire:click="insertMacro('{{ $macro['key'] }}')"
                                >{{ $macro['title'] }}</x-ui.button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <label class="mt-6 flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Reply to the account</span>
                    <textarea
                        id="support-ticket-reply"
                        wire:model="reply"
                        rows="4"
                        class="rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    ></textarea>
                </label>
                @error('reply')
                    <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <div class="mt-4 flex flex-wrap gap-3">
                    <x-ui.button type="button" wire:click="answer">Reply</x-ui.button>

                    @if ($mayResolve)
                        @if ($confirmingResolveId === $thread->ticketId)
                            {{--
                                ⚠️ THE CONSEQUENCE IS STATED ABOVE THE BUTTON, not
                                hidden behind it (1228, `PhiTenants`' rule): closing
                                takes the request off this queue and the only person
                                who finds out is the one who comes back to it.
                            --}}
                            <div class="w-full rounded-[--radius-control] border border-rule p-4">
                                <p class="text-base text-ink">
                                    Close this request? It leaves the queue. The account can
                                    reply on their own screen, which opens it again.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <x-ui.button id="confirm-resolve-ticket" type="button" wire:click="resolve">Yes, close it</x-ui.button>
                                    <button
                                        type="button"
                                        wire:click="dismissConfirm"
                                        class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                                    >
                                        Keep it open
                                    </button>
                                </div>
                            </div>
                        @else
                            <button
                                type="button"
                                wire:click="confirmResolve({{ $thread->ticketId }})"
                                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                            >
                                Close this request
                            </button>
                        @endif
                    @endif
                </div>
            @elseif (! $thread->status->isLive())
                <p class="mt-6 text-base text-ink-2">This request is closed.</p>
            @else
                <p class="mt-6 text-base text-ink-2">
                    You can read this request. Replying to an account is a support role.
                </p>
            @endif

            <div class="mt-6">
                <button
                    type="button"
                    wire:click="back"
                    class="min-h-11 text-base text-ink-2 underline decoration-rule underline-offset-4 hover:text-ink"
                >
                    Back to the queue
                </button>
            </div>
        </section>
    @endif

    <p class="text-sm text-ink-2">
        What this queue does not carry yet: requests that arrived by email or text.
        The desk accepts both and no transport delivers to it — see
        <span class="font-mono">SupportInbox</span> for what each one owes.
    </p>
</div>

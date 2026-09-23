{{--
    "Ask us something" — the tenant's half of T137 `SL-7`.

    ⚠️ COLOUR IS NOT THE SIGNAL (`22`). A thread's state is carried by its words —
    "Waiting for us", "We replied", "Closed" — from `SupportTicketStatus::label()`,
    so one vocabulary serves this screen, the console and any future one.

    ⚠️ WHAT THIS SCREEN DOES NOT PROMISE. There is no response-time claim on it.
    Nothing in this application measures one yet (the queue row records a first
    response and no screen reports it), and a promise a system cannot keep is
    worse on a support page than on any other.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Ask us something</h1>
        <p class="mt-1 text-base text-ink-2">
            Send us a question and we’ll answer here. Everything you’ve asked stays on
            this page.
        </p>
    </div>

    @if ($thread === null)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5" aria-label="Ask a new question">
            <h2 class="font-display text-lg font-semibold text-ink">New question</h2>

            <label class="mt-4 flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">What’s it about</span>
                <input
                    type="text"
                    wire:model="subject"
                    maxlength="{{ $this->subjectLimit() }}"
                    class="rounded-[--radius-control] border {{ $errors->has('subject') ? 'border-alert' : 'border-rule' }} bg-card px-3 py-2 text-base text-ink"
                    @error('subject') aria-invalid="true" @enderror
                />
            </label>
            @error('subject')
                <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror

            <label class="mt-4 flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Tell us what’s happening</span>
                <textarea
                    wire:model="body"
                    rows="4"
                    class="rounded-[--radius-control] border {{ $errors->has('body') ? 'border-alert' : 'border-rule' }} bg-card px-3 py-2 text-base text-ink"
                    @error('body') aria-invalid="true" @enderror
                ></textarea>
            </label>
            @error('body')
                <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror

            <div class="mt-4">
                <x-ui.button type="button" wire:click="raise">Send</x-ui.button>
            </div>
        </section>

        <section aria-label="Your questions">
            <h2 class="font-display text-lg font-semibold text-ink">What you’ve asked</h2>

            @if ($tickets->isEmpty())
                {{--
                    "Nothing yet" is a different statement from "no results", and
                    every account is in this state on day one.

                    No action, because the form that asks us something is the
                    section directly above this one — an invitation would point
                    at what the reader is already looking at.
                --}}
                <x-ui.empty-state class="mt-3" icon="◇">
                    You haven’t asked us anything yet.
                </x-ui.empty-state>
            @else
                <ul class="mt-4 space-y-3">
                    @foreach ($tickets as $ticket)
                        <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <button
                                    type="button"
                                    wire:click="open({{ $ticket->id }})"
                                    class="text-left text-base font-medium text-ink underline decoration-rule underline-offset-4 hover:decoration-ink"
                                >
                                    {{ $ticket->subject }}
                                </button>
                                <p class="text-sm text-ink-2">{{ $ticket->last_message_at->diffForHumans() }}</p>
                            </div>
                            <p class="mt-1 text-sm text-ink" data-ticket-status="{{ $ticket->id }}">
                                {{ $ticket->status->label() }}
                                <span class="text-ink-2">· {{ $ticket->channel->label() }}</span>
                            </p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @else
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5" aria-label="One question">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-ink">{{ $thread->subject }}</h2>
                <p class="text-sm text-ink" data-thread-status>{{ $thread->status->label() }}</p>
            </div>

            <ol class="mt-4 space-y-4">
                @foreach ($thread->messages as $entry)
                    <li class="border-l-2 border-rule pl-4">
                        <p class="text-sm font-medium text-ink-2">
                            {{ $entry->author->labelForTenant() }}
                            <span class="font-normal">· {{ $entry->writtenAt->diffForHumans() }}</span>
                        </p>
                        <p class="mt-1 whitespace-pre-line text-base text-ink">{{ $entry->body }}</p>
                    </li>
                @endforeach
            </ol>

            @if ($thread->status->isLive())
                <label class="mt-6 flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Reply</span>
                    <textarea
                        wire:model="reply"
                        rows="3"
                        class="rounded-[--radius-control] border {{ $errors->has('reply') ? 'border-alert' : 'border-rule' }} bg-card px-3 py-2 text-base text-ink"
                        @error('reply') aria-invalid="true" @enderror
                    ></textarea>
                </label>
                @error('reply')
                    <p class="mt-2 text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <div class="mt-4 flex flex-wrap gap-3">
                    <x-ui.button type="button" wire:click="send">Send</x-ui.button>
                    <button
                        type="button"
                        wire:click="close"
                        class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
                    >
                        Close this
                    </button>
                </div>
            @else
                <p class="mt-6 text-base text-ink-2">
                    This one is closed. Ask a new question and we’ll pick it up there.
                </p>
            @endif

            <div class="mt-6">
                <button
                    type="button"
                    wire:click="back"
                    class="min-h-11 text-base text-ink-2 underline decoration-rule underline-offset-4 hover:text-ink"
                >
                    Back to everything you’ve asked
                </button>
            </div>
        </section>
    @endif
</div>

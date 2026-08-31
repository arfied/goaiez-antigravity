{{--
    The display queue (`17` FPR-05).

    Deliberately plain — DASH-02's Reviews Inbox is the real screen and is
    Sprint 9. This exists so ReviewDisplay has a caller; see the component.

    COLOUR IS NOT THE SIGNAL. `22`: signal colour means running / attention /
    action and is never the sole indicator. The rating is a number and a word,
    the two actions are labelled verbs, and nothing here relies on a hue to be
    understood.

    ⛔ THE VOICE IS THE OPERATOR'S, NOT THE OWNER'S, AND IT USED TO BE THE
    OWNER'S ON A `super_admin`-ONLY SCREEN (9239). "Reviews waiting on **you**
    before they can show on **your** website" and a primary button reading
    "Show on my website" described a reader this route has never admitted —
    `AdminAccess::GATE` is `super_admin` alone. Now that the screen names whose
    account it is showing, the copy has to agree with the heading.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Reviews waiting for a decision</h1>
        <p class="mt-1 text-base text-ink-2">
            First-party reviews this customer has not published yet.
            Opening an account is recorded in that account's own trail.
        </p>
    </div>

    {{--
        ⛔ THE NO-ACCOUNT BRANCH IS THE FIX, NOT DECORATION (5733, 9236).
        This screen reads one tenant's reviews and the path names only a
        location, so it has to be told whose account that location is —
        platform staff own no business and have no tenant, and reading
        under whichever tenant happened to be in context is the quiet wrong
        answer. No account named runs no query at all.
    --}}
    @if ($location === null)
        <form wire:submit="resolve" class="max-w-md space-y-3">
            <label class="flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Account number</span>
                <input
                    type="text"
                    inputmode="numeric"
                    wire:model="reference"
                    class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 text-base text-ink"
                    placeholder="From the ticket"
                    @error('reference') aria-invalid="true" aria-describedby="reference-error" @enderror
                />
            </label>

            <p class="text-sm text-ink-3">
                Location {{ $locationId }}, once you say which account it belongs to.
            </p>

            @error('reference')
                {{-- Text, not colour alone (`22`). --}}
                <p id="reference-error" class="text-sm text-danger">{{ $message }}</p>
            @enderror

            {{--
                The label swaps rather than a spinner appearing beside it:
                the verb survives the flow (`22`), and the disabled
                attribute is what stops a second press opening the same
                account twice.
            --}}
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="resolve"
                class="min-h-11 rounded-[--radius-control] bg-ink px-4 text-base font-medium text-surface"
            >
                <span wire:loading.remove wire:target="resolve">Show this location's reviews</span>
                <span wire:loading wire:target="resolve">Opening…</span>
            </button>
        </form>
    @else
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[--radius-card] border border-rule bg-card px-4 py-3">
            <p class="text-base text-ink">
                <span class="text-ink-2">Account</span>
                <span class="font-medium">{{ $businessName }}</span>
                <span class="font-mono text-sm text-ink-3">#{{ $businessId }}</span>
                <span class="text-ink-2">·</span>
                <span class="font-medium">{{ $location->name }}</span>
            </p>

            <button
                type="button"
                wire:click="clearAccount"
                class="min-h-11 rounded-[--radius-control] border border-rule px-4 text-base text-ink-2 hover:text-ink"
            >
                Open a different account
            </button>
        </div>

        @if ($reviews->isEmpty())
            {{--
                "All clear", never an empty table with zeros. DASH-02 asks
                for this explicitly and it is the state this screen shows
                most: every 5-star is auto-approved, so an empty queue is
                the product working rather than nothing having happened.
            --}}
            <x-ui.empty-state heading="All clear" icon="✓">
                Nothing is waiting for a decision on this location.
            </x-ui.empty-state>
        @else
            <ul class="space-y-4">
                @foreach ($reviews as $review)
                    <li
                        wire:key="review-{{ $review->id }}"
                        class="rounded-[--radius-panel] border border-rule bg-card p-5"
                    >
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <p class="font-display text-lg font-semibold text-ink">
                                {{ $review->rating }} out of 5
                            </p>
                            <p class="text-base text-ink-2">
                                {{ $review->reviewer_name ?: 'No name given' }}
                            </p>
                            <p class="text-base text-ink-2">
                                {{ $review->created_at?->diffForHumans() }}
                            </p>
                        </div>

                        @if (filled($review->comment))
                            {{-- Escaped, like every other customer-written string
                                 on a staff screen. --}}
                            <p class="mt-3 text-base text-ink">{{ $review->comment }}</p>
                        @else
                            <p class="mt-3 text-base text-ink-2">No comment left.</p>
                        @endif

                        <div class="mt-5 flex flex-wrap gap-3">
                            <x-ui.button
                                size="default"
                                wire:click="approve({{ $review->id }})"
                                wire:loading.attr="disabled"
                                wire:target="approve({{ $review->id }})"
                            >
                                Show on their website
                            </x-ui.button>

                            <x-ui.button
                                size="default"
                                variant="secondary"
                                wire:click="reject({{ $review->id }})"
                                wire:loading.attr="disabled"
                                wire:target="reject({{ $review->id }})"
                            >
                                Keep it off
                            </x-ui.button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>

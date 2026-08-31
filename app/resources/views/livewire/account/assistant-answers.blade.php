{{--
    What your assistant may say (T176 §2.4, P5).

    ⛔ EVERY EMPTY SECTION SAYS WHAT STAYS SWITCHED OFF. R13 makes a missing price
    list mean skill 4 is absent — the assistant takes the question and passes it
    to the owner — and an owner who cannot see that is an owner who believes
    quotes are going out. `Account\AssistantLinks` records the same rule for the
    links half of this step.

    ⛔ THE PROPOSALS BLOCK IS THE REVIEW GATE MADE VISIBLE. Rows read off an
    uploaded price sheet sit here, plainly marked as not yet quotable, with a
    Confirm on each and one Throw these away for the set. Nothing in it is
    quotable until somebody presses.

    COLOUR IS NEVER THE SIGNAL (`22`). What is live and what is waiting for a
    person is carried by the heading and the sentence under it, never by a hue.

    WORKS AT 320px — every section is a stacked block, the prices are a list
    rather than a table, and nothing is below 16px except the supporting lines.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What your assistant can quote</h1>
        <p class="mt-1 text-base text-ink-2">
            Tell us what you charge for the jobs people ask about, and we will answer
            with your prices. Anything not on this list, we take a message and come to
            you — we never make a price up.
        </p>
    </div>

    @unless ($mayEdit)
        {{--
            Named rather than hidden, and never a disabled control —
            `Account\AssistantLinks`' reasoning: a greyed-out button reads as a bug
            in our page, a sentence naming who can do this reads as the truth and
            says who to ask.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Someone else sets these</h2>
            <p class="mt-2 text-base text-ink-2">
                These are quoted to your customers in your name, so only an owner or a
                manager can change them. You can see everything below.
            </p>
        </div>
    @endunless

    {{-- The list itself — skill 4. --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Your prices</h2>

        <div class="mt-4 space-y-3">
            @forelse ($list->entries as $slug => $entry)
                <div
                    class="flex flex-col gap-1 border-b border-rule pb-3 last:border-0 last:pb-0"
                    data-testid="price-{{ $slug }}"
                >
                    <span class="text-base text-ink">{{ $entry->label }}</span>
                    {{--
                        `PlanPricing::format()` is this codebase's only exit from
                        cents to a currency string (decision 512), and it takes a
                        `Money` rather than a `?Money` — so the top of the range
                        is pulled out first rather than called through `isRange()`,
                        which no type checker can see the guard in.
                    --}}
                    @php $upper = $entry->upperAmount(); @endphp
                    <span class="font-mono text-base text-ink">
                        {{ \App\Support\PlanPricing::format($entry->amount()) }}@if ($upper !== null) – {{ \App\Support\PlanPricing::format($upper) }}@endif
                    </span>
                    <span class="text-sm text-ink-2">{{ $entry->source->label() }}</span>

                    @if ($mayEdit)
                        <span>
                            <x-ui.button
                                variant="quiet"
                                size="default"
                                wire:click="removePrice('{{ $slug }}')"
                                wire:loading.attr="disabled"
                                wire:target="removePrice('{{ $slug }}')"
                            >Stop quoting this</x-ui.button>
                        </span>
                    @endif
                </div>
            @empty
                {{--
                    No action on the empty state: the form that adds one is on this
                    same screen, a few centimetres below, and a button that scrolls
                    somebody to something already in front of them is furniture —
                    `Account\AssistantLinks`' call, for the same reason.
                --}}
                <x-ui.empty-state icon="◫">
                    Nothing priced yet. Your assistant will take the question and pass it
                    to you rather than guessing a figure.
                </x-ui.empty-state>
            @endforelse
        </div>

        @if ($mayEdit)
            <form wire:submit="savePrice" class="mt-5 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">What is the job</span>
                    <input
                        type="text"
                        wire:model="jobName"
                        placeholder="Front door lockout"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                    <span class="text-sm text-ink-2">Use the words a customer would use when they text you.</span>
                </label>

                @error('jobName')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">What you charge</span>
                    <input
                        type="text"
                        inputmode="decimal"
                        wire:model="price"
                        placeholder="85"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 font-mono text-base text-ink"
                    />
                    <span class="text-sm text-ink-2">Put 0 if you do that one free and want us to say so.</span>
                </label>

                @error('price')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Up to (if it varies)</span>
                    <input
                        type="text"
                        inputmode="decimal"
                        wire:model="priceMax"
                        placeholder="400"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 font-mono text-base text-ink"
                    />
                    <span class="text-sm text-ink-2">
                        Leave this empty if the job has one price. Fill it in and we quote
                        the range instead.
                    </span>
                </label>

                @error('priceMax')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit" size="default">
                    <span wire:loading.remove wire:target="savePrice">Add this price</span>
                    <span wire:loading wire:target="savePrice">Saving…</span>
                </x-ui.button>
            </form>
        @endif
    </div>

    {{-- The disclaimer — R13 requires it with every quote. --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What we say with every price</h2>
        <p class="mt-2 text-base text-ink" data-testid="disclaimer-in-force">{{ $disclaimerInForce }}</p>
        <p class="mt-1 text-sm text-ink-2">
            @if ($disclaimerIsTheirOwn)
                Your words. Clear the box and save to go back to ours.
            @else
                Ours, until you write your own. It goes out with every price we quote.
            @endif
        </p>

        @if ($mayEdit)
            <form wire:submit="saveDisclaimer" class="mt-4 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Your own line</span>
                    <input
                        type="text"
                        wire:model="disclaimer"
                        placeholder="{{ $disclaimerInForce }}"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                </label>

                @error('disclaimer')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit" size="default">
                    <span wire:loading.remove wire:target="saveDisclaimer">Save this line</span>
                    <span wire:loading wire:target="saveDisclaimer">Saving…</span>
                </x-ui.button>
            </form>
        @endif
    </div>

    {{-- Doc ingest, and the review that stands between it and a quote. --}}
    @if ($mayEdit)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Have a price sheet already?</h2>
            <p class="mt-1 text-base text-ink-2">
                Upload it and we will read what we can — one job and its price per line.
                <strong class="font-medium text-ink">Nothing we read is quoted until you say so.</strong>
            </p>

            <form wire:submit="uploadSheet" class="mt-4 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Your price sheet</span>
                    <input
                        type="file"
                        wire:model="sheet"
                        accept=".txt,.csv,.md,.markdown"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                    {{--
                        THE SIZE JOINS THE FILE TYPE (9981). The type was named
                        and the limit was not, so the one refusal an owner can
                        actually provoke — `sheet.max`, at 256KB — arrived as a
                        surprise. Its sibling screens both name theirs.
                    --}}
                    <span class="text-sm text-ink-2">A plain text or CSV file, up to 256KB.</span>
                </label>

                {{--
                    ⚠️ THE TWO SIBLING UPLOAD SCREENS BOTH CARRY THIS AND THIS
                    ONE DID NOT (9982). Reading a price sheet is the slowest of
                    the three, and between picking the file and the sheet being
                    read this panel said nothing at all — which is the same
                    silence this slice exists to remove, arriving from the other
                    direction.
                --}}
                <div wire:loading wire:target="sheet" class="text-base text-ink-2">Reading your file…</div>

                @error('sheet')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit" size="default">
                    <span wire:loading.remove wire:target="uploadSheet">Read my price sheet</span>
                    <span wire:loading wire:target="uploadSheet">Reading…</span>
                </x-ui.button>
            </form>
        </div>
    @endif

    @if ($proposals !== [])
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5" data-testid="proposals">
            <h2 class="font-display text-lg font-semibold text-ink">Waiting for you to check</h2>
            <p class="mt-1 text-base text-ink-2">
                We read these off your price sheet. <strong class="font-medium text-ink">We will not
                quote any of them</strong> until you confirm it.
            </p>

            <div class="mt-4 space-y-3">
                {{--
                    empty-state: absent because a business with nothing waiting has
                    nothing to review, and an invitation to review nothing is an
                    instruction to upload a price sheet — which is the block
                    immediately above this one, already on screen. A permanent "no
                    proposals" panel would also read as reassurance that an upload
                    produced nothing, on a screen where an upload usually produces
                    something.
                --}}
                @foreach ($proposals as $proposal)
                    <div
                        class="flex flex-col gap-1 border-b border-rule pb-3 last:border-0 last:pb-0"
                        data-testid="proposal-{{ $proposal->slug }}"
                    >
                        <span class="text-base text-ink">{{ $proposal->label }}</span>
                        @php $proposedUpper = $proposal->upperAmount(); @endphp
                        <span class="font-mono text-base text-ink">
                            {{ \App\Support\PlanPricing::format($proposal->amount()) }}@if ($proposedUpper !== null) – {{ \App\Support\PlanPricing::format($proposedUpper) }}@endif
                        </span>

                        @if ($mayEdit)
                            <span class="flex flex-wrap gap-3">
                                <x-ui.button
                                    size="default"
                                    wire:click="confirmProposal('{{ $proposal->slug }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="confirmProposal('{{ $proposal->slug }}')"
                                >Quote this</x-ui.button>

                                <x-ui.button
                                    variant="quiet"
                                    size="default"
                                    wire:click="removePrice('{{ $proposal->slug }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="removePrice('{{ $proposal->slug }}')"
                                >Not this one</x-ui.button>
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($mayEdit)
                <div class="mt-4">
                    <x-ui.button
                        variant="quiet"
                        size="default"
                        wire:click="discardProposals"
                        wire:loading.attr="disabled"
                        wire:target="discardProposals"
                    >Throw all of these away</x-ui.button>
                </div>
            @endif
        </div>
    @endif

    {{-- Urgent terms and the emergency line — skill 9. --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What counts as urgent</h2>
        <p class="mt-1 text-base text-ink-2">
            When somebody uses one of these words we come straight to you, whatever time
            it is. Use the words your customers actually use — a lockout, a leak, a
            flood, no heat.
        </p>

        <div class="mt-4 space-y-3">
            @forelse ($terms as $term)
                <div
                    class="flex flex-col gap-1 border-b border-rule pb-3 last:border-0 last:pb-0"
                    data-testid="urgent-term"
                >
                    <span class="text-base text-ink">{{ $term }}</span>

                    @if ($mayEdit)
                        <span>
                            {{--
                                ⛔ `@js` RATHER THAN `'{{ … }}'`, AND THIS IS THE FIRST
                                PLACE IN THE APPLICATION THAT NEEDS IT. Every other
                                `wire:click="method('{{ … }}')"` here passes a slug, a
                                registry key or a place id — machine-shaped strings with
                                no quote in them. An urgent term is the tenant's own
                                words, so "won't start" closes the Livewire expression
                                early: Blade escapes the apostrophe to its HTML entity,
                                the parser turns it back into an apostrophe before
                                Livewire sees it, and the button silently stops working
                                on exactly the terms a person would write. `Js::from`
                                emits a properly escaped JavaScript literal instead.

                                ⚠️ AND THE ENTITY IS NOT SPELLED OUT IN THIS COMMENT ON
                                PURPOSE: `ComponentLibraryTest`'s colour lint greps every
                                view for `#` followed by three to eight hex digits, and a
                                numeric character reference is exactly that shape. It
                                reddened the build on this paragraph.

                                ⚠️ AND `wire:target` NAMES THE METHOD RATHER THAN THE
                                CALL, for the same reason plus one: a target has to match
                                the call Livewire dispatched, and matching it by a quoted
                                argument is one more place the quoting has to agree.
                            --}}
                            <x-ui.button
                                variant="quiet"
                                size="default"
                                wire:click="removeUrgentTerm({{ Illuminate\Support\Js::from($term) }})"
                                wire:loading.attr="disabled"
                                wire:target="removeUrgentTerm"
                            >Stop treating this as urgent</x-ui.button>
                        </span>
                    @endif
                </div>
            @empty
                <x-ui.empty-state icon="!">
                    No urgent words yet. Every message will be handled the same way, and we
                    will still tell you about anything we cannot answer.
                </x-ui.empty-state>
            @endforelse
        </div>

        @if ($mayEdit)
            <form wire:submit="addUrgentTerm" class="mt-5 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">A word that means drop everything</span>
                    <input
                        type="text"
                        wire:model="urgentTerm"
                        placeholder="lockout"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                </label>

                @error('urgentTerm')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit" size="default">
                    <span wire:loading.remove wire:target="addUrgentTerm">Add this word</span>
                    <span wire:loading wire:target="addUrgentTerm">Saving…</span>
                </x-ui.button>
            </form>
        @endif
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Your emergency number</h2>

        @if ($emergency === null)
            <p class="mt-2 text-base text-ink-2">
                Not set. When something is urgent we come straight to you and give nobody a
                number to ring.
            </p>
        @else
            <p class="mt-2 font-mono text-base text-ink" data-testid="emergency-line">{{ $emergency }}</p>
        @endif

        @if ($mayEdit)
            <form wire:submit="saveEmergencyLine" class="mt-4 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">The number to give out</span>
                    <input
                        type="tel"
                        inputmode="tel"
                        wire:model="emergencyLine"
                        placeholder="555 123 4567"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 font-mono text-base text-ink"
                    />
                    <span class="text-sm text-ink-2">Clear it and save if you would rather we did not give one out.</span>
                </label>

                @error('emergencyLine')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit" size="default">
                    <span wire:loading.remove wire:target="saveEmergencyLine">Save this number</span>
                    <span wire:loading wire:target="saveEmergencyLine">Saving…</span>
                </x-ui.button>
            </form>
        @endif
    </div>
</div>

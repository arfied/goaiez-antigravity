{{--
    What your assistant can send (T176 §2.4, P6).

    ⛔ EVERY EMPTY SECTION SAYS WHAT STAYS SWITCHED OFF, IN THE ENUM'S OWN WORDS.
    R13 makes a missing link mean the skill is absent — the assistant takes
    preferred times rather than booking — and an owner who cannot see that is an
    owner who believes appointments are being made. `missingCapability()` is one
    sentence per kind and it is rendered here rather than re-written, so the
    screen and the prompt cannot come to disagree.

    COLOUR IS NEVER THE SIGNAL (`22`). What is set and what is not is carried by
    the sentence under each heading, never by a hue or a dot.

    WORKS AT 320px — every section is a stacked block, the documents are a list
    rather than a table, and nothing is below 16px except the supporting lines.

    ⚠️ THE DESTINATION IS SHOWN AND THAT IS THE ONE PLACE IT MAY BE. `TenantLink`
    keeps the raw URL behind `destination()` because a message must carry a short
    link (R14); showing a business its own settings is the exception the method's
    own docblock names, and this is that screen.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What your assistant can send</h1>
        <p class="mt-1 text-base text-ink-2">
            When somebody texts you, we can hand them your booking page, your payment
            page or anything you share with customers. Give us the addresses and we use
            them; leave one out and we take a message instead.
        </p>
    </div>

    @unless ($mayEdit)
        {{--
            Named rather than hidden, and never a disabled control — `Account\Knowledge`'s
            reasoning: a greyed-out button reads as a bug in our page, a sentence
            naming who can do this reads as the truth and says who to ask.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Someone else sets these</h2>
            <p class="mt-2 text-base text-ink-2">
                These decide where we send your customers, so only an owner or a manager
                can change them. You can see everything below.
            </p>
        </div>
    @endunless

    {{-- Booking — skill 5. --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Where people book</h2>

        @if ($booking === null)
            <p class="mt-2 text-base text-ink-2">
                Not set. {{ \App\Enums\TenantLinkKind::Booking->missingCapability() }}
            </p>
        @else
            <p class="mt-2 break-all text-base text-ink" data-testid="booking-destination">{{ $booking->destination() }}</p>
        @endif

        @if ($mayEdit)
            <form wire:submit="saveBooking" class="mt-4 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Your booking page</span>
                    <input
                        type="url"
                        inputmode="url"
                        wire:model="bookingUrl"
                        placeholder="https://"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                </label>

                @error('bookingUrl')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button type="submit" size="default">
                        <span wire:loading.remove wire:target="saveBooking">Save booking page</span>
                        <span wire:loading wire:target="saveBooking">Saving…</span>
                    </x-ui.button>

                    @if ($booking !== null)
                        <x-ui.button
                            variant="quiet"
                            size="default"
                            wire:click="removeBooking"
                            wire:loading.attr="disabled"
                            wire:target="removeBooking"
                        >
                            <span wire:loading.remove wire:target="removeBooking">Stop sending it</span>
                            <span wire:loading wire:target="removeBooking">Removing…</span>
                        </x-ui.button>
                    @endif
                </div>
            </form>
        @endif
    </div>

    {{-- Payment and the call-out fee — skill 6. --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Where people pay</h2>

        @if ($payment === null)
            <p class="mt-2 text-base text-ink-2">
                Not set. {{ \App\Enums\TenantLinkKind::Payment->missingCapability() }}
            </p>
        @else
            <p class="mt-2 break-all text-base text-ink" data-testid="payment-destination">{{ $payment->destination() }}</p>

            @unless ($grounded[\App\Enums\TenantLinkKind::Payment->value])
                {{--
                    ⚠️ THE HALF-SET STATE HAS ITS OWN SENTENCE, BECAUSE IT IS THE
                    ONE AN OWNER WOULD OTHERWISE READ AS FINISHED. R13 grounds
                    skill 6 on the fee *and* the link; with a link and no fee we
                    can take somebody to the page and cannot say what it costs.
                --}}
                <p class="mt-2 text-base text-ink-2" data-testid="fee-missing">
                    We can send people here to pay. We will not name a call-out charge
                    until you set one below.
                </p>
            @endunless
        @endif

        @if ($mayEdit)
            <form wire:submit="savePayment" class="mt-4 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Your payment page</span>
                    <input
                        type="url"
                        inputmode="url"
                        wire:model="paymentUrl"
                        placeholder="https://"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                </label>

                @error('paymentUrl')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">What you charge to come out</span>
                    <input
                        type="text"
                        inputmode="decimal"
                        wire:model="fee"
                        placeholder="85"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 font-mono text-base text-ink"
                    />
                    <span class="text-sm text-ink-2">
                        Leave this blank if you do not charge to come out — we will not
                        mention a charge at all. Put 0 if you come out free and want us to
                        say so.
                    </span>
                </label>

                @error('fee')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">What that covers</span>
                    <input
                        type="text"
                        wire:model="feeCovers"
                        placeholder="Coming out, looking at the job and quoting"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                    <span class="text-sm text-ink-2">One line. We say it whenever we name the charge.</span>
                </label>

                @error('feeCovers')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button type="submit" size="default">
                        <span wire:loading.remove wire:target="savePayment">Save payment page</span>
                        <span wire:loading wire:target="savePayment">Saving…</span>
                    </x-ui.button>

                    @if ($payment !== null)
                        <x-ui.button
                            variant="quiet"
                            size="default"
                            wire:click="removePayment"
                            wire:loading.attr="disabled"
                            wire:target="removePayment"
                        >
                            <span wire:loading.remove wire:target="removePayment">Stop sending it</span>
                            <span wire:loading wire:target="removePayment">Removing…</span>
                        </x-ui.button>
                    @endif
                </div>
            </form>
        @endif
    </div>

    {{-- Shared documents — skill 7. --}}
    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">What you share with customers</h2>
        <p class="mt-1 text-base text-ink-2">
            A price sheet, a brochure, your licence — anything that already lives on the
            web. Name it the way a customer would ask for it.
        </p>

        <div class="mt-4 space-y-3">
            @forelse ($documents as $slug => $document)
                <div
                    class="flex flex-col gap-1 border-b border-rule pb-3 last:border-0 last:pb-0"
                    data-testid="document-{{ $slug }}"
                >
                    <span class="text-base text-ink">{{ $document->label }}</span>
                    <span class="break-all text-sm text-ink-2">{{ $document->destination() }}</span>

                    @if ($mayEdit)
                        <span>
                            <x-ui.button
                                variant="quiet"
                                size="default"
                                wire:click="removeDocument('{{ $slug }}')"
                                wire:loading.attr="disabled"
                                wire:target="removeDocument('{{ $slug }}')"
                            >Stop sharing this</x-ui.button>
                        </span>
                    @endif
                </div>
            @empty
                {{--
                    No action on the empty state: the form that adds one is on this
                    same screen, a few centimetres below, and a button that scrolls
                    somebody to something already in front of them is furniture —
                    `Account\Knowledge`'s call, for the same reason.
                --}}
                <x-ui.empty-state icon="◫">
                    Nothing shared yet. {{ \App\Enums\TenantLinkKind::Document->missingCapability() }}
                </x-ui.empty-state>
            @endforelse
        </div>

        @if ($mayEdit)
            <form wire:submit="addDocument" class="mt-5 space-y-4">
                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">What is it called</span>
                    <input
                        type="text"
                        wire:model="documentName"
                        placeholder="Price sheet"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                </label>

                @error('documentName')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <label class="flex flex-col gap-1">
                    <span class="text-sm font-medium text-ink-2">Where it lives</span>
                    <input
                        type="url"
                        inputmode="url"
                        wire:model="documentUrl"
                        placeholder="https://"
                        class="min-h-11 rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                    />
                </label>

                @error('documentUrl')
                    <p class="text-base text-alert" role="alert">{{ $message }}</p>
                @enderror

                <x-ui.button type="submit" size="default">
                    <span wire:loading.remove wire:target="addDocument">Share this</span>
                    <span wire:loading wire:target="addDocument">Adding…</span>
                </x-ui.button>
            </form>
        @endif
    </div>
</div>

{{--
    Who a phone number is, starting from the phone number — wave 40 lane C,
    decision 10880.

    A carrier forwards "+1555… says they never agreed to be texted by you." Every
    screen in this console was keyed on a business number, so the one question a
    carrier actually asks could only be answered in `tinker`.

    IT SHOWS AND IT DOES NOTHING ELSE. No stop, no start, no lift, no
    correction — releasing somebody from a suppression is a named authority with
    an audit row, and it does not belong one click from a text box whose subject
    is a stranger's phone number.

    COLOUR IS NOT THE SIGNAL (`22`). Every state on this page is a word first;
    `x-ui.status-pill` carries an icon and a label and has no colour-only
    variant.

    ⚠️ THE TYPED NUMBER IS NEVER STORED. What is recorded is the ordinary
    `business.viewed_by_staff` entry in each resolved tenant's own audit log —
    see the component.

    ⚠️ WHAT THIS PAGE CANNOT SAY IS AT THE FOOT AND IS NOT DECORATION. A screen
    that answered for owner numbers while looking as though it answered for all
    of them would be worse than no screen.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">What we know about a number</h1>
        <p class="mt-1 max-w-2xl text-base text-ink-2">
            Start from the phone number, which is usually the only thing a
            carrier gives you. This page shows what is on record against it,
            whether any business has registered it as its account holder's
            mobile, and whether it is one of our own sending numbers.
        </p>
    </div>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Find a number</h2>

        <p class="mt-1 max-w-2xl text-base text-ink-2">
            Any form you were given — <span class="font-mono">+15125559999</span>,
            <span class="font-mono">512-555-9999</span> or
            <span class="font-mono">(512) 555 9999</span> all reach the same
            record.
        </p>

        <form wire:submit="lookUp" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block" for="number-lookup">
                <span class="text-sm text-ink-2">Phone number</span>
                <input
                    wire:model="lookup"
                    id="number-lookup"
                    type="text"
                    inputmode="tel"
                    autocomplete="off"
                    class="mt-1 w-full min-w-0 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink sm:w-64"
                >
            </label>

            <x-ui.submit size="default" target="lookUp" busy="Looking…">Show what we know</x-ui.submit>
        </form>
    </section>

    @if ($e164)
        <div>
            <h2 class="font-display text-xl font-semibold text-ink">
                <span class="font-mono">{{ $e164 }}</span>
            </h2>
        </div>

        @unless ($readable)
            {{--
                ⛔ THE ARM THAT IS EASY TO MISS. Every row below is matched on a
                keyed digest; after an APP_KEY rotation every stored hash is
                unmatchable at once, and an empty panel would read as "we have
                no record of this number" — the worst available answer to a
                carrier, and indistinguishable from the true one.
            --}}
            <x-ui.attention-card state="alert" heading="We cannot answer for this number right now">
                Two encryption keys have written records to this platform and one
                set of them can no longer be read, so a stop or a register
                listing recorded under the older key would not be found. Nothing
                below is being shown, because an empty page here would read as
                "we have never heard of this number". Sending is already being
                refused platform-wide for the same reason — see
                <a class="underline" href="{{ route('admin.sending-controls') }}">Sending</a>.
            </x-ui.attention-card>
        @else
            <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <h3 class="font-display text-lg font-semibold text-ink">What is on record against this number</h3>

                <p class="mt-1 max-w-2xl text-base text-ink-2">
                    Refusals that bind the whole platform: a STOP, a complaint, a
                    bounce, or a never-contact instruction. Each one stays on
                    record for ever; a release is a second row beside it rather
                    than a deletion.
                </p>

                @forelse ($record as $event)
                    <div wire:key="{{ $event['key'] }}" class="mt-4 border-t border-rule pt-4 first:mt-3 first:border-0 first:pt-0">
                        <p class="text-base text-ink">
                            @if ($event['kind'] === 'refused')
                                <x-ui.status-pill state="alert" label="Refused" />
                            @else
                                <x-ui.status-pill state="ok" label="Released" />
                            @endif
                            <span class="ml-2">{{ $event['what'] }}</span>
                        </p>
                        <p class="mt-1 text-base text-ink-2">
                            Recorded {{ $event['when'] }}@if ($event['who']), by <span class="font-mono">{{ $event['who'] }}</span>@endif.
                        </p>
                        @if ($event['note'])
                            <p class="mt-1 text-base text-ink-2">{{ $event['note'] }}</p>
                        @endif
                    </div>
                @empty
                    <x-ui.empty-state class="mt-4" heading="Nothing on record">
                        No platform-wide refusal has ever been recorded against
                        this number, and nothing has ever been released for it.
                        If a carrier says somebody asked us to stop, we have no
                        record of it arriving.
                    </x-ui.empty-state>
                @endforelse
            </section>

            <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <h3 class="font-display text-lg font-semibold text-ink">The registers we scrub against</h3>

                @if ($blocksEverything === [] && $blocksMarketing === [])
                    <p class="mt-1 max-w-2xl text-base text-ink-2">
                        This number is on none of the registers we hold.
                    </p>
                @else
                    <ul class="mt-3 space-y-2">
                        {{--
                            empty-state: absent because the sibling branch of the
                            conditional this loop sits in IS the empty state, in
                            a full sentence — "This number is on none of the
                            registers we hold" — and a dashed invitation card
                            would be the wrong shape for it. There is nothing to
                            invite anybody to do: nobody adds a register listing
                            from this console, they are imported from a paid
                            extract by `compliance:load-suppressions`.
                        --}}
                        @foreach ($blocksEverything as $sentence)
                            <li wire:key="ref-t-{{ $loop->index }}" class="text-base text-ink">
                                <x-ui.status-pill state="alert" label="Listed" />
                                <span class="ml-2">On {{ $sentence }} — nothing may be sent to it, including a review request.</span>
                            </li>
                        @endforeach

                        {{--
                            empty-state: absent because the sibling branch of the
                            conditional this loop sits in IS the empty state, in
                            a full sentence — "This number is on none of the
                            registers we hold" — and a dashed invitation card
                            would be the wrong shape for it. There is nothing to
                            invite anybody to do: nobody adds a register listing
                            from this console, they are imported from a paid
                            extract by `compliance:load-suppressions`.
                        --}}
                        @foreach ($blocksMarketing as $sentence)
                            <li wire:key="ref-m-{{ $loop->index }}" class="text-base text-ink">
                                <x-ui.status-pill state="attention" label="Listed" />
                                <span class="ml-2">On {{ $sentence }} — no marketing may be sent to it.</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($neverLoaded !== null)
                    {{--
                        ⛔ 1600's DISTINCTION, ON THE PAGE. "Not on any register
                        we hold" over an empty register means nobody has ever
                        scrubbed this number, which is a different sentence from
                        "we checked and it is not listed" — and the second is
                        what an operator would otherwise send to a carrier.
                    --}}
                    <div class="mt-4">
                        <x-ui.attention-card state="attention" heading="Some registers have never been imported">
                            We hold no rows at all for {{ $neverLoaded }}.
                            For those, "not listed" means nobody has ever
                            checked — not that this number was checked and found
                            clean.
                        </x-ui.attention-card>
                    </div>
                @endif
            </section>
        @endunless

        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h3 class="font-display text-lg font-semibold text-ink">An account holder's own mobile</h3>

            <p class="mt-1 max-w-2xl text-base text-ink-2">
                Whether a business has registered this number to be texted about
                its own account. One person can own two businesses and use one
                mobile for both, so this is a list.
            </p>

            @forelse ($owners as $owner)
                <div wire:key="owner-{{ $owner['id'] }}" class="mt-4 border-t border-rule pt-4 first:mt-3 first:border-0 first:pt-0">
                    <p class="text-base text-ink">
                        <span class="font-semibold">{{ $owner['name'] }}</span>
                        — business {{ $owner['id'] }}
                    </p>
                    <p class="mt-1 text-base text-ink-2">
                        @if ($owner['stopped'])
                            <x-ui.status-pill state="attention" label="Stopped" />
                            <span class="ml-2">We do not text this number about this account.</span>
                        @else
                            <x-ui.status-pill state="ok" label="Live" />
                            <span class="ml-2">This is the number we would text about this account.</span>
                        @endif
                    </p>
                    <p class="mt-2 text-base">
                        <a class="underline text-ink" href="{{ route('admin.owner-notify-consents') }}">
                            Open the consent record for business {{ $owner['id'] }}
                        </a>
                        — every disclosure they were shown, and when.
                    </p>
                </div>
            @empty
                <x-ui.empty-state class="mt-4" heading="Not an account holder's number">
                    No business has registered this number to be texted about its
                    own account. If a carrier is asking about an owner-channel
                    text, it did not come from here.
                </x-ui.empty-state>
            @endforelse
        </section>

        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h3 class="font-display text-lg font-semibold text-ink">One of our own numbers</h3>

            <p class="mt-1 max-w-2xl text-base text-ink-2">
                A carrier complaint quotes two numbers — the person's, and the
                one that texted them. This says whether the number you typed is
                the second kind.
            </p>

            @forelse ($ours as $index => $row)
                <div wire:key="ours-{{ $index }}" class="mt-4 border-t border-rule pt-4 first:mt-3 first:border-0 first:pt-0">
                    <p class="text-base text-ink">{{ $row['state'] }}</p>
                    <p class="mt-1 text-base text-ink-2">{{ $row['numberRole'] }}. {{ $row['lane'] }}.</p>
                    <p class="mt-1 text-base text-ink-2">
                        @if ($row['business'] !== null)
                            Registered to {{ $row['business'] }} — business {{ $row['businessId'] }}.
                        @else
                            Not registered to any business.
                        @endif
                    </p>
                </div>
            @empty
                <x-ui.empty-state class="mt-4" heading="Not one of our numbers">
                    This is not one of our numbers. It has never been in our
                    sending inventory, so nothing we sent came from it.
                </x-ui.empty-state>
            @endforelse

            @if ($ours !== [])
                <p class="mt-4 text-sm text-ink-3">
                    Every state this number has ever been moved through, and who
                    moved it, is <span class="font-mono">php artisan numbers:history {{ $e164 }}</span>.
                </p>
            @endif
        </section>
    @endif

    {{--
        ⛔ THE BOUNDARY IS STATED, ALWAYS, WHETHER OR NOT A NUMBER IS IN VIEW.
        A screen that answers for owner numbers while looking as though it
        answers for all of them is worse than no screen.
    --}}
    <section class="rounded-[--radius-panel] border border-rule bg-paper p-5">
        <h3 class="font-display text-base font-semibold text-ink">What this page cannot tell you</h3>

        <ul class="mt-2 max-w-2xl list-disc space-y-2 pl-5 text-base text-ink-2">
            <li>
                <span class="text-ink">Whether this number belongs to any
                business's customer.</span> Customer records are readable only as
                the business that owns them, so answering would mean walking
                every business on the platform. Nothing here does that.
            </li>
            <li>
                <span class="text-ink">Whether a single business has recorded its
                own refusal for this number.</span> Those records are the
                business's, held under the same rule, and they are deliberately
                not assembled here: doing it would produce a list of the
                businesses this person deals with, which is not a question a
                carrier complaint asks.
            </li>
            <li>
                <span class="text-ink">Whether we ever actually sent anything to
                it.</span> Sent messages are filed against a customer record
                rather than against a number, so a message can only be found from
                the business it was sent for.
            </li>
            <li>
                <span class="text-ink">Whether a particular message would be sent
                today.</span> That is decided per business, per purpose, at the
                moment of sending, and it reads several things this page cannot
                see. What is above is the record, not the decision.
            </li>
        </ul>
    </section>
</div>

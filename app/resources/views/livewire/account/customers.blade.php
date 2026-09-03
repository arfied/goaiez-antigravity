{{--
    The customer list (`34` §1.1).

    ⚠️ NO LIFETIME-VALUE COLUMN, AND THAT IS §1.1's OWN INSTRUCTION RATHER THAN A
    CUT: "when POS/pay data exists, else hidden — never a $0 column". Nothing
    writes `value_to_date_cents`, so the column would tell every owner that every
    customer they have is worth nothing.

    COLOUR IS NOT THE SIGNAL (`22`). The consent state is words, not a dot.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your customers</h1>
        <p class="mt-1 text-base text-ink-2">
            Everyone who has been in touch, and everyone you have imported.
        </p>
    </div>

    @if ($noneAtAll)
        {{--
            ⚠️ "Nobody yet" is a different statement from "nothing matched", and
            this branch exists so the two never share wording. §1.1 makes this
            state an invitation into Import — which would be wrong advice for
            somebody whose search simply missed, because they would re-import
            contacts they already have.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">No customers yet</h2>
            <p class="mt-2 text-base text-ink-2">
                Customers appear here as soon as someone leaves you feedback. You can
                also bring in a list you already have.
            </p>
            <p class="mt-4">
                <a href="{{ route('account.customers.import') }}" class="text-base font-medium text-ink underline">
                    Import your customers
                </a>
            </p>
        </div>
    @else
        <div>
            <label for="customer-search" class="block text-sm font-medium text-ink">
                Search
            </label>
            <input
                id="customer-search"
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Name, email or phone"
                class="mt-1 w-full rounded-[--radius-field] border border-rule bg-card px-3 py-2 text-base text-ink"
            >
        </div>

        {{--
            §1.1's "Needs follow-up" chip — open triage or an open follow-up.
            Missed calls are the third leg of §1.1's definition and have no
            source until voice exists, so the chip filters what is true rather
            than claiming the full definition. A pressed chip says so in words
            and in aria-pressed, never by colour alone (`22`).
        --}}
        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                wire:click="$toggle('needsFollowUp')"
                aria-pressed="{{ $needsFollowUp ? 'true' : 'false' }}"
                @class([
                    'flex min-h-11 items-center rounded-full border border-rule px-4 text-base',
                    'bg-ink font-medium text-paper' => $needsFollowUp,
                    'bg-card text-ink-2 hover:text-ink' => ! $needsFollowUp,
                ])
            >Needs follow-up{{ $needsFollowUp ? ' — showing only these' : '' }}</button>

            {{--
                The archived view (`44` §8) and the Recently deleted one (1540).
                Each door renders only while its room has somebody in it —
                restorable means findable, and a hidden contact with no door back
                is a trap. The deleted door also closes on its own after seven
                days, because by then there is nothing to be done in that room.
            --}}
            @php($archived = $view === \App\Enums\ContactView::Archived)
            @php($recentlyDeleted = $view === \App\Enums\ContactView::RecentlyDeleted)

            @if ($hasArchived || $archived)
                <button
                    type="button"
                    wire:click="$set('view', '{{ $archived ? '' : \App\Enums\ContactView::Archived->value }}')"
                    aria-pressed="{{ $archived ? 'true' : 'false' }}"
                    @class([
                        'flex min-h-11 items-center rounded-full border border-rule px-4 text-base',
                        'bg-ink font-medium text-paper' => $archived,
                        'bg-card text-ink-2 hover:text-ink' => ! $archived,
                    ])
                >{{ $archived ? 'Showing archived customers' : 'Archived customers' }}</button>
            @endif

            @if ($hasRecentlyDeleted || $recentlyDeleted)
                <button
                    type="button"
                    wire:click="$set('view', '{{ $recentlyDeleted ? '' : \App\Enums\ContactView::RecentlyDeleted->value }}')"
                    aria-pressed="{{ $recentlyDeleted ? 'true' : 'false' }}"
                    @class([
                        'flex min-h-11 items-center rounded-full border border-rule px-4 text-base',
                        'bg-ink font-medium text-paper' => $recentlyDeleted,
                        'bg-card text-ink-2 hover:text-ink' => ! $recentlyDeleted,
                    ])
                >{{ $recentlyDeleted ? 'Showing recently deleted' : 'Recently deleted' }}</button>
            @endif
        </div>

        {{--
            §1.2's loading skeleton, with the honest label the component
            requires. It replaces the list rather than sitting beside it, so
            what an owner sees while a search runs is one answer rather than the
            previous answer with a spinner over it.
        --}}
        <div wire:loading.delay wire:target="search, view, needsFollowUp, gotoPage, previousPage, nextPage">
            <x-ui.skeleton label="Loading your customers…" :lines="3" />
        </div>

        <div wire:loading.delay.remove wire:target="search, view, needsFollowUp, gotoPage, previousPage, nextPage">

        @if ($customers->isEmpty())
            {{--
                FIVE SENTENCES RATHER THAN ONE, AND NOT ALL OF THEM CARRY AN
                ACTION. Which view is on decides what "empty" means here, and a
                single sentence covering all five would be true of none of them.
                The four filtered views can offer the way back to the whole list;
                the fifth is a genuinely empty address book, where the only
                honest thing to say is how names arrive, since nothing on this
                screen adds one by hand.
            --}}
            <x-ui.empty-state icon="○">
                @if ($archived)
                    No archived customers.
                @elseif ($recentlyDeleted)
                    {{--
                        Names the window rather than saying "none", because
                        a tenant who deleted somebody eight days ago is
                        looking at an empty room they were in last week and
                        the reason is the clock, not their memory.
                    --}}
                    No customers deleted in the last {{ \App\Services\Crm\CustomerEditor::RESTORE_WINDOW_DAYS }} days.
                @elseif ($needsFollowUp)
                    Nobody needs a follow-up right now.
                @elseif (trim($search) === '')
                    {{--
                        Reachable since delete landed: a tenant whose only
                        contacts are archived or deleted has an empty list
                        and an empty search box, and "Nothing matched
                        &ldquo;&rdquo;" would be a sentence about a search
                        nobody ran.
                    --}}
                    Nobody is on your list right now. They appear as customers reach you
                    — a call, a form, a review — so there is nothing to add by hand.
                @else
                    Nothing matched “{{ $search }}”. Try part of a name, an email or a
                    phone number.
                @endif
            </x-ui.empty-state>
        @else
            <ul class="space-y-3">
                @foreach ($customers as $customer)
                    <li class="rounded-[--radius-panel] border border-rule bg-card p-5">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <a
                                href="{{ route('account.customers.show', $customer) }}"
                                class="text-base font-medium text-ink underline"
                                data-customer="{{ $customer->id }}"
                            >
                                {{ $customer->name ?: $customer->email ?: $customer->phone ?: 'A customer' }}
                            </a>
                            <p class="text-sm text-ink-2">
                                {{--
                                    §1.1 sorts on last activity, so the column that
                                    decides the order is the one shown. "Not yet"
                                    rather than a blank, because a blank reads as a
                                    rendering fault.
                                --}}
                                {{ $customer->last_activity_at?->diffForHumans() ?? 'No activity yet' }}
                            </p>
                        </div>

                        <p class="mt-1 text-base text-ink-2">
                            {{--
                                §1.1's phone column is tap-to-CALL, and no
                                tap-to-text affordance renders for any contact
                                (decision 1328): there is no SMS sender in app/
                                at all, so "where lane-legal" is false for every
                                contact and an sms: link would be a button that
                                cannot work. A test asserts the absence — it is
                                the one that reddens the day a sender lands.
                            --}}
                            @if ($customer->phone)
                                <a href="tel:{{ $customer->phone }}" class="underline">{{ $customer->phone }}</a>
                            @endif
                            @if ($customer->phone && $customer->email)
                                ·
                            @endif
                            @if ($customer->email)
                                {{ $customer->email }}
                            @endif
                            @if (! $customer->phone && ! $customer->email)
                                No contact details
                            @endif
                        </p>

                        @if ($customer->tags)
                            <p class="mt-2 flex flex-wrap gap-2" data-tags>
                                @foreach ($customer->tags as $tag)
                                    <span class="rounded-full border border-rule bg-paper px-2 py-0.5 text-sm text-ink-2">{{ $tag }}</span>
                                @endforeach
                            </p>
                        @endif

                        {{--
                            The consent badge §1.1 asks for, in words — built by
                            ConsentService::badgesFor(), the same method the
                            profile header renders from, so the two screens
                            cannot disagree. Standing suppression included: a
                            contact who said STOP no longer reads as "agreed".
                        --}}
                        @php $badge = $consentBadges[$customer->id] ?? null; @endphp
                        <p class="mt-3 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $badge && $badge->stopped !== [] ? 'bg-alert-bg text-alert' : ($badge && $badge->agreed !== [] ? 'bg-ok-bg text-ok' : 'bg-paper text-ink-2 border border-rule') }}" data-consent-badge>
                            {{ $badge?->label() ?? 'Hasn’t agreed to messages' }}
                        </p>

                        {{--
                            In the Recently deleted room only: when the undo
                            runs out. A date rather than "7 days", because the
                            owner is reading this on some later day than the one
                            they deleted on. Restore itself lives on the profile
                            — one control, not two that can disagree.
                        --}}
                        @if ($recentlyDeleted && $customer->deleted_at)
                            <p class="mt-2 text-sm text-ink-2" data-restorable-until>
                                Deleted {{ $customer->deleted_at->diffForHumans() }} — you can bring them
                                back until
                                {{ $customer->deleted_at->addDays(\App\Services\Crm\CustomerEditor::RESTORE_WINDOW_DAYS)->toFormattedDateString() }}.
                            </p>
                        @endif
                    </li>
                @endforeach
            </ul>

            {{ $customers->links() }}
        @endif
        </div>
    @endif
</div>

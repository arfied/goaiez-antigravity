{{--
    Connecting Google, per location (row 3 slice H).

    COLOUR IS NOT THE SIGNAL (`22`). Each location says its state in words —
    "Connected", "Needs reconnecting", "Not connected" — and which buttons are
    offered follows from it. Nothing here is told apart by a hue.

    OUTCOME LANGUAGE (`29` §2 rule 47). "Connect Google", "Google is connected".
    Never "OAuth", never the provider's name as a verb, and never "sync".

    WHO MAY PRESS WHAT (6480). Connect, Reconnect and Disconnect are behind
    `$mayManage` — `GbpConnectionPolicy::create()`, which is
    `UserRole::canManageConnections()` and excludes a manager as well as staff.
    "Check connection" is not, on purpose: it asks the provider a question and
    records the answer, and two background jobs already make the same call. The
    read-only sentence names who can do the rest, and the partner disclosure at
    the foot stays outside every branch so it is read by the person who works the
    inbox as well as by the person who agrees to it (2077).

    WHAT THE OWNER IS TOLD ABOUT THE PARTNER, and why it is on the page rather
    than in a policy nobody opens: connecting grants a third party read *and
    write* access to their Google Business Profile in one consent flow, and
    those scopes cannot be requested separately (SUBPROCESSOR-INVENTORY §2).
    Somebody agreeing to that should be able to read it where they agree to it.

    A SECOND, UNRELATED SECTION BELOW — "Google Search" — is Search Console's
    own door (wave 38, lane B). It is read-only by construction (decision 1083)
    and carries no dark-launch flag, so it renders whenever a location exists
    regardless of `$available` above.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Google reviews</h1>
        <p class="mt-1 text-base text-ink-2">
            Connect Google and your reviews appear here as customers leave them.
        </p>
    </div>

    @unless ($available)
        {{--
            The dark-launch state. It says the feature is not open yet rather
            than showing a button that ends in a vendor error the owner can do
            nothing about — and the service refuses independently of this, on
            decision 391's rule.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Not open yet</h2>
            <p class="mt-2 text-base text-ink-2">
                Connecting Google is not available on your account yet. We will let you
                know the moment it is — there is nothing for you to do.
            </p>
        </div>
    @else
        @unless ($mayManage)
            {{--
                ⚠️ NAMED, NOT HIDDEN, AND NOT A DISABLED BUTTON (6446). A greyed
                out control reads as a bug in our page; a sentence saying who can
                do this reads as the truth and tells them who to ask. It sits
                ABOVE the list because it is about every location at once, and it
                says what this person CAN still do rather than only what they
                cannot — checking a connection is open to everyone, and it is the
                answer to "why have no reviews arrived since Tuesday".
            --}}
            <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <p class="text-base text-ink-2">
                    Connecting Google gives our review partner access to your Business
                    Profile, so only an account owner can connect or disconnect it. You
                    can still check whether a connection is working.
                </p>
            </div>
        @endunless

        <div class="space-y-4">
            @forelse ($locations as $location)
                @php($connection = $connections->get($location->id))

                <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ $location->name }}</h2>

                    @if ($connection?->isUsable())
                        {{--
                            ⚠️ NOT `Connected@if (...)`. A Blade directive glued to
                            the preceding word is left as literal text, while its
                            `@endif` still compiles — so the stray `endif` closes
                            the enclosing branch and the next `@elseif` is a PHP
                            parse error twenty lines away. The compiled view is
                            the only place it is visible.
                        --}}
                        <p class="mt-2 text-base text-ink-2">
                            @if ($connection->external_label)
                                Connected as {{ $connection->external_label }}.
                            @else
                                Connected.
                            @endif
                        </p>

                        {{--
                            ⛔ WHAT OUR LAST LOOK AT THIS LISTING ACTUALLY DID
                            (10120–10139). "Connected." was the whole of what
                            this card said about a connection whose sync had
                            been failing for a week — and this is the screen an
                            owner opens to ask why no reviews have arrived since
                            Tuesday. The answer was already on the run row.

                            ⚠️ ABSENT WHENEVER THE LAST READ READ, and there is
                            deliberately no "we are reading your listing" here:
                            we can prove a sweep ran and a connection exists, and
                            we cannot prove Google answered, that the listing is
                            verified, or that one review has ever come back
                            (9921). Silence means only that nothing here is
                            stopping it.

                            ⚠️ COLOUR IS NOT THE SIGNAL (`22`) AND NEITHER IS AN
                            ICON. This is plain text in the body colour, on the
                            same footing as "Connected." above it: it is an
                            account of our own reading, not a fault in the
                            owner's listing and not a state they can act on —
                            every sentence it can carry is ours. A warning badge
                            would send somebody to check a Google listing that is
                            fine.
                        --}}
                        @isset ($reviewAbsences[$location->id])
                            {{--
                                ⚠️ `data-review-absence` CARRIES THE LOCATION ID
                                SO A TEST CAN BE LOAD-BEARING, and it is not
                                decoration — `data-proof-number`'s own reason on
                                the Home tile. A note keyed to the wrong location
                                would render one place's sync failure under
                                another place's name, and every document-wide
                                text assertion would pass. Rendered tight against
                                the tag on purpose: whitespace here would let an
                                incidental match back in.
                            --}}
                            <p class="mt-2 text-base text-ink-2" data-review-absence="{{ $location->id }}">{{ $reviewAbsences[$location->id] }}</p>
                        @endisset

                        <div class="mt-4 flex flex-wrap gap-3">
                            {{-- Open to everyone: `GbpConnectionPolicy::check()`. --}}
                            <x-ui.button
                                type="button"
                                wire:click="check({{ $connection->id }})"
                                wire:loading.attr="disabled"
                            >Check connection</x-ui.button>

                            @if ($mayManage)
                                <button
                                    type="button"
                                    class="text-base text-ink-2 underline"
                                    wire:click="disconnect({{ $connection->id }})"
                                    wire:loading.attr="disabled"
                                >Disconnect</button>
                            @endif
                        </div>
                    @elseif ($connection?->status === \App\Enums\GbpConnectionStatus::Disconnected)
                        <p class="mt-2 text-base text-ink-2">
                            Needs reconnecting. We are not reading your Google reviews for this
                            place at the moment.
                        </p>

                        @if ($mayManage)
                            <div class="mt-4">
                                <x-ui.button
                                    type="button"
                                    wire:click="connect({{ $location->id }})"
                                    wire:loading.attr="disabled"
                                >Reconnect Google</x-ui.button>
                            </div>
                        @endif
                    @else
                        <p class="mt-2 text-base text-ink-2">
                            Not connected.
                            @if ($connection)
                                You started connecting and did not finish — picking it up again
                                takes a moment.
                            @endif
                        </p>

                        @if ($mayManage)
                            <div class="mt-4">
                                <x-ui.button
                                    type="button"
                                    wire:click="connect({{ $location->id }})"
                                    wire:loading.attr="disabled"
                                >Connect Google</x-ui.button>
                            </div>
                        @endif
                    @endif
                </div>
            @empty
                {{--
                    A location arrives with the account, so an owner reading
                    this has hit something that went wrong upstream rather than
                    a step they skipped — and there is no button on this screen
                    that would create one. Saying who is dealing with it beats
                    an invitation to do the impossible.
                --}}
                <x-ui.empty-state icon="⌖">
                    There is nowhere to connect Google to yet. Your first location is set
                    up with your account — if this is still here tomorrow, tell us and we
                    will sort it out.
                </x-ui.empty-state>
            @endforelse
        </div>

        <p class="text-sm text-ink-2">
            Connecting sends you to Google to sign in. Our review partner handles the
            connection and can read and post to your Business Profile while it is
            connected. Disconnecting ends that access.
        </p>
    @endunless

    {{--
        GOOGLE SEARCH — the Search Console door (wave 38, lane B).

        SEPARATE FROM THE SECTION ABOVE ON PURPOSE. That one is gated on
        `gbp.zernio_enabled`, a dark-launch flag for the review partner; Search
        Console carries no such flag — the OAuth chain has been live since row
        15 slice 1 and simply had no link anywhere. So this section renders
        whenever there is at least one location, independent of `$available`.

        OUTCOME LANGUAGE, MATCHING THE SECTION ABOVE. "Connect Search Console",
        never "OAuth", never "authorize", never the scope name.

        ⚠️ NO HAND-WRITTEN `route('account.…')` LINK TO "How people find you"
        — `OwnerNavTest`'s rule: movement between owner screens goes through
        the shell nav (`OwnerNav` already lists it), never through a link typed
        into a page body. That was the pre-shell failure this lint exists to
        keep closed.
    --}}
    <div>
        <h2 class="font-display text-lg font-semibold text-ink">Google Search</h2>
        <p class="mt-1 text-base text-ink-2">
            Connect Search Console and choose which site is this location, to see
            search movement on "How people find you".
        </p>
    </div>

    @if ($gscConnectionState === null)
        {{-- No locations at all — see the empty state above for the same reasoning. --}}
        <x-ui.empty-state icon="⌖">
            There is nowhere to connect Search Console to yet. Your first location is
            set up with your account — if this is still here tomorrow, tell us and we
            will sort it out.
        </x-ui.empty-state>
    @else
        @unless ($mayManageSearchConsole)
            <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <p class="text-base text-ink-2">
                    Connecting Search Console gives us permission to read your Google
                    Search performance data, so only an account owner can connect it or
                    choose which site a location measures.
                </p>
            </div>
        @endunless

        @if ($gscConnectionState === \App\Services\Visibility\ConnectionState::Absent)
            <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <p class="text-base text-ink-2">Not connected.</p>

                @if ($mayManageSearchConsole)
                    <div class="mt-4">
                        <x-ui.button href="{{ route('gsc.connect.redirect') }}">Connect Search Console</x-ui.button>
                    </div>
                @endif
            </div>
        @elseif ($gscConnectionState === \App\Services\Visibility\ConnectionState::Unusable)
            <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <p class="text-base text-ink-2">
                    Needs reconnecting. We are not reading your Google Search
                    performance data at the moment.
                </p>

                @if ($mayManageSearchConsole)
                    <div class="mt-4">
                        <x-ui.button href="{{ route('gsc.connect.redirect') }}">Reconnect Search Console</x-ui.button>
                    </div>
                @endif
            </div>
        @else
            <div class="space-y-4">
                @forelse ($locations as $location)
                    @php($property = $gscProperties[$location->id] ?? null)

                    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
                        <h3 class="font-display text-lg font-semibold text-ink">{{ $location->name }}</h3>

                        @if ($property)
                            <p class="mt-2 text-base text-ink-2">Measuring {{ $property->site_url }}.</p>

                            @if ($mayManageSearchConsole)
                                <div class="mt-4 flex flex-wrap gap-3">
                                    <x-ui.button
                                        type="button"
                                        variant="secondary"
                                        size="default"
                                        wire:click="openSearchConsolePicker({{ $location->id }})"
                                        wire:loading.attr="disabled"
                                    >Change site</x-ui.button>

                                    <button
                                        type="button"
                                        class="text-base text-ink-2 underline"
                                        wire:click="clearSearchConsoleProperty({{ $location->id }})"
                                        wire:loading.attr="disabled"
                                    >Remove</button>
                                </div>
                            @endif
                        @else
                            <p class="mt-2 text-base text-ink-2">No site chosen yet.</p>

                            @if ($mayManageSearchConsole)
                                <div class="mt-4">
                                    <x-ui.button
                                        type="button"
                                        size="default"
                                        wire:click="openSearchConsolePicker({{ $location->id }})"
                                        wire:loading.attr="disabled"
                                    >Choose a site</x-ui.button>
                                </div>
                            @endif
                        @endif

                        {{--
                            ⚠️ `$gscAvailableProperties` IS THE LAST FETCH ONLY, AND
                            IT IS NEVER TRUSTED BACK — `chooseSearchConsoleProperty()`
                            re-asks Google before writing anything (decision 1083).
                            This `<select>` is convenience, not authority.
                        --}}
                        @if ($mayManageSearchConsole && $gscPropertyPickerLocationId === $location->id)
                            <div class="mt-4 space-y-3 border-t border-rule pt-4">
                                @if ($gscAvailableProperties === [])
                                    <p class="text-base text-ink-2">
                                        Your Google account has no Search Console sites we can
                                        measure yet.
                                    </p>
                                @else
                                    <div>
                                        <label
                                            for="gsc-site-{{ $location->id }}"
                                            class="block text-sm font-medium text-ink"
                                        >Site for {{ $location->name }}</label>

                                        <select
                                            id="gsc-site-{{ $location->id }}"
                                            wire:model="gscSelectedSiteUrl.{{ $location->id }}"
                                            class="mt-1 w-full rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                                        >
                                            <option value="">Choose a site</option>
                                            @foreach ($gscAvailableProperties as $option)
                                                {{--
                                                    ⚠️ NOT `{{ ... }}@if (...) ... @endif` — the class
                                                    docblock's own trap, for `@endif` rather than `@if`
                                                    this time: a directive glued to the preceding WORD
                                                    with no space is left as literal text while its
                                                    partner still compiles, and the stray directive
                                                    misaligns every `@endforeach`/`@endif` after it. One
                                                    echo, no directive, cannot be glued.
                                                --}}
                                                <option value="{{ $option['site_url'] }}" @disabled(! $option['readable'])>
                                                    {{ $option['site_url'].($option['readable'] ? '' : ' — needs verifying in Search Console') }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif

                                <div class="flex flex-wrap gap-3">
                                    <x-ui.button
                                        type="button"
                                        size="default"
                                        wire:click="chooseSearchConsoleProperty({{ $location->id }})"
                                        wire:loading.attr="disabled"
                                    >Save</x-ui.button>

                                    <button
                                        type="button"
                                        class="text-base text-ink-2 underline"
                                        wire:click="closeSearchConsolePicker"
                                        wire:loading.attr="disabled"
                                    >Cancel</button>
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    {{--
                        ⚠️ UNREACHABLE IN PRACTICE, KEPT FOR THE LINT AND FOR
                        SAFETY. `$gscConnectionState` is derived from
                        `$locations->first()`, so being non-null already implies
                        a location exists — but `ScreenStatesTest`'s own rule is
                        that a bare `@foreach` with no `@empty` is a report on a
                        query rather than an invitation, and it cannot see that
                        correlation. Same sentence as the empty state above.
                    --}}
                    <x-ui.empty-state icon="⌖">
                        There is nowhere to connect Search Console to yet. Your first
                        location is set up with your account — if this is still here
                        tomorrow, tell us and we will sort it out.
                    </x-ui.empty-state>
                @endforelse
            </div>
        @endif
    @endif
</div>

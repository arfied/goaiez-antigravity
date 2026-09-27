{{--
    Extra locations, added by an operator (T176 P25).

    Deliberately plain, like the PHI tenants screen — this exists so
    `Subscriptions::recordAdditionalLocations()` and `LocationProvisioner` have
    a caller. See the component for why it acts on one business at a time rather
    than listing them.

    ⛔ NOTHING ON THIS PAGE CHARGES ANYBODY, AND THE PAGE SAYS SO TWICE. The
    operator changes the recurring amount at the gateway, where the subscription
    lives; this records what our side was told. An operator who reads a button
    as having taken the money will not go and take it, and the customer gets the
    product for nothing — which is a worse failure than a double charge, because
    nothing anywhere will ever notice it.

    ⛔ AND THE TENANT CANNOT DO THIS THEMSELVES ON PURPOSE. T176 P25 puts
    self-serve and proration on the OUT list, because "what does adding a
    location mid-cycle cost" is open question K and is the owner's to answer
    (147–149). The tenant screen quotes the price and sends them to a person.

    THE SCREEN NEVER OFFERS AN ACTION THE SERVICE WILL REFUSE, where it can tell
    in advance: no set-up form while the plan has no room. ⚠️ The inverse is not
    claimed — `recordAdditionalLocations()` refuses a subscription with no
    stored price and this page cannot tell in advance, so that refusal is shown
    as the service's own sentence rather than pre-empted.

    COLOUR IS NOT THE SIGNAL (`22`). Every state here is a word.

    ⚠️ LOCATION NAMES CAN BE PERSONAL (334) and appear only in this panel,
    behind the platform-staff gate. They are never in a toast or a URL.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Extra locations</h1>
        <p class="mt-1 text-base text-ink-2">
            What a plan covers, and setting up the locations it has paid for.
        </p>
    </div>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Find a business</h2>

        <p class="mt-1 text-base text-ink-2">
            By its number, or by the owner’s email address. There is no list — see the note at the foot of this page.
        </p>

        <form wire:submit="lookUp" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="block">
                <span class="text-sm text-ink-2">Business number or owner email</span>
                <input
                    wire:model="lookup"
                    type="text"
                    class="mt-1 w-40 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                >
            </label>

            {{--
                ⚠️ THE LABEL SWAPS RATHER THAN A SPINNER APPEARING BESIDE
                IT — `account-audit.blade.php`'s idiom, and the verb
                survives the flow (`22`). `wire:loading.attr="disabled"` is
                what stops a second press, which matters more on the two
                forms below than on this one: `attach` writes what a
                customer pays and `setUp` publishes a public page.
            --}}
            <x-ui.button
                type="submit"
                variant="secondary"
                size="default"
                wire:loading.attr="disabled"
                wire:target="lookUp"
            >
                <span wire:loading.remove wire:target="lookUp">Open</span>
                <span wire:loading wire:target="lookUp">Opening…</span>
            </x-ui.button>
        </form>

        @if ($ownedChoices !== [])
            <p class="mt-4 text-base text-ink">That owner has {{ count($ownedChoices) }} businesses. Open one:</p>
            <ul class="mt-2 space-y-1">
                @foreach ($ownedChoices as $choice)
                    <li><x-ui.button wire:click="choose({{ $choice['id'] }})" size="default" variant="secondary">#{{ $choice['id'] }} — {{ $choice['name'] }}</x-ui.button></li>
                @endforeach
            </ul>
        @endif
    </section>

    @if ($business !== null)
        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Where things stand</h2>

            <p class="mt-2 text-base text-ink-2">
                This plan covers {{ $permitted }}
                {{ $permitted === 1 ? 'location' : 'locations' }}.
                {{ $used }} {{ $used === 1 ? 'is' : 'are' }} set up.
            </p>

            <p class="mt-2 text-base text-ink-2">
                Each location beyond the first is {{ $addOnPrice }}
                {{ $term === \App\Enums\BillingTerm::Annual ? 'a year' : 'a month' }}
                at today's rate. What this customer already agreed to is on their
                plan and is not changed by anything on this page.
            </p>

            {{--
                ⚠️ `@forelse`, AND THE EMPTY BRANCH IS THE ONE AN OPERATOR
                IS MOST LIKELY TO MEET (4302). This screen is opened against
                a business somebody is asking about, and "no locations at
                all" is a real support case — a heading with nothing under
                it reads as a screen that failed to load, on the one page
                where an operator is about to record what a customer pays.
            --}}
            {{--
                ⛔ THESE TWO LINKS ARE THE ONLY WAY INTO EITHER SCREEN AND
                THERE WAS NO WAY IN AT ALL UNTIL 2026-08-24 (9287).
                `admin.location-settings` and `admin.review-queue` were the
                whole unreachable population of this router: seventeen `admin.`
                routes and three `support.` ones against seventeen `AdminNav`
                items, and the third of the difference —
                `admin.legal-documents` — was already one click from its own
                index. **Nothing in `app/`, `routes/` or `resources/` called
                `route()` on either name.**

                ⚠️ THE NAV COULD NOT HAVE CARRIED THEM AND THAT IS WHY THEY ARE
                HERE. `AdminNav` items take no route parameter — `NavItem` has
                no slot for one and `components/admin/nav.blade.php` calls
                `route($item->route)` with nothing else — so an entry for
                either screen would throw at render for every viewer.
                `admin.legal-documents` answered the same problem the same way
                and is the precedent (`legal-document-index.blade.php`).

                ⚠️ AND THIS IS THE RIGHT INDEX RATHER THAN A CONVENIENT ONE:
                it is the only screen in the console that lists an account's
                locations at all, which is exactly the fact those two screens
                need and cannot derive — `locations` is `ENABLE`+`FORCE` row
                level security, so no path segment turns into the tenant it
                belongs to without already being inside one.

                ⚠️ THE LINK CARRIES THE LOCATION AND NOT THE ACCOUNT, ON
                PURPOSE. Both destinations ask for the account number again,
                which is 9236's design and is what files the read in that
                account's own trail; putting a business id in the query string
                would move a customer identifier into a URL to save an operator
                one field.
            --}}
            <ul class="mt-4 space-y-2">
                @forelse ($locations as $location)
                    <li class="text-base text-ink">
                        {{ $location->name }}

                        <span class="mt-1 flex flex-wrap gap-x-5">
                            <a
                                href="{{ route('admin.location-settings', ['location' => $location->id]) }}"
                                class="inline-flex min-h-11 items-center text-base text-ink-2 underline hover:text-ink"
                            >What runs on its own</a>

                            <a
                                href="{{ route('admin.review-queue', ['location' => $location->id]) }}"
                                class="inline-flex min-h-11 items-center text-base text-ink-2 underline hover:text-ink"
                            >Reviews waiting for a decision</a>
                        </span>
                    </li>
                @empty
                    <li>
                        <x-ui.empty-state icon="○" heading="No locations set up">
                            This business has none. Record what the plan covers
                            below, then set one up.
                        </x-ui.empty-state>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">No-card trial</h2>
            @if ($onNoCardTrial && $noCardTrialEndsAt !== null)
                <p class="mt-1 text-base text-ink-2">Ends {{ $noCardTrialEndsAt->format('j M Y') }}. Extending it moves that date for this account only.</p>
                <form wire:submit="extendTrial" class="mt-4 flex flex-wrap items-end gap-3">
                    <label class="block">
                        <span class="text-sm text-ink-2">New end date</span>
                        <input wire:model="trialUntil" type="date" class="mt-1 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink">
                    </label>
                    <x-ui.button
                        type="submit"
                        variant="secondary"
                        size="default"
                        wire:loading.attr="disabled"
                        wire:target="extendTrial"
                    >
                        <span wire:loading.remove wire:target="extendTrial">Extend</span>
                        <span wire:loading wire:target="extendTrial">Extending…</span>
                    </x-ui.button>
                </form>
                @error('trialUntil')
                    <p class="mt-3 text-base text-alert" role="alert">{{ $message }}</p>
                @enderror
            @else
                <p class="mt-1 text-base text-ink-2">Not on a no-card trial, so there is nothing to extend here.</p>
            @endif
        </section>

        <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">
                Record what the plan covers
            </h2>

            {{--
                ⛔ THE ORDER OF THE TWO SENTENCES IS THE POINT. The operator
                is told what this does NOT do before they are told what it
                does, because the failure this wording exists to prevent is
                an operator believing the money moved.
            --}}
            <p class="mt-2 text-base text-ink-2">
                This does not charge anyone. Change the recurring amount at the
                gateway first, then record the new total here so the rest of the
                product knows how many locations this customer has paid for.
            </p>

            <form wire:submit="attach" class="mt-4 flex flex-wrap items-end gap-3">
                <label class="block">
                    <span class="text-sm text-ink-2">Locations beyond the first</span>
                    <input
                        wire:model="additionalLocations"
                        type="text"
                        inputmode="numeric"
                        class="mt-1 w-40 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                    >
                </label>

                <x-ui.button
                    type="submit"
                    variant="secondary"
                    size="default"
                    wire:loading.attr="disabled"
                    wire:target="attach"
                >
                    <span wire:loading.remove wire:target="attach">Record</span>
                    <span wire:loading wire:target="attach">Recording…</span>
                </x-ui.button>
            </form>

            @error('additionalLocations')
                <p class="mt-3 text-base text-alert" role="alert">{{ $message }}</p>
            @enderror
        </section>

        @if ($remaining > 0)
            <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <h2 class="font-display text-lg font-semibold text-ink">Set up a location</h2>

                <p class="mt-2 text-base text-ink-2">
                    This plan has room for {{ $remaining }} more. Setting one up
                    publishes its feedback page straight away, so use the name the
                    customer wants their customers to see.
                </p>

                <form wire:submit="setUp" class="mt-4 space-y-4">
                    <label class="block">
                        <span class="text-sm text-ink-2">What is this location called?</span>
                        <input
                            wire:model="name"
                            type="text"
                            autocomplete="off"
                            class="mt-1 w-full max-w-md rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-base text-ink"
                        >
                    </label>

                    @error('name')
                        <p class="text-base text-alert" role="alert">{{ $message }}</p>
                    @enderror

                    <x-ui.button
                        type="submit"
                        variant="primary"
                        size="default"
                        wire:loading.attr="disabled"
                        wire:target="setUp"
                    >
                        <span wire:loading.remove wire:target="setUp">Set up this location</span>
                        <span wire:loading wire:target="setUp">Setting up…</span>
                    </x-ui.button>
                </form>
            </section>
        @else
            {{--
                ⚠️ ABSENT RATHER THAN DISABLED (1220), and it names the step
                that comes first. A set-up form that exists and then explains
                why it cannot work is how somebody concludes the rule is a bug.
            --}}
            <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
                <p class="text-base text-ink-2">
                    Every location this plan covers is already set up. Record a
                    higher total above before setting up another.
                </p>
            </section>
        @endif
    @endif

    <p class="text-base text-ink-2">
        There is no list of businesses here. Every table this screen reads is
        row-level-secured on one tenant at a time and platform staff belong to
        none, so an operator opens one business by its number and sees nothing
        else. An owner’s email address opens that owner’s businesses through the same policy the owner themselves uses to see them.
    </p>
</div>

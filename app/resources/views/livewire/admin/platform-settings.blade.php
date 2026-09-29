{{--
    Ops → Platform → Settings — the Defaults Registry editor (`38` Part 2).

    Deliberately plain, like the review queue and the legal document screen. It
    exists so DefaultsRegistry has a caller; see the component for why the
    prices below are read-only.

    COLOUR IS NOT THE SIGNAL (`22`). "Default" and "Changed" are words, not
    hues; the withheld panel is a heading and a sentence rather than a warning
    colour. Nothing here needs a legend to be understood.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Settings</h1>
        <p class="mt-1 text-base text-ink-2">
            The numbers the platform runs on. Each one starts at a reviewed
            default and can be put back to it at any time.
        </p>
    </div>

    <div class="flex flex-wrap gap-3">
        @foreach (\App\Support\DefaultsManifest::GROUPS as $g)
            @if (isset($groups[$g]))
                <a href="#group-{{ str()->slug($g) }}" class="text-sm font-medium text-ink underline decoration-rule underline-offset-4">
                    {{ $g }} ({{ count($groups[$g]) }})
                </a>
            @endif
        @endforeach
    </div>

    <div>
        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search keys and descriptions..." class="rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink w-full max-w-md">
        @if ($search !== '')
            <p class="mt-2 text-sm text-ink-2">{{ $matchCount }} settings match</p>
        @endif
    </div>

    {{--
        empty-state: absent because the groups are the defaults registry
        rendered, not a query — every setting this platform has is seeded by
        `DefaultsManifest`, so an empty branch here would never render and a
        lint satisfied by dead code is worse than the gap it closes.
    --}}
    @foreach ($groups as $group => $rows)
        <section id="group-{{ str()->slug($group) }}" class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">{{ $group }}</h2>

            <ul class="mt-4 space-y-5 max-h-[600px] overflow-y-auto pr-2">
                @foreach ($rows as $row)
                    <li wire:key="setting-{{ $row['key'] }}" class="border-t border-rule pt-5 first:border-0 first:pt-0">
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                            <p class="font-mono text-sm text-ink break-all">{{ $row['key'] }}</p>

                            {{--
                                Seed vs current, which is the whole point of
                                the panel: an operator has to be able to see
                                at a glance which numbers somebody has moved.
                            --}}
                            @if (! $row['isSeeded'])
                                <span class="text-sm text-ink-3">Set by an operator only</span>
                            @elseif ($row['current'] === $row['seed'])
                                <span class="text-sm text-ink-3">Default</span>
                            @else
                                <span class="text-sm text-ink-2">
                                    Changed — the default is
                                    <span class="tabular-nums break-all">{{ var_export($row['seed'], true) }}</span>
                                </span>
                            @endif
                        </div>

                        {{--
                            A switch reads its state in words, never in the
                            key's spelling and never in colour alone (`22`).
                            ⚠️ The "and the row still holds" clause is not
                            decoration: until 5880 a boolean could be saved
                            as the string `'True'`, which every reader
                            refuses, so a row can exist that is Off and does
                            not look empty. Saying both is what makes the
                            repair pressable rather than mysterious.
                        --}}
                        @if ($row['isBoolean'])
                            <p class="mt-1 font-display text-lg font-semibold text-ink">
                                {{ $row['current'] === true ? 'On' : 'Off' }}
                                @if (! is_bool($row['current']))
                                    <span class="font-sans text-base font-normal text-ink-2">
                                        — the row holds
                                        <span class="font-mono">{{ var_export($row['current'], true) }}</span>,
                                        which is not on or off, so everything treats it as off
                                    </span>
                                @endif
                            </p>
                        @elseif ($row['isModelKey'])
                            <p class="mt-1 font-display text-lg font-semibold tabular-nums text-ink break-all">
                                {{ $row['current'] === null ? 'Not set' : $row['current'] }}
                            </p>
                        @else
                            <p class="mt-1 font-display text-lg font-semibold tabular-nums text-ink break-all">
                                {{ $row['current'] === null ? 'Not set' : var_export($row['current'], true) }}
                            </p>
                        @endif

                        <p class="mt-1 text-base text-ink-2">{{ $row['description'] }}</p>

                        @if (isset($doors[$row['key']]))
                            {{--
                                ⛔ SHOWN AND NOT MOVED (5900, 5901). The value
                                above stays — an operator asking "is the
                                platform halted" has to get an answer here,
                                and 2402 records that no second row anywhere
                                answers it — and every control goes, because
                                this key has a screen of its own with guards,
                                an ordering and a confirmation that a switch
                                here would have none of.

                                ⚠️ NO SWITCH ON THIS BRANCH, EVER — and no
                                "Change" and no "Put back to default" either,
                                because putting a halt back to its default IS
                                starting sending again. A lint in
                                `Architecture/RegistryTest` fails the build on
                                any of the three, and the component refuses
                                all four actions server-side besides, because
                                a Livewire action argument is request input.

                                The link is a link and not a button: it goes
                                somewhere, it does nothing. Console doors have
                                no route, so they render as the command to
                                type.
                            --}}
                            <p class="mt-3 text-base text-ink-2">
                                Changed on
                                @if ($doors[$row['key']]['route'] !== null)
                                    <a
                                        href="{{ route($doors[$row['key']]['route']) }}"
                                        class="font-medium text-ink underline decoration-rule underline-offset-4"
                                    >{{ $doors[$row['key']]['door'] }}</a>
                                @else
                                    <span class="font-mono text-sm text-ink">{{ $doors[$row['key']]['door'] }}</span>
                                @endif
                            </p>

                            <p class="mt-1 text-base text-ink-2">{{ $doors[$row['key']]['why'] }}</p>
                        @elseif ($row['isModelKey'])
                            @if ($editing === $row['key'])
                                <div class="mt-4 flex flex-wrap items-end gap-3">
                                    <label class="block">
                                        <span class="text-sm text-ink-2">New value</span>
                                        <div class="mt-1 flex gap-2">
                                            <select wire:model="draft" class="rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink">
                                                @foreach ($modelOptions as $opt)
                                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                                @endforeach
                                                <option value="">(Task default)</option>
                                            </select>
                                            <input wire:model="draft" type="text" class="rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink">
                                        </div>
                                    </label>

                                    <div class="flex items-center text-sm text-ink-2 mb-2">Effective: {{ $effectiveModel }}</div>

                                    <button wire:click="save" type="button" class="rounded-[--radius-field] bg-ink px-4 py-2 text-paper">
                                        Save
                                    </button>

                                    <button wire:click="cancel" type="button" class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink">
                                        Cancel
                                    </button>
                                </div>

                                @include('livewire.admin.partials.registry-history', ['history' => $history])
                            @else
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <button
                                        wire:click="edit('{{ $row['key'] }}')"
                                        type="button"
                                        class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink"
                                    >
                                        Change
                                    </button>

                                    @if ($row['isSeeded'] && $row['current'] !== $row['seed'])
                                        <button
                                            wire:click="resetToSeed('{{ $row['key'] }}')"
                                            type="button"
                                            class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink"
                                        >
                                            Put back to default
                                        </button>
                                    @endif
                                </div>
                            @endif
                        @elseif ($row['isBoolean'])
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                {{--
                                    ⛔ NO TEXT BOX ON THIS BRANCH, EVER — a
                                    lint in `Architecture/RegistryTest` fails
                                    the build if a boolean-seeded key renders
                                    one.

                                    `role="switch"` with `aria-checked` (5890)
                                    carries the state programmatically; the
                                    visible verb carries it to everyone else,
                                    and the state is also written in words
                                    above. ⚠️ THE `aria-label` STARTS WITH
                                    THE VISIBLE LABEL ON PURPOSE — WCAG 2.5.3
                                    (Label in Name) requires the accessible
                                    name to contain the visible text, and a
                                    bare `aria-label` of the key would break
                                    it. The key is appended because twenty
                                    switches on one screen otherwise announce
                                    as twenty identical "Turn on"s to
                                    somebody tabbing between them.
                                --}}
                                <button
                                    wire:click="toggle('{{ $row['key'] }}')"
                                    type="button"
                                    role="switch"
                                    aria-checked="{{ $row['current'] === true ? 'true' : 'false' }}"
                                    aria-label="{{ $row['current'] === true ? 'Turn off' : 'Turn on' }} {{ $row['key'] }}"
                                    @class([
                                        'flex min-h-11 items-center rounded-[--radius-field] border border-rule px-4 text-base',
                                        'bg-ink font-medium text-paper' => $row['current'] === true,
                                        'bg-card text-ink' => $row['current'] !== true,
                                    ])
                                >{{ $row['current'] === true ? 'Turn off' : 'Turn on' }}</button>

                                @if ($row['isSeeded'] && $row['current'] !== $row['seed'])
                                    <button
                                        wire:click="resetToSeed('{{ $row['key'] }}')"
                                        type="button"
                                        class="flex min-h-11 items-center rounded-[--radius-field] border border-rule px-4 text-base text-ink"
                                    >
                                        Put back to default
                                    </button>
                                @endif
                            </div>

                            @if ($confirming === $row['key'] && ! $confirmingIsClaim)
                                {{--
                                    The second step (5883, decision 220's
                                    pattern).

                                    ⚠️ AND `! $confirmingIsClaim` IS LOAD-BEARING
                                    RATHER THAN DEFENSIVE (11705). `$confirming`
                                    holds two kinds of key since the capability
                                    claim switches got a second press, and without
                                    this arm an operator turning on
                                    `features.inbox` would be asked "Turn on
                                    changes to customers' websites?" — the wrong
                                    question, about the wrong switch, with a
                                    button that does nothing because
                                    `confirmSiteWrites()` checks its own kind. It names what becomes possible
                                    in plain words — not the key, not the
                                    value — because what an operator is being
                                    asked to weigh is the consequence.

                                    ⛔ AND IT CLAIMS NO PROTECTION (5888).
                                    An earlier draft added "every change is
                                    snapshotted and can be undone", which is
                                    true and is 314–316's shape on a
                                    confirmation screen: the sentence that
                                    stops the person reading it weighing the
                                    thing they are being asked to weigh.
                                --}}
                                <div class="mt-4 rounded-[--radius-panel] border border-rule bg-paper p-4">
                                    <h3 class="font-display text-base font-semibold text-ink">
                                        Turn on changes to customers' websites?
                                    </h3>

                                    <p class="mt-2 text-base text-ink-2">
                                        While this is on, this platform may publish pages and
                                        apply fixes to the websites of the businesses that use
                                        it, on its own, without anybody here pressing anything
                                        again. Those are other people's websites. Turning it
                                        off again takes one press.
                                    </p>

                                    {{--
                                        ⛔ THE ONLY SENTENCE ON THIS SCREEN
                                        THAT IS ABOUT THIS MACHINE, AND IT IS
                                        HERE BECAUSE ONE WAS IN THE REGISTRY
                                        AND WAS FALSE (6121, 6184).
                                        `actuation.enabled`'s description told
                                        an operator that "the only adapter
                                        that exists reports itself unwritable,
                                        so turning this on today changes
                                        nothing" — while `CMS_DRIVER=wordpress`
                                        was deployed. **A string in the
                                        repository cannot know what a running
                                        install has in `.env`; this component
                                        can, so it asks.**

                                        ⚠️ AND IT CLAIMS NO PROTECTION, on the
                                        same rule as the paragraph above
                                        (5888). The "off" arm says what is
                                        bound, not that the operator is safe:
                                        the switch can be turned on now and a
                                        deploy can bind a live adapter
                                        afterwards, with nobody pressing
                                        anything again.
                                    --}}
                                    <p class="mt-2 text-base text-ink-2">
                                        @if ($adapterReachesAWebsite)
                                            This deployment has a website adapter connected, so
                                            it can reach real websites as soon as this is on.
                                        @else
                                            This deployment has no website adapter connected, so
                                            nothing would be written today — but that is a
                                            setting on this machine, and the next deploy can
                                            change it without anybody pressing this again.
                                        @endif
                                    </p>

                                    <div class="mt-4 flex flex-wrap items-center gap-3">
                                        <button
                                            wire:click="confirmSiteWrites"
                                            type="button"
                                            class="flex min-h-11 items-center rounded-[--radius-field] bg-ink px-4 text-base text-paper"
                                        >
                                            Yes — allow website changes
                                        </button>

                                        <button
                                            wire:click="cancel"
                                            type="button"
                                            class="flex min-h-11 items-center rounded-[--radius-field] border border-rule px-4 text-base text-ink"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            @endif

                            @if ($confirming === $row['key'] && $confirmingIsClaim)
                                {{--
                                    THE SECOND STEP ON A CLAIM SWITCH (11705).
                                    The switch above asks whether you want the
                                    row on. This asks whether the claim is
                                    TRUE — because these five rows enable
                                    nothing. Flipping one makes /features,
                                    /compare and /faq say, to anybody who
                                    visits, that this product does the thing.

                                    ⛔ AN EXISTENCE CHECK WAS REFUSED AND THIS
                                    IS WHY IT IS A QUESTION RATHER THAN A
                                    GATE. `App\Livewire\Account\Inbox`
                                    exists, is routed and works, and
                                    `features.inbox` publishes "every text,
                                    email, and call from the same person, in
                                    one story" while the Inbox filters every
                                    query to SMS. A machine asking "does the
                                    class exist" answers yes here and is
                                    wrong; a person asked to affirm the
                                    sentence is not.

                                    ⛔ AND IT CLAIMS NO PROTECTION (5888).
                                    Nothing here checks the claim, nothing
                                    records what was checked, and the last
                                    line is the asymmetry rather than a
                                    reassurance: a website write can be put
                                    back, and a sentence somebody has already
                                    read cannot.
                                --}}
                                <div class="mt-4 rounded-[--radius-panel] border border-rule bg-paper p-4">
                                    <h3 class="font-display text-base font-semibold text-ink">
                                        Say publicly that we do this?
                                    </h3>

                                    {{--
                                        ⚠️ `$claimHeading` IS UNCONDITIONAL HERE
                                        AND THE @else IT REPLACES WAS DEAD AND
                                        UGLY. Both view props are derived from
                                        `$confirming` in one render, so a branch
                                        that reaches this panel has a capability
                                        by construction — and the null arm, found
                                        by rendering the screen and reading it as
                                        text, printed the sentence with a space
                                        and a full stop hanging off the end.
                                    --}}
                                    {{--
                                        ⛔ THIS PROMISED THREE PAGES FOR EVERY
                                        SWITCH AND THAT IS TRUE OF ONE (12244).
                                        `features.inbox` and `features.websites`
                                        reach What it does alone;
                                        `features.campaigns` has no Questions
                                        answer and `features.boost_score` no
                                        Compare row. Rendered off this screen
                                        and read as text, an operator was
                                        promised a comparison row **directly
                                        below the manifest description that
                                        names no such row** — and a blast radius
                                        stated wider than it is teaches the
                                        reader that the sentence is decoration,
                                        which is the one thing a second press
                                        cannot afford.

                                        ⚠️ SO IT IS DERIVED AND NOT REWORDED.
                                        `$claimSurfaces` is
                                        `MarketingCapability::publishedSurfaces()`,
                                        and `MarketingTest` re-derives the same
                                        map from every blade in the tree and
                                        asserts it against that method in both
                                        directions. A narrower promise typed
                                        here would have gone stale the next time
                                        a capability reached a fourth page.
                                    --}}
                                    <p class="mt-2 text-base text-ink-2">
                                        This switch turns nothing on. It makes the signed-out
                                        site say we do it, under the heading
                                        &ldquo;{{ $claimHeading }}&rdquo;.
                                    </p>

                                    <p class="mt-2 text-base text-ink-2">
                                        The pages that start saying it — and it is not the same
                                        set for every switch:
                                    </p>

                                    <ul class="mt-2 list-disc space-y-1 ps-5 text-base text-ink-2">
                                        @foreach ($claimSurfaces as $claimSurface)
                                            <li>{{ $claimSurface }}</li>
                                        @endforeach
                                    </ul>

                                    <p class="mt-2 text-base text-ink-2">
                                        The line above this switch is what the site will be
                                        saying. Press on only if it is true of the product
                                        today.
                                    </p>

                                    <p class="mt-2 text-base text-ink-2">
                                        Turning it off again takes one press and the pages stop
                                        saying it straight away. It does not unsay it to
                                        anybody who has already read it.
                                    </p>

                                    <div class="mt-4 flex flex-wrap items-center gap-3">
                                        <button
                                            wire:click="confirmClaim"
                                            type="button"
                                            class="flex min-h-11 items-center rounded-[--radius-field] bg-ink px-4 text-base text-paper"
                                        >
                                            Yes — we do this today
                                        </button>

                                        <button
                                            wire:click="cancel"
                                            type="button"
                                            class="flex min-h-11 items-center rounded-[--radius-field] border border-rule px-4 text-base text-ink"
                                        >
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            @endif

                            @if ($confirming === $row['key'])
                                @include('livewire.admin.partials.registry-history', ['history' => $history])
                            @endif
                        @elseif ($editing === $row['key'])
                            <div class="mt-4 flex flex-wrap items-end gap-3">
                                <label class="block">
                                    <span class="text-sm text-ink-2">New value</span>
                                    <input
                                        wire:model="draft"
                                        type="text"
                                        class="mt-1 rounded-[--radius-field] border border-rule bg-paper px-3 py-2 text-ink"
                                    >
                                </label>

                                <button wire:click="save" type="button" class="rounded-[--radius-field] bg-ink px-4 py-2 text-paper">
                                    Save
                                </button>

                                <button wire:click="cancel" type="button" class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink">
                                    Cancel
                                </button>
                            </div>

                            @include('livewire.admin.partials.registry-history', ['history' => $history])
                        @else
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <button
                                    wire:click="edit('{{ $row['key'] }}')"
                                    type="button"
                                    class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink"
                                >
                                    Change
                                </button>

                                @if ($row['isSeeded'] && $row['current'] !== $row['seed'])
                                    <button
                                        wire:click="resetToSeed('{{ $row['key'] }}')"
                                        type="button"
                                        class="rounded-[--radius-field] border border-rule px-4 py-2 text-ink"
                                    >
                                        Put back to default
                                    </button>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Plans</h2>

        <p class="mt-1 text-base text-ink-2">
            Read-only here. Changing a price has to create the matching price
            with the card processor in the same breath, or the two disagree
            and nobody can tell which one a customer is on. That work is the
            billing sprint's.
        </p>

        {{--
            empty-state: absent because the plan prices are read from the
            manifest that `ArchitectureTest` parses against `CLAUDE.md`'s own
            table on every run. There is no state of this application in
            which no plan has a price.
        --}}
        @foreach ($prices as $plan => $keys)
            <div class="mt-4 border-t border-rule pt-4 first:border-0">
                <h3 class="font-display text-base font-semibold text-ink">{{ $plan }}</h3>

                <ul class="mt-2 space-y-1">
                    @foreach ($keys as $key => $formatted)
                        <li wire:key="price-{{ $plan }}-{{ $key }}" class="flex flex-wrap gap-x-3 text-base">
                            <span class="font-mono text-sm text-ink-2">{{ $key }}</span>
                            <span class="tabular-nums text-ink">{{ $formatted }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </section>

    <section class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Waiting on a decision</h2>

        <p class="mt-1 text-base text-ink-2">
            These have no default and nothing may invent one. Anything that
            needs them refuses rather than quoting a plausible number.
        </p>

        <ul class="mt-4 space-y-3">
            {{--
                ⚠️ THIS ONE CAN GENUINELY EMPTY, AND THE DAY IT DOES IS
                WORTH SAYING OUT LOUD RATHER THAN LEAVING A BARE HEADING —
                it means the owner has answered every open figure, which is
                a state this platform has never been in.
            --}}
            @forelse ($withheld as $path => $reason)
                <li wire:key="withheld-{{ $path }}">
                    <p class="font-mono text-sm text-ink break-all">{{ $path }}</p>
                    <p class="mt-1 text-base text-ink-2">{{ $reason }}</p>
                </li>
            @empty
                <li>
                    <x-ui.empty-state icon="✓">
                        Nothing is waiting on a decision. Every figure this platform
                        needs has been set.
                    </x-ui.empty-state>
                </li>
            @endforelse
        </ul>
    </section>
</div>

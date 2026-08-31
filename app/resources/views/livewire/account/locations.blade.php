{{--
    Your locations — and the allowance the plan bought.

    ⛔ THE PAGE NEVER SAYS "SUBSCRIPTION", "SKU", "ENTITLEMENT" OR "ALLOWANCE".
    `22`'s outcome-language rule: every string names what the person controls.
    "Your plan covers 3 locations. You are using 2." is the same fact in words a
    business owner already has.

    ⚠️ NOTHING ON THIS PAGE SPENDS MONEY, AND THE COPY IS EXPLICIT ABOUT IT.
    `CLAUDE.md`'s never-bill-by-surprise cuts both ways: an owner pressing Add
    must know they are not being charged now, or they will not press it. What
    was charged was charged at checkout.

    ⚠️ THE PRICE SHOWN TO AN OWNER WITH NO ROOM IS TODAY'S RATE ON THEIR OWN
    TERM, formatted by the one formatter (512) — never a literal, which a lint
    fails the build on.

    COLOUR IS NEVER THE SOLE SIGNAL: the "no room" panel is a bordered panel
    with a sentence in it, readable in monochrome and to a screen reader.

    WORKS AT 320px, and nothing here is below 16px.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your locations</h1>
        <p class="mt-1 text-base text-ink-2">
            Every place you do business. Reviews, invitations and reports are kept
            separately for each one.
        </p>
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Where things stand</h2>

        <p class="mt-2 text-base text-ink-2">
            Your plan covers {{ $permitted }}
            {{ $permitted === 1 ? 'location' : 'locations' }}. You are using {{ $used }}.
        </p>

        {{--
            ⚠️ `@forelse` RATHER THAN `@foreach`, AND THE EMPTY BRANCH IS NOT
            DEAD CODE (4302). `TenantProvisioner` makes the first location, so
            an owner reaching this page normally has one — but "normally" is not
            "always": a location can be removed, and the state this page would
            otherwise render is a heading with nothing under it and no sentence
            saying why. `Architecture/ScreenStatesTest` fails the build on that
            shape, and it is right to: an empty list that says nothing reads as
            a page that failed to load.

            NO ACTION ON THE INVITATION, DELIBERATELY. `29` §5.7 wants an empty
            state to be an invitation, and the invitation here is to ask a
            person — self-serve is on T176 P25's OUT list because open question
            K is the owner's, so a button would be the one this whole screen
            exists not to draw.
        --}}
        <ul class="mt-4 space-y-4">
            @forelse ($locations as $location)
                <li wire:key="location-{{ $location->id }}" class="border-t border-rule pt-4 first:border-0 first:pt-0">
                    <p class="text-base font-semibold text-ink">{{ $location->name }}</p>

                    {{--
                        ⛔ HOW CUSTOMERS REACH THIS BUSINESS — THE TWO COLUMNS
                        NOTHING HAD EVER WRITTEN (6100). The phone number here is
                        printed word for word into the reply somebody gets when
                        they text HELP, and the address is what our assistant
                        answers "where are you" with. Until this panel existed
                        both were empty for every real business, so the HELP reply
                        gave out our support address instead of theirs.

                        ⚠️ ONE PRESS, AND THE BOXES ARRIVE FILLED WITH WHAT WE
                        HOLD. Nothing here is derived, so there is nothing to show
                        back for a second confirmation — and a box that starts
                        full is what lets an empty one mean "we do not have this"
                        rather than "leave it alone".

                        ⚠️ NEITHER IS REQUIRED. A business that works out of a van
                        has no address to give, and saying so is an answer.

                        COLOUR IS NEVER THE SOLE SIGNAL: every state here is a
                        sentence, readable in monochrome and to a screen reader.
                    --}}
                    @if ($detailsLocationId === $location->id)
                        <form wire:submit="saveDetails" class="mt-3 rounded-[--radius-panel] border border-rule-strong bg-card p-4">
                            <p class="text-base text-ink">How your customers reach you</p>
                            <p class="mt-1 text-base text-ink-2">
                                We give these out exactly as you write them here — when
                                somebody texts asking who we are, and when our assistant
                                answers a customer.
                            </p>

                            <label for="phone-{{ $location->id }}" class="mt-4 block text-base text-ink">
                                The phone number your customers ring
                            </label>
                            <input
                                id="phone-{{ $location->id }}"
                                type="text"
                                inputmode="tel"
                                autocomplete="tel"
                                wire:model="statedPhone"
                                placeholder="(901) 555-0182"
                                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                            />

                            @error('statedPhone')
                                <p class="mt-1 text-base text-alert" role="alert">{{ $message }}</p>
                            @enderror

                            <label for="address-{{ $location->id }}" class="mt-4 block text-base text-ink">
                                Where you are
                            </label>
                            <input
                                id="address-{{ $location->id }}"
                                type="text"
                                autocomplete="street-address"
                                wire:model="statedAddress"
                                placeholder="1234 Union Avenue, Memphis, TN 38104"
                                class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                            />

                            @error('statedAddress')
                                <p class="mt-1 text-base text-alert" role="alert">{{ $message }}</p>
                            @enderror

                            <p class="mt-2 text-base text-ink-2">
                                Leave one empty if you do not have it. We will not make one up.
                            </p>

                            <div class="mt-3 flex flex-wrap gap-3">
                                <x-ui.button type="submit" size="default">
                                    <span wire:loading.remove wire:target="saveDetails">Save</span>
                                    <span wire:loading wire:target="saveDetails">Saving…</span>
                                </x-ui.button>

                                <x-ui.button type="button" size="default" variant="secondary" wire:click="cancelDetails">
                                    Cancel
                                </x-ui.button>
                            </div>
                        </form>
                    @else
                        {{--
                            ⚠️ THREE SENTENCES RATHER THAN A FIELD LIST, AND THE
                            EMPTY ONE ASKS. A page that printed "Phone: —" would
                            say nothing about whether anybody had been asked;
                            229's rule is that a missing answer and a negative
                            answer are different things to be told.
                        --}}
                        @if ($location->primary_phone !== null)
                            <p class="mt-1 text-base text-ink-2">
                                We tell your customers to ring
                                <span class="font-mono">{{ $location->primary_phone }}</span>.
                            </p>
                        @endif

                        @if ($location->address !== null)
                            <p class="mt-1 text-base text-ink-2">
                                We tell them you are at {{ $location->address }}.
                            </p>
                        @endif

                        @if ($location->primary_phone === null && $location->address === null)
                            <p class="mt-1 text-base text-ink-2">
                                Tell us the number your customers ring and where you are, and we
                                will give those out when somebody asks. Until then we say we will
                                get back to them.
                            </p>
                        @endif

                        <div class="mt-2">
                            <x-ui.button type="button" size="default" variant="secondary" wire:click="editDetails({{ $location->id }})">
                                {{ $location->primary_phone === null && $location->address === null
                                    ? 'Add how customers reach you'
                                    : 'Change how customers reach you' }}
                            </x-ui.button>
                        </div>
                    @endif

                    {{--
                        ⛔ THE ADDRESS IS PASTED AND CONFIRMED, NEVER GUESSED.
                        It is where a later change to this business's website
                        gets written, so a wrong one would have us editing
                        somebody else's site — a franchisor's, in the case
                        decision 1083 is about.

                        ⚠️ TWO PRESSES. The first shows the address back in the
                        form we will actually use; the second is the one that
                        saves. Nothing is stored by the first.

                        COLOUR IS NEVER THE SOLE SIGNAL: every state here is a
                        sentence, readable in monochrome and to a screen reader.
                    --}}
                    <p class="mt-1 text-base text-ink-2">{{ $reach[$location->id]['sentence'] }}</p>

                    {{--
                        ⛔ `28` §4.1's "the system tells the owner the truth once,
                        simply". ONCE IS TRUE BY CONSTRUCTION HERE: this is a
                        screen state rather than a notification, so nothing sends
                        anything and there is nothing to repeat. 5542 settled the
                        same shape for the tier offer above — a sentence with no
                        button is not a nag.

                        ⛔ IT DOES NOT PROMISE PRECONNECT. §4.1's pixel column
                        offers "measurement + preconnect only"; nothing in this
                        platform can see a site's third-party origins, so the
                        preconnect half is unbuilt and claiming it here would be
                        a promise made to somebody's face.
                    --}}
                    @if ($location->website_url !== null)
                        <p class="mt-1 text-base text-ink-2">{{ $reach[$location->id]['speed'] }}</p>
                    @endif

                    @if ($location->website_url !== null)
                        <p class="mt-1 text-base text-ink-2">
                            We have <span class="font-mono">{{ $location->website_url }}</span> as your website.
                        </p>
                    @endif

                    {{--
                        ⛔ THE PAGE THE BYLINE POINTS AT — `29` §2 rule 36.
                        "Auto-published content carries a real author byline
                        linked to a genuine About page", and the owner's ruling
                        of 2026-08-20 makes it a gate: no About page, no
                        publish. The copy says that plainly rather than
                        describing a rule.

                        ⚠️ ASKED ONLY ONCE THERE IS A WEBSITE TO ASK ABOUT. The
                        writer refuses an About page on any other host, so the
                        question makes no sense before the address is confirmed.
                    --}}
                    @if ($location->website_url !== null)
                        {{--
                            THE REFUSAL AN OWNER CAN FIX AND COULD NOT SEE
                            (6065). Rule 36 will not publish without a resolving
                            About page, and a tenant whose own robots.txt
                            disallows us is refused for ever — the only record of
                            it being an activity feed no screen in this
                            application renders. It sits above the byline line
                            rather than beside it, because while it is true the
                            byline line's promise ("anything we publish will say
                            it is by …") is not going to happen.

                            Attention rather than Alert, on the undo screen's
                            argument: it is a thing to decide about, not an
                            emergency, and the word inside the card carries the
                            state whatever the colour does.
                        --}}
                        @if ($reach[$location->id]['aboutBlocked'] !== null)
                            <x-ui.attention-card class="mt-3" heading="We cannot check your About page">
                                {{ $reach[$location->id]['aboutBlocked'] }}
                            </x-ui.attention-card>
                        @endif

                        @if ($reach[$location->id]['about'] !== null)
                            <p class="mt-1 text-base text-ink-2">
                                Anything we publish will say it is by
                                <span class="font-semibold text-ink">{{ $bylineName }}</span>
                                and link to
                                <span class="font-mono">{{ $reach[$location->id]['about'] }}</span>.
                            </p>
                        @else
                            <p class="mt-1 text-base text-ink-2">
                                Anything we publish will say it is by
                                <span class="font-semibold text-ink">{{ $bylineName }}</span>.
                                Tell us the page on your website that says who you are, and we
                                will link your name to it. Until then we will not publish
                                anything.
                            </p>
                        @endif

                        @if ($aboutLocationId === $location->id)
                            <form wire:submit="saveAbout" class="mt-3">
                                <label for="about-{{ $location->id }}" class="block text-base text-ink">
                                    The page on your website that says who you are
                                </label>

                                <input
                                    id="about-{{ $location->id }}"
                                    type="text"
                                    inputmode="url"
                                    autocomplete="url"
                                    wire:model="pastedAboutUrl"
                                    placeholder="{{ $location->website_url }}/about"
                                    class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                />

                                @error('pastedAboutUrl')
                                    <p class="mt-1 text-base text-alert" role="alert">{{ $message }}</p>
                                @enderror

                                <div class="mt-3 flex flex-wrap gap-3">
                                    <x-ui.button type="submit" size="default">
                                        <span wire:loading.remove wire:target="saveAbout">Save</span>
                                        <span wire:loading wire:target="saveAbout">Saving…</span>
                                    </x-ui.button>

                                    <x-ui.button type="button" size="default" variant="secondary" wire:click="cancelAbout">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </form>
                        @else
                            <div class="mt-2">
                                <x-ui.button type="button" size="default" variant="secondary" wire:click="editAbout({{ $location->id }})">
                                    {{ $reach[$location->id]['about'] === null ? 'Add your About page' : 'Change your About page' }}
                                </x-ui.button>
                            </div>
                        @endif
                    @endif

                    {{--
                        ⛔ THIS IS THE PRESS THAT HANDS US WRITE ACCESS TO
                        SOMEBODY ELSE'S WEBSITE. The instructions ask for an
                        Editor rather than an administrator because §19.7's gate
                        refuses a login that can install plugins, edit users or
                        change settings — and the refusal sentence, if they
                        paste one anyway, tells them the same thing again.

                        COLOUR IS NEVER THE SOLE SIGNAL: connected, not
                        connected, and "you still have to remove it yourself"
                        are three sentences, readable in monochrome.
                    --}}
                    @if ($reach[$location->id]['connected'])
                        <div class="mt-3 rounded-[--radius-panel] border border-rule bg-card p-4">
                            <p class="text-base text-ink">
                                We can change this website for you.
                            </p>
                            <p class="mt-1 text-base text-ink-2">
                                Every change is written down and you can undo any of it. Take
                                this away whenever you like.
                            </p>

                            <div class="mt-3">
                                <x-ui.button type="button" size="default" variant="secondary" wire:click="disconnectWordPress({{ $location->id }})">
                                    <span wire:loading.remove wire:target="disconnectWordPress">Stop us changing this website</span>
                                    <span wire:loading wire:target="disconnectWordPress">Disconnecting…</span>
                                </x-ui.button>
                            </div>
                        </div>
                    @elseif ($reach[$location->id]['wordpress'])
                        @if ($connectingLocationId === $location->id)
                            <form wire:submit="connectWordPress" class="mt-3 rounded-[--radius-panel] border border-rule-strong bg-card p-4">
                                <p class="text-base text-ink">Let us make these changes for you</p>

                                <ol class="mt-2 list-decimal space-y-1 pl-5 text-base text-ink-2">
                                    <li>In WordPress, add a new user with the <span class="font-semibold">Editor</span> role.</li>
                                    <li>Open that user, scroll to <span class="font-semibold">Application Passwords</span>, and add one called GO AI EZ.</li>
                                    <li>Copy what WordPress shows you and paste it below. It will not show it again.</li>
                                </ol>

                                <p class="mt-2 text-base text-ink-2">
                                    Please do not give us an administrator. We will not keep a login
                                    that can change your plugins, your users or your settings.
                                </p>

                                <label for="wp-user-{{ $location->id }}" class="mt-4 block text-base text-ink">
                                    The WordPress username
                                </label>
                                <input
                                    id="wp-user-{{ $location->id }}"
                                    type="text"
                                    autocomplete="off"
                                    wire:model="wpUsername"
                                    class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                />

                                @error('wpUsername')
                                    <p class="mt-1 text-base text-alert" role="alert">{{ $message }}</p>
                                @enderror

                                {{--
                                    ⛔ PLAIN `wire:model`, NEVER `.live`. Livewire
                                    defers a plain binding to the next action, so
                                    the secret crosses the wire once with the
                                    press instead of once per keystroke — and
                                    `connectWordPress()` resets it on every path,
                                    so it is not in the response snapshot either.
                                --}}
                                <label for="wp-pass-{{ $location->id }}" class="mt-4 block text-base text-ink">
                                    The application password
                                </label>
                                <input
                                    id="wp-pass-{{ $location->id }}"
                                    type="password"
                                    autocomplete="off"
                                    spellcheck="false"
                                    wire:model="wpPassword"
                                    class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 font-mono text-base text-ink"
                                />

                                @error('wpPassword')
                                    <p class="mt-1 text-base text-alert" role="alert">{{ $message }}</p>
                                @enderror

                                <div class="mt-3 flex flex-wrap gap-3">
                                    <x-ui.button type="submit" size="default">
                                        <span wire:loading.remove wire:target="connectWordPress">Connect</span>
                                        <span wire:loading wire:target="connectWordPress">Checking with your website…</span>
                                    </x-ui.button>

                                    <x-ui.button type="button" size="default" variant="secondary" wire:click="cancelConnect">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </form>
                        @else
                            <div class="mt-2">
                                <x-ui.button type="button" size="default" variant="secondary" wire:click="beginConnect({{ $location->id }})">
                                    Let us make these changes for you
                                </x-ui.button>
                            </div>
                        @endif
                    @endif

                    @if ($removeByHandLocationId === $location->id)
                        {{--
                            ⛔ THE ONE STATE THAT NEEDS THE OWNER TO DO
                            SOMETHING. We have thrown our copy away, and the site
                            would not tell us which password it was — so it is
                            still sitting in their WordPress and only they can
                            remove it. Slice F1 recorded that saying so was owed.
                        --}}
                        <div class="mt-3 rounded-[--radius-panel] border border-rule-strong bg-card p-4">
                            <p class="text-base text-ink">
                                We have let go of your login and we can no longer change this website.
                            </p>
                            <p class="mt-1 text-base text-ink-2">
                                Your website would not let us delete it for you, so it is still
                                listed in WordPress. Open that user, find the application password
                                called GO AI EZ, and remove it.
                            </p>
                        </div>
                    @endif

                    @if ($editingLocationId === $location->id)
                        @if ($pendingLocationId === $location->id && $pendingUrl !== null)
                            <div class="mt-3 rounded-[--radius-panel] border border-rule-strong bg-card p-4">
                                <p class="text-base text-ink">
                                    Is this your website?
                                    <span class="font-mono">{{ $pendingUrl }}</span>
                                </p>
                                <p class="mt-1 text-base text-ink-2">
                                    We will only ever change this website — nothing else.
                                </p>

                                <div class="mt-3 flex flex-wrap gap-3">
                                    <x-ui.button type="button" size="default" wire:click="confirmWebsite">
                                        <span wire:loading.remove wire:target="confirmWebsite">Yes, that is our website</span>
                                        <span wire:loading wire:target="confirmWebsite">Saving…</span>
                                    </x-ui.button>

                                    <x-ui.button type="button" size="default" variant="secondary" wire:click="cancel">
                                        No, let me change it
                                    </x-ui.button>
                                </div>
                            </div>
                        @else
                            <form wire:submit="review" class="mt-3">
                                <label for="website-{{ $location->id }}" class="block text-base text-ink">
                                    The web address of your website
                                </label>

                                <input
                                    id="website-{{ $location->id }}"
                                    type="text"
                                    inputmode="url"
                                    autocomplete="url"
                                    wire:model="pastedUrl"
                                    placeholder="https://yourbusiness.com"
                                    class="mt-1 w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                                />

                                @error('pastedUrl')
                                    <p class="mt-1 text-base text-alert" role="alert">{{ $message }}</p>
                                @enderror

                                <div class="mt-3 flex flex-wrap gap-3">
                                    <x-ui.button type="submit" size="default">
                                        <span wire:loading.remove wire:target="review">Continue</span>
                                        <span wire:loading wire:target="review">Checking…</span>
                                    </x-ui.button>

                                    <x-ui.button type="button" size="default" variant="secondary" wire:click="cancel">
                                        Cancel
                                    </x-ui.button>
                                </div>
                            </form>
                        @endif
                    @else
                        <div class="mt-2">
                            <x-ui.button type="button" size="default" variant="secondary" wire:click="edit({{ $location->id }})">
                                {{ $location->website_url === null ? 'Add your website' : 'Change your website' }}
                            </x-ui.button>
                        </div>
                    @endif

                    {{--
                        A CODE FOR THE COUNTER (6700). `AutopilotActionType::
                        QrGenerated` had a label and no writer, and nothing in
                        this product handed a tenant anything they could put in
                        front of a customer who is standing at the till.

                        ⚠️ HERE RATHER THAN ON A SCREEN OF ITS OWN, AND FOR THIS
                        SCREEN'S OWN REASON: a sign is per-location, and this is
                        the only owner page that lists every location. A screen
                        of its own would need a nav entry an owner has to find
                        first.

                        ⛔ A BLADE PARTIAL DRIVEN FROM HERE, AND IT WAS A
                        NESTED LIVEWIRE COMPONENT UNTIL THE SUITE SAID
                        OTHERWISE (6706). Livewire writes every child into a
                        `wire:snapshot` whose memo contains the key `errors`,
                        and `Content\AuthorBylineTest` asserts this screen shows
                        an owner no such word — 5844's rule about blame in copy,
                        met by framework plumbing. Relaxing somebody else's
                        compliance assertion to make a card printable is the
                        wrong trade; the panel holds no state of its own, so it
                        did not need to be a thing that has state.

                        ⚠️ ONE CARD AT A TIME, WHICH IS THE POINT OF THE
                        `$signLocationId` COMPARISON: the print stylesheet gives
                        the whole page to `.review-sign`, so two open cards would
                        print as two overlapping sheets.
                    --}}
                    <x-account.review-sign
                        :location-id="$location->id"
                        :open="$signLocationId === $location->id"
                        :sign="$signLocationId === $location->id ? $sign : null"
                    />
                </li>
            @empty
                <li>
                    <x-ui.empty-state icon="○" heading="Nothing set up yet">
                        Your plan covers {{ $permitted }}
                        {{ $permitted === 1 ? 'location' : 'locations' }} and none is
                        set up. Ask us and we will set the first one up for you —
                        there is nothing further to pay.
                    </x-ui.empty-state>
                </li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-[--radius-panel] border border-rule-strong bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Need another location?</h2>

        {{--
            ⛔ THIS SCREEN QUOTES AND DOES NOT SELL, WHICH IS T176 P25's OWN
            SHAPE: "operator attaches the SKU per schedule; self-serve +
            proration stay OUT". Adding a location to a plan that is already
            running changes what a payment provider charges partway through a
            cycle, and that question — what a mid-cycle addition costs — has not
            been answered and is the owner's (147–149). A button here would have
            to invent the answer.
        --}}
        <p class="mt-2 text-base text-ink-2">
            Each location beyond your first is {{ $addOnPrice }}
            {{ $term === App\Enums\BillingTerm::Annual ? 'a year' : 'a month' }}.
            Ask us and we will set it up and tell you exactly what changes before
            anything is charged.
        </p>

        @if ($remaining > 0)
            {{--
                ⚠️ THE PAID-FOR-BUT-NOT-YET-SET-UP CASE IS NAMED RATHER THAN
                LEFT SILENT. A business that bought three locations at signup and
                has one is owed two, and a page that showed only the numbers
                would leave them wondering whether they had been charged for
                nothing.
            --}}
            <p class="mt-2 text-base text-ink-2">
                Your plan already covers {{ $remaining }} more than you are using.
                Ask us and we will set {{ $remaining === 1 ? 'it' : 'them' }} up —
                there is nothing further to pay.
            </p>
        @endif
    </div>
</div>

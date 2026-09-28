{{--
    The install screen for the pixel — decision 4979 item (2).

    OUTCOME LANGUAGE, NO INTERNAL VOCABULARY (`22`, `29` §2 rule 47). No
    "pixel", "public key", "collector", "tenant" or "L0" on this page — the
    heading is what the person gets, the box is the one thing they do.

    COLOUR IS NEVER THE SIGNAL (`22`). ⛔ THIS READ "There is no status pill on
    this screen — nothing writes a signal this page could honestly read, so it
    says that in a sentence instead of a coloured dot", AND THE SECOND HALF OF
    THAT WAS ALWAYS FALSE (7800): the signal that mattered was written down all
    along, in `plugins.allowed_domains` and in `ingest_rejects`, and it was
    being rung to an operator. There is a pill now; it carries a word and an
    icon as well as a hue.

    ⛔ AND THE CLAUSE THAT CLOSED THAT CORRECTION WAS FALSE IN ITS TURN —
    CORRECTED 2026-08-22 (7980). It ended "and `PixelCollectionState` has no
    case that renders as `Ok`, because the one thing still unwritten is
    acceptance." Acceptance was written the whole time, by the same job that
    writes the archive, one table over. There is a fifth case now —
    `PixelCollectionState::Collecting` — it is the only one on this page that
    renders `Ok`, and it is reachable only when a derived event row exists for
    this tenant. Both readings are kept because the habit is the subject: each
    correction here has itself contained a sentence explaining why the next
    thing was impossible.

    WORKS AT 320px. One stacked block, nothing below 16px (`text-base`) except
    the supporting line, `text-sm` like every other account screen. The
    snippet is a `<textarea readonly>`, `WidgetInstall`'s own pattern: it
    wraps, it selects with one tap on a phone, and it is reachable by
    keyboard — never a `<pre>`, which is none of those things.
--}}

@php
    use App\Enums\PixelCollectionState;
    use Illuminate\Support\Str;
@endphp

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Let us see your website</h1>
        <p class="mt-1 text-base text-ink-2">
            Put one line on your website — most website editors call this a “custom
            code”, “HTML” or “embed” block. It is what lets us watch how your website is
            doing and show you the effect of every change we make. If someone else looks
            after your site, send them this line.
        </p>
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <h2 class="font-display text-lg font-semibold text-ink">Your line</h2>

        <label class="mt-4 flex flex-col gap-1">
            <span class="text-sm font-medium text-ink-2">Paste this into your website</span>
            <textarea
                readonly
                rows="3"
                onclick="this.select()"
                class="w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 font-mono text-base text-ink"
            >{{ $snippet }}</textarea>
        </label>

        <p class="mt-2 text-sm text-ink-2">
            This line is yours alone. It is safe to publish — it does not carry your
            name, your customers' names, or anything else about your business, only
            what it needs to find its way back to us.
        </p>
    </div>

    <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-display text-lg font-semibold text-ink">Once it is on your site</h2>
            <x-ui.status-pill :state="$collection->state->signal()" :label="$collection->state->label()" />
        </div>

        {{--
            ⛔ THIS SECTION SAID "Once it is on your site, there is nothing else
            to do — leave it in place and everything else builds from what it
            sees over time." IT WAS FALSE FOR EVERY TENANT ON THE DAY THEY WERE
            SHOWN IT (7800). A new account lists no website, the collector takes
            nothing from a website the account does not list (4968), and the
            endpoint answers 204 either way — so the sentence, the browser and
            the operator alert all agreed, and only the archive disagreed.

            ⛔ AND THE PARAGRAPH UNDER IT SENT THEM TO THE NETWORK TAB, which is
            the one place guaranteed to show success on a refusal. Gone too.

            ⛔ AND THE PARAGRAPH THAT STOOD HERE SAID THE POSITIVE WAS
            IMPOSSIBLE, WHICH IS THE THIRD TIME THIS FILE HAS DONE IT (7980).
            It read: "NO 'connected ✓' HAS ARRIVED IN THEIR PLACE, and the class
            docblock says why: nothing writes a last-seen for accepted traffic,
            so this screen still cannot claim a positive. What it can do is stop
            claiming one by implication — `PixelCollectionState::Listening`
            carries that limit in its own sentence."

            ✅ THE POSITIVE IS HERE AND IT IS A TIME, NOT A TICK. The line below
            renders when something this tenant's own pixel sent was last
            accepted and kept. ⚠️ IT IS RENDERED ON EVERY STATE, not only on the
            green one: a tenant with one site collecting and another turned away
            needs both facts, and the pill carries the one they can act on.
            ⚠️ AND IT IS RELATIVE ("two minutes ago") RATHER THAN A CLOCK TIME,
            which is not decoration — the stored value is UTC and this page has
            no idea what timezone the person reading it is in, so an absolute
            time would be wrong by hours for most owners.

            ⛔ AND WHEN THE LINE BELOW DOES NOT RENDER, THIS PAGE SPENT A WEEK
            EXPLAINING THE SILENCE AS A FACT ABOUT THE READER'S OWN BUSINESS —
            CORRECTED 2026-08-26 (9900). `PixelCollectionState::Listening` said
            "a website has to be visited before there is anything to send"
            while `Storage::disk('s3')` could not be built on any deployment
            (9408, 9421), so every accepted beacon was failing into
            `failed_jobs` and the derived layer this reads was empty for a
            reason that had nothing to do with anybody's visitors. There is a
            sixth state now — `PixelCollectionState::AcceptedNotShown` — and it
            is the only one on this page whose subject is us.
        --}}
        <p class="mt-3 text-base text-ink-2">{{ $collection->state->explanation() }}</p>

        @if ($collection->lastArrivedAt !== null)
            <p class="mt-3 text-base text-ink">
                We last heard from your website {{ $collection->lastArrivedAt->diffForHumans() }}.
            </p>
        @endif

        @if ($collection->state === PixelCollectionState::NoWebsiteListed)
            <p class="mt-3 text-base text-ink">
                {{--
                    ⚠️ AN INVITATION OUT OF AN EMPTY STATE, WHICH IS THE ONE
                    SHAPE `Architecture/OwnerNavTest` ADMITS — the customers
                    list's link into Import, for its reason: this is advice for
                    somebody who has nothing, not a second navigation
                    convention. The list lives on that screen because it is one
                    list (4968) and a second one would be two screens that can
                    disagree.
                --}}
                Add your website on
                <a href="{{ route('account.website') }}" class="font-medium text-ink underline">Reviews on your website</a>.
                That list is the same one we take visits from.
            </p>
        @elseif ($collection->state === PixelCollectionState::TrafficRefused)
            <p class="mt-3 text-base text-ink">
                Add the missing address on
                <a href="{{ route('account.website') }}" class="font-medium text-ink underline">Reviews on your website</a>.
            </p>
        @endif

        {{--
            ⛔ THERE IS DELIBERATELY NO BRANCH HERE FOR
            `PixelCollectionState::AcceptedNotShown`, AND THE ABSENCE IS THE
            DESIGN RATHER THAN AN OMISSION (9902). Both branches above hand the
            owner a door because both states are theirs to fix; that one is
            ours, so a link would be sending somebody to edit a list that is
            already correct — which is how a platform fault becomes a tenant
            deleting a working website from their allowlist. The state's own
            sentence carries the only action there is, and it is "tell us".
        --}}

        {{--
            ⚠️ WHAT WE ACCEPT, NAMED RATHER THAN SUMMARISED. An owner with two
            websites needs to see which of them is written down — that is the
            difference between "your tracking is broken" and a job that takes
            two minutes. Rendered exactly as stored, because that is the string
            the gate compares (`WidgetPlugins::acceptedHosts()`).

            empty-state: absent because the four states above already say what
            an empty list means, in a sentence with the action beside it — a
            second "no websites listed" here would be the same news twice on
            the one screen somebody opens when something is wrong.
        --}}
        @if ($collection->listedSites !== [])
            <h3 class="mt-5 text-base font-semibold text-ink">Websites we accept visits from</h3>

            <ul class="mt-2 space-y-2">
                @foreach ($collection->listedSites as $site)
                    <li wire:key="listed-{{ $loop->index }}" class="border-t border-rule pt-2 font-mono text-base break-all text-ink">
                        {{ Str::limit($site, 120) }}
                    </li>
                @endforeach
            </ul>
        @endif

        {{--
            ⛔ THE ONE PLACE A STRANGER'S TEXT REACHES AN ACCOUNT SCREEN.
            `ingest_rejects.origin` is the `Origin` request header — up to 512
            bytes the caller wrote — so this copies `Admin\OperatorAlertBoard`'s
            treatment of the identical value rather than inventing a second one:
            Blade auto-escape, clipped at 120, mono and `break-all`, so it reads
            as evidence somebody has to judge and never as a sentence this
            platform is asserting. There is no `{!! !!}` on this page and there
            must never be.

            ⚠️ THE HEADING NAMES THE ONE REASON THIS LIST CAN PROVE.
            `IngestRejects::record()` has a single call site, on
            `PixelRefusal::OriginNotAllowed`, and that enum has eight cases — so
            "turned away because the address was not on your list" is exactly
            what these rows are, and "everything we dropped" would be a claim
            the table cannot support.

            empty-state: absent because an empty refusal list is not a state an
            owner can act on — it means either nothing arrived or everything
            arrived, and this reader cannot tell those apart. Saying "nothing
            was turned away" to somebody whose line is not installed would be a
            null result dressed as a diagnosis (3084).
        --}}
        @if ($collection->refusals !== [])
            <h3 class="mt-5 text-base font-semibold text-ink">
                Turned away in the last {{ $collection->windowDays() }} days, because the address was not on your list
            </h3>

            <ul class="mt-2 space-y-2">
                @foreach ($collection->refusals as $refusal)
                    <li wire:key="refused-{{ $loop->index }}" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-t border-rule pt-2 text-base text-ink">
                        @if ($refusal->isAddress())
                            <span class="font-mono break-all">{{ Str::limit((string) $refusal->origin, 120) }}</span>
                        @else
                            <span>{{ $refusal->summary() }}</span>
                        @endif

                        <span class="text-sm text-ink-2">
                            {{ $refusal->rejects }} {{ Str::plural('visit', $refusal->rejects) }},
                            last {{ $refusal->lastAt->diffForHumans() }}
                        </span>
                    </li>
                @endforeach
            </ul>

            @if ($collection->otherAddresses > 0)
                <p class="mt-2 text-sm text-ink-2">
                    And {{ $collection->otherAddresses }} other
                    {{ Str::plural('address', $collection->otherAddresses) }}.
                </p>
            @endif
        @endif
    </div>
    <livewire:x-110.install-verify :business-id="\App\Support\Tenancy::id()" />
    <livewire:x-110.abandoned-forms :business-id="\App\Support\Tenancy::id()" />
    <livewire:x-138.attribution-row :business-id="\App\Support\Tenancy::id()" />
</div>

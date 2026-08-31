{{--
    Your reviews on your website (`29` §7, `41` §3.2).

    ⚠️ THERE IS NO RATING FILTER ON THIS SCREEN AND THERE IS NOT GOING TO BE
    ONE (2951–2955). `min_stars_to_show` exists in the schema and the feed reads
    it, and giving it a control here would be a standing rating filter on a
    public surface — FTC 16 CFR §465.7 review suppression — that also reaches
    Google reviews, which rule 1 forbids outright. The screen says plainly what
    it does instead of leaving the absence to be read as an oversight.

    OUTCOME LANGUAGE, NO INTERNAL VOCABULARY (`22`, `29` §2 rule 47). No
    "allowlist", no "origin", no "embed key", no "CORS". The heading is what the
    person gets; the box asks for their website.

    COLOUR IS NEVER THE SIGNAL (`22`). Whether the feed is live is a sentence
    somebody can read, never a dot or a hue.

    WORKS AT 320px. Stacked blocks, nothing below 16px (`text-base`) except the
    supporting lines, which are `text-sm` like every other account screen. The
    snippet is a `<textarea readonly>` rather than a `<pre>`: it wraps, it
    selects with one tap on a phone, and it is reachable by keyboard.
--}}

<div class="space-y-8">
    <div>
        <h1 class="font-display text-2xl font-semibold text-ink">Your reviews on your website</h1>
        <p class="mt-1 text-base text-ink-2">
            Put one line on your website and the reviews you have approved will show there,
            keeping themselves up to date. Nothing else changes on the page, and taking the
            line back out removes it completely.
        </p>
    </div>

    {{--
        ⛔ WITHOUT THIS, A MULTI-LOCATION TENANT SAW "Not ready yet" AND HAD NO
        ROUTE TO THEIR REVIEW FEED AT ALL (2969). A plugin is minted per
        location, so this screen has to know which one it means; the picker is
        what lets it answer, and it renders itself away for the one-location
        tenant who has nothing to choose between.
    --}}
    <x-account.location-picker :locations="$locationOptions" :selected="$selectedLocation" />

    @if ($available)
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Paste this into your website</h2>

            <p class="mt-2 text-base text-ink-2">
                Put it wherever you want the reviews to appear — most website editors call
                this a “custom code”, “HTML” or “embed” block. If someone else looks after
                your site, send them this line.
            </p>

            <label class="mt-4 flex flex-col gap-1">
                <span class="text-sm font-medium text-ink-2">Your line</span>
                <textarea
                    readonly
                    rows="3"
                    onclick="this.select()"
                    class="w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 font-mono text-base text-ink"
                >{{ $snippet }}</textarea>
            </label>

            <p class="mt-2 text-sm text-ink-2">
                This line is yours alone. It is safe to publish — it shows the reviews you
                have approved and nothing else about your customers.
            </p>
        </div>

        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Where it is allowed to show</h2>

            <p class="mt-2 text-base text-ink-2">
                Tell us which websites are yours. Your reviews will show on the ones you
                list here and nowhere else, so nobody can put your reviews on their own
                site by copying the line above.
            </p>

            {{--
                ⛔ THIS LIST DOES TWO JOBS AND THE SCREEN NAMED ONE OF THEM (7801).
                `PixelCollector` answers §11 row 2 from this same allowlist (4968) —
                there is deliberately only one — so a website missing from this box
                is one we take no measurements from either, and until 7800 nothing
                on either screen said so. The sentence is here rather than only on
                the other screen because THIS is where the list is written: a person
                deciding what to type deserves to know the whole consequence of
                leaving it empty.

                No link back. `Architecture/OwnerNavTest` refuses a cross-link
                between owner screens and the exception it admits is an empty
                state's invitation — this is neither an empty state nor an
                invitation, so it is a sentence.
            --}}
            <p class="mt-2 text-base text-ink-2">
                This is also the list we watch for you. If you have put our other line on a
                website — the one under “Let us see your website” — it only reaches us from
                the sites named here.
            </p>

            {{--
                Stated rather than left to be discovered. The list starts empty and an
                empty list serves nobody, which is the right direction to fail and the
                opposite of what an empty box usually means — a tenant who reads this
                screen and closes it without typing has a feed that shows nothing.
            --}}
            @if (! $live)
                <p class="mt-3 text-base text-ink" role="status">
                    Not showing anywhere yet. Add your website below to switch it on.
                </p>
            @endif

            @if ($mayEdit)
                <form wire:submit="save" class="mt-5 space-y-4">
                    <label class="flex flex-col gap-1">
                        <span class="text-sm font-medium text-ink-2">Your website addresses</span>
                        <textarea
                            wire:model="domains"
                            rows="4"
                            spellcheck="false"
                            autocapitalize="none"
                            placeholder="ledger.test"
                            class="w-full rounded-[--radius-control] border border-rule bg-card px-3 py-2 text-base text-ink"
                        ></textarea>
                        <span class="text-sm text-ink-2">
                            One per line, written the way it appears in the address bar. If your
                            site answers on both <span class="font-mono">example.com</span> and
                            <span class="font-mono">www.example.com</span>, list both — we match
                            exactly what you write.
                        </span>
                    </label>

                    @error('domains')
                        <p class="text-base text-alert" role="alert">{{ $message }}</p>
                    @enderror

                    <x-ui.button type="submit">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </x-ui.button>
                </form>
            @else
                {{--
                    Named rather than hidden, and never a disabled button. A greyed-out
                    control reads as a bug in our page; a sentence naming who can do this
                    reads as the truth and tells them who to ask. The snippet above stays
                    visible — pasting it is exactly the job this person is likely doing.
                --}}
                <p class="mt-4 text-base text-ink-2">
                    Deciding which websites show your reviews is an owner or manager
                    decision, so ask one of them to add it. You can still copy the line
                    above and put it on the site.
                </p>
            @endif
        </div>

        {{--
            Is it actually working? (2969, 3080.)

            ⚠️ THE HONEST STATE IS "NOT SEEN YET", NEVER "NOT INSTALLED" (3084).
            We learn an install works by watching a real visitor load a real
            page, so a perfectly pasted line on a page nobody has opened looks
            exactly like no line at all. Telling that owner they had failed
            would be a false accusation from a check that cannot see the
            difference — so the copy names the one thing that resolves it, which
            is the owner opening their own website.

            NO NUMBERS HERE, AND NONE TO SHOW (3085). We keep a first-seen and a
            last-seen per website and deliberately no count: a visit counter on
            somebody else's site is traffic analytics collected as a side effect
            of an install check.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <div class="flex flex-wrap items-center gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">Is it working?</h2>
                <x-ui.status-pill :state="$install->state->signal()" :label="$install->state->label()" />
            </div>

            {{--
                ⚠️ A TENANT WHO HAS NAMED NO WEBSITE GETS A DIFFERENT SENTENCE,
                because the standard one would send them to do something that
                cannot possibly work. "Open your website in a browser" is right
                only once a website is listed — until then the feed refuses every
                request by design (the empty allowlist of 2950), so visiting the
                page could never turn this green, and an owner who followed that
                instruction and saw nothing change would conclude we are broken.
                A null result must not be dressed up as a diagnosis.
            --}}
            @if (! $live)
                <p class="mt-2 text-base text-ink-2">
                    We will start checking once you have added your website above.
                </p>
            @else
                <p class="mt-2 text-base text-ink-2">{{ $install->state->explanation() }}</p>
            @endif

            @if ($install->sightings !== [])
                {{--
                    Which website, not how many visits. An owner with two sites
                    needs to know the shop is fine and the blog is missing —
                    that is the difference between "your widget is broken" and a
                    job that takes two minutes.
                --}}
                <ul class="mt-4 space-y-2">
                    @foreach ($install->sightings as $sighting)
                        <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-t border-rule pt-2 text-base text-ink">
                            <span class="font-mono">{{ $sighting->host }}</span>
                            <span class="text-sm text-ink-2">
                                Last showed {{ $sighting->last_seen_at->diffForHumans() }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">What shows</h2>

            <p class="mt-2 text-base text-ink-2">
                Every review you have approved for your website shows here, whatever its
                rating. We do not hide low ratings and we will not build a way to — the
                law treats filtering your own reviews by star rating as suppressing them,
                and for reviews left on Google it is not ours to touch at all.
            </p>

            <p class="mt-2 text-base text-ink-2">
                Decide what appears one review at a time, on your reply and approval
                screens. Nothing is deleted either way.
            </p>
        </div>
    @else
        {{--
            Absent rather than disabled when there is no single location (1220). A feed
            belongs to one location, this application has no location picker, and a
            screen that guessed would put one branch's reviews on another's website.
        --}}
        <div class="rounded-[--radius-panel] border border-rule bg-card p-5">
            <h2 class="font-display text-lg font-semibold text-ink">Not ready yet</h2>
            <p class="mt-2 text-base text-ink-2">
                We set this up once your business has a single place we can show reviews
                for. Ask us and we will finish it off.
            </p>
        </div>
    @endif
</div>

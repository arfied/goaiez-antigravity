{{--
    The home page, where the audit IS the hero (`29` §6.1).

    NO TESTIMONIALS AND NO COUNTERS, deliberately (decision 260). The reference
    site at docs/reference/marketing-site fills its proof band with three named
    customer quotes and animated totals — 184,320 reviews generated, and so on.
    Those are invented. There are no customers yet, `29` §2 forbids fabricated
    social proof outright, and §6.1 requires proof to be "real platform
    aggregates, k>=8 cohorts". So the band is gone rather than faked.

    What replaces it is better anyway: the audit is the proof. A stranger types
    their own business name and sees true findings about their own listing in
    under twenty seconds. A quote card cannot compete with that, and it has the
    additional advantage of being true.
--}}

<x-marketing.layout
    title="See what Google sees about your business"
    description="A free check of your Google Business Profile, your reviews and your website. No account, no card, about twenty seconds."
>
    <!-- Hero Section with Sleek Background Glow -->
    <div class="relative overflow-hidden pt-12 pb-16 sm:pt-20 sm:pb-24 border-b border-rule">
        <div class="absolute inset-0 -z-10 flex items-center justify-center">
            <div class="h-[32rem] w-[50rem] rounded-full bg-gradient-to-tr from-indigo-500/10 via-purple-500/5 to-transparent blur-3xl"></div>
        </div>

        <section class="mx-auto w-full max-w-4xl px-4 text-center">
            <!-- Announcement / Feature Pill -->
            <div class="inline-flex items-center gap-2 rounded-full border border-rule bg-card px-3.5 py-1.5 text-xs font-medium text-ink shadow-sm mb-6">
                <span class="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Autonomous Local Growth Engine</span>
                <span class="text-ink-3">·</span>
                <span class="text-indigo-600 dark:text-indigo-400">Zero Signup Required</span>
            </div>

            <h1 class="font-display text-4xl font-semibold tracking-tight text-balance text-ink sm:text-6xl sm:leading-[1.1]">
                See what Google sees about your business.
            </h1>

            <p class="mx-auto mt-6 max-w-2xl text-lg sm:text-xl text-ink-2 leading-relaxed">
                We check your Google listing, your reviews and your website, then tell you
                plainly what is costing you customers. It takes about twenty seconds and
                you do not need an account.
            </p>

            <div
                data-audit
                data-suggest-url="{{ route('api.public.audit.suggest') }}"
                data-start-url="{{ route('api.public.audit.store') }}"
                data-result-url="{{ route('audit.result', ['token' => '__TOKEN__']) }}"
                data-share-url="{{ route('audit.show', ['token' => '__TOKEN__']) }}"
                data-turnstile-key="{{ $turnstileSiteKey }}"
                class="mt-10"
            >
                <form data-audit-form class="mx-auto flex w-full max-w-xl flex-col gap-3 sm:flex-row shadow-lg rounded-[--radius-control] p-1.5 bg-card border border-rule-strong focus-within:ring-2 focus-within:ring-ink focus-within:border-transparent transition">
                    <div class="relative flex-1 text-left flex items-center">
                        <svg class="ml-3 h-5 w-5 text-ink-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>

                        <label for="business-name" class="sr-only">Enter your business name</label>

                        <input
                            id="business-name"
                            name="business"
                            type="text"
                            data-audit-input
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Enter your business name (e.g. Rachel Taylor Clinic)"
                            aria-describedby="business-name-help"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="business-suggestions"
                            class="min-h-12 w-full border-none bg-transparent px-3 text-base text-ink placeholder:text-ink-3 focus:outline-none focus:ring-0"
                        >

                        {{-- Suggestions are a listbox so a screen reader announces them as options. --}}
                        <ul
                            id="business-suggestions"
                            data-audit-suggestions
                            role="listbox"
                            aria-label="Matching businesses"
                            hidden
                            class="absolute left-0 top-full z-20 mt-2 w-full overflow-hidden rounded-[--radius-card] border border-rule bg-card shadow-xl"
                        ></ul>
                    </div>

                    <x-ui.button type="submit" data-audit-submit class="h-12 px-6 font-semibold shadow">Check it</x-ui.button>
                </form>

                <div class="mt-4 flex items-center justify-center gap-4 text-xs text-ink-3">
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        100% Free Audit
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        No Card Required
                    </span>
                    <span class="flex items-center gap-1">
                        <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        Instant 20-Second Analysis
                    </span>
                </div>

                {{--
                    Turnstile after the first audit (`29` §6.2, decision 194). The
                    script is fetched on first interaction rather than on load, so it
                    is never in the LCP path and never fires before a consent banner
                    that does not exist yet.
                --}}
                <div data-audit-turnstile hidden class="mt-4 flex justify-center"></div>

                <p
                    data-audit-error
                    role="alert"
                    hidden
                    class="mx-auto mt-4 max-w-xl rounded-[--radius-card] border border-rule bg-alert-bg px-4 py-3 text-base text-alert"
                ></p>

                {{-- The result lands here, rendered by the server (decision 258). --}}
                <div data-audit-result-target class="mt-12"></div>
            </div>
        </section>
    </div>

    <!-- Core Platform Pillars Strip -->
    <section class="mx-auto w-full max-w-5xl px-4 py-12" aria-labelledby="the-suite">
        <h2 id="the-suite" class="sr-only">What you get</h2>

        <ul class="grid gap-6 sm:grid-cols-3">
            @foreach ([
                ['Never miss a call again', 'Every missed call gets a text back in 60 seconds.', '📞'],
                ['Reviews on autopilot', 'Happy customers asked at the right moment.', '⭐'],
                ['A front desk that never sleeps', 'Booked, answered, handled.', '🤖'],
            ] as [$heading, $body, $icon])
                <li class="rounded-[--radius-card] border border-rule bg-card p-6 shadow-sm hover:border-rule-strong transition">
                    <div class="text-2xl mb-3">{{ $icon }}</div>
                    <h3 class="font-display text-lg font-semibold text-ink">{{ $heading }}</h3>
                    <p class="mt-2 text-base text-ink-2">{{ $body }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <!-- How It Works Flow -->
    <section class="border-y border-rule bg-card py-16 sm:py-20" aria-labelledby="how-it-works">
        <div class="mx-auto w-full max-w-5xl px-4">
            <div class="text-center max-w-2xl mx-auto mb-12">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">The Loop</span>
                <h2 id="how-it-works" class="mt-1 font-display text-3xl font-semibold text-ink sm:text-4xl">
                    How it works
                </h2>
                <p class="mt-3 text-base text-ink-2">
                    An autonomous cycle that monitors, diagnoses, repairs, and measures your local visibility.
                </p>
            </div>

            <ol class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['Watch', 'We keep an eye on your listing, your reviews, your website and the calls you miss.'],
                    ['Decide', 'We work out which single thing is costing you the most right now.'],
                    ['Fix', 'We do it — the post, the reply, the page, the follow-up text.'],
                    ['Prove', 'We measure whether it worked, and undo it if it did not.'],
                ] as $index => [$step, $body])
                    <li class="relative rounded-[--radius-card] border border-rule bg-paper p-6 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-ink text-paper font-mono text-xs font-bold mb-4">
                                {{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}
                            </div>
                            <h3 class="font-display text-xl font-semibold text-ink">{{ $step }}</h3>
                            <p class="mt-2 text-sm text-ink-2 leading-relaxed">{{ $body }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            {{--
                `29` §6.1 asks the home for "honest scope" in as many words. This is
                it.
            --}}
            <div class="mt-12 rounded-[--radius-card] border border-rule bg-paper p-6 text-center max-w-2xl mx-auto shadow-sm">
                <p class="text-sm font-medium text-ink-2 leading-relaxed">
                    🛡️ <strong>Our Promise:</strong> We do not promise rankings, and nobody can. What we promise is that the work
                    gets done, that you can see it, and that anything which makes things worse
                    gets reversed.
                </p>
            </div>
        </div>
    </section>

    <!-- What It Does For You Outcomes -->
    <section class="mx-auto w-full max-w-5xl px-4 py-16 sm:py-20" aria-labelledby="what-it-does">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Tangible Business Value</span>
            <h2 id="what-it-does" class="mt-1 font-display text-3xl font-semibold text-ink sm:text-4xl">
                What it does for you
            </h2>
            <p class="mt-3 text-base text-ink-2">
                Focused on local business outcomes, never confusing jargon.
            </p>
        </div>

        <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Get found', 'Your listing complete and current, your hours right, your details the same everywhere anyone looks.', '📍'],
                ['Get chosen', 'More reviews from happy customers, and every review answered in your voice.', '⭐'],
                ['Get contacted', 'Missed calls texted back in seconds, and questions answered while the person is still deciding.', '📱'],
                ['Get booked', 'Open slots filled, reminders sent, deposits taken where you want them.', '📅'],
                ['Get reviewed', 'The ask goes out at the right moment, to the right customer, through the channel they already use.', '💬'],
                ['Get them back', 'An unhappy customer reaches you before they reach a review page, and quiet regulars hear from you again.', '🔄'],
            ] as [$outcome, $body, $icon])
                <li class="rounded-[--radius-card] border border-rule bg-card p-6 shadow-sm hover:border-rule-strong transition">
                    <div class="text-2xl mb-2">{{ $icon }}</div>
                    <h3 class="font-display text-lg font-semibold text-ink">{{ $outcome }}</h3>
                    <p class="mt-2 text-sm text-ink-2 leading-relaxed">{{ $body }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    @if ($industryPages)
        <section class="border-t border-rule bg-card py-16 text-center" aria-labelledby="hundred-kinds">
            <div class="mx-auto w-full max-w-3xl px-4">
                <h2 id="hundred-kinds" class="font-display text-2xl font-semibold text-balance text-ink sm:text-3xl">
                    A hundred kinds of local business. One front desk.
                </h2>

                <p class="mx-auto mt-4 max-w-xl text-base text-ink-2">
                    Plumbers to law offices, salons to swim schools — find yours and text its demo.
                </p>

                <div class="mt-8 flex justify-center">
                    <x-ui.button :href="route('industries.index')" variant="secondary">
                        See your industry →
                    </x-ui.button>
                </div>
            </div>
        </section>
    @endif

    <!-- Pricing Section -->
    <section class="border-t border-rule bg-paper py-16 sm:py-20 text-center" aria-labelledby="pricing">
        <div class="mx-auto w-full max-w-4xl px-4">
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Simple & Predictable</span>
            <h2 id="pricing" class="mt-1 font-display text-3xl font-semibold text-ink sm:text-4xl">
                What it costs
            </h2>
            <p class="mt-3 text-base text-ink-2">
                Start with a 14-day free trial. No surprise fees.
            </p>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 max-w-3xl mx-auto">
                <div class="rounded-[--radius-card] border border-rule bg-card p-8 text-left shadow-sm flex flex-col justify-between">
                    <div>
                        <h3 class="font-display text-xl font-semibold text-ink">Free</h3>
                        <p class="mt-2 font-display text-4xl font-semibold tabular-nums text-ink">{{ $free }}</p>
                        <p class="mt-4 text-sm text-ink-2">
                            The watching, without the doing. Your score, your alerts, one location.
                        </p>

                        <ul class="mt-6 space-y-2.5 text-xs text-ink-2 border-t border-rule pt-4">
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Google Listing & SEO Monitoring</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Weekly Health Score Telemetry</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Single Catchment Location</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-8">
                        <x-ui.button :href="route('start')" variant="secondary" class="w-full">Get started free</x-ui.button>
                    </div>
                </div>

                <div class="relative rounded-[--radius-card] border-2 border-indigo-600 bg-card p-8 text-left shadow-md flex flex-col justify-between">
                    <div class="absolute -top-3.5 right-6 rounded-full bg-indigo-600 px-3 py-0.5 text-xs font-semibold text-white uppercase tracking-wider">
                        Full Autopilot
                    </div>

                    <div>
                        <h3 class="font-display text-xl font-semibold text-ink">Everything</h3>
                        <p class="mt-2 font-display text-4xl font-semibold tabular-nums text-ink">
                            {{ $monthly }}<span class="text-base font-normal text-ink-2">/month</span>
                        </p>
                        <p class="mt-1 text-xs text-ink-3 tabular-nums">
                            or {{ $annual }}/year · each extra location {{ $locationMonthly }}/month or {{ $locationAnnual }}/year
                        </p>
                        <p class="mt-4 text-sm text-ink-2">
                            The whole system, working on your business while you reply to the odd text.
                        </p>

                        <ul class="mt-6 space-y-2.5 text-xs text-ink-2 border-t border-rule pt-4">
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Missed Call Instant SMS Text-Back</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Autopilot Review Ingestion & Responses</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>NAP Directory Citations & Sync</span>
                            </li>
                            <li class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Automated SEO Speed & Schema Fixes</span>
                            </li>
                        </ul>
                    </div>

                    <div class="mt-8">
                        <x-ui.button :href="route('start')" class="w-full">Start 14-day free trial</x-ui.button>
                    </div>
                </div>
            </div>

            <p class="mt-8 text-sm text-ink-2">
                {{ $trialDays }} days free to start. No card required, and you can cancel in one click.
            </p>
        </div>
    </section>
</x-marketing.layout>

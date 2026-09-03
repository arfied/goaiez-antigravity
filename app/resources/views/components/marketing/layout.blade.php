@props([
    'title' => null,
    'description' => 'See what Google sees about your business. A free check in under a minute, no account needed.',
    'noindex' => false,
])

{{--
    The shell for every public, signed-out page.

    Separate from components/auth/layout because the two have opposite jobs: the
    auth shell is a single centred card with no navigation, deliberately offering
    a person mid-sign-in nowhere else to go. This one is a site.

    LCP IS A GATE ON THIS FILE, not just on the pages using it (`29` §11.2 row 1:
    "marketing home renders <1.5s LCP"). Three things here serve that and should
    not be undone casually:

      - the only render-blocking request is one stylesheet. Vite emits the JS as
        a deferred module, and @fonts self-hosts through Bunny at build time, so
        no third party is in the critical path
      - the headline is plain text in the initial HTML. Text LCP needs no image
        decode and no layout pass waiting on a network response, which is most
        of the budget spent for free
      - there is no Livewire runtime on these pages. The audit talks to the
        public JSON API directly (decision 259)

    NAV LINKS ONLY WHERE SOMETHING EXISTS. `29` §6.1 lists eleven marketing
    pages and slice G2 builds three; a nav full of 404s is worse than a short
    nav, so this grows as the pages do (decision 261).
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{--
        Required, not decorative. bootstrap/app.php calls statefulApi(), so
        Sanctum applies the web middleware — session and CSRF included — to
        /api/* requests coming from a stateful domain. A browser POST from this
        page to the public audit endpoint is exactly that, so without this tag
        the audit returns 419 in production while passing every feature test,
        because the test harness disables CSRF.
    --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' · '.config('app.name') : config('app.name') }}</title>
    <meta name="description" content="{{ $description }}">

    @if ($noindex)
        {{--
            Decision 192: an audit result is link-shareable and never listed.
            The token is unguessable, but a search engine that finds one shared
            link would publish somebody's business diagnosis, so the page says
            no explicitly rather than relying on the token being secret.
        --}}
        <meta name="robots" content="noindex, nofollow">
    @endif

    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-paper font-sans text-base text-ink">
    {{-- Keyboard users reach the content without tabbing the whole nav (WCAG 2.2 AA). --}}
    <a
        href="#main"
        class="sr-only rounded-[--radius-control] bg-card px-4 py-2 focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:ring-2 focus:ring-ink"
    >Skip to content</a>

    <header class="border-b border-rule">
        <nav class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4" aria-label="Main">
            <a href="{{ route('home') }}" class="font-display text-lg font-semibold tracking-tight text-ink">
                {{ config('app.name') }}
            </a>

            <div class="flex items-center gap-2">
                @auth
                    <x-ui.button :href="auth()->user()->role?->isPlatformStaff() ? route('admin.automation-runs') : route('account.home')" size="default">
                        Go to dashboard &rarr;
                    </x-ui.button>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="rounded-[--radius-control] px-3 py-2 text-base text-ink-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                    >Sign in</a>

                    <x-ui.button :href="route('start')" size="default">Start free</x-ui.button>
                @endauth
            </div>
        </nav>
    </header>

    <main id="main">
        {{ $slot }}
    </main>

    <footer class="mt-20 border-t border-rule">
        {{--
            THE SITE NAVIGATION LIVES HERE RATHER THAN IN THE HEADER, AND THAT IS
            TWO GATES RATHER THAN A PREFERENCE (CC-2, decision 5202). `29` §5.5
            requires 320px, where the header already carries the wordmark, Sign in
            and Start free with nothing to spare; and row 1's LCP budget is measured
            on this layout, so every link in the header is markup ahead of the
            headline. Decision 261's rule is satisfied either way — these link only
            to pages that exist, and the list grows as the surface does.
        --}}
        <nav
            aria-label="Site"
            class="mx-auto w-full max-w-6xl border-b border-rule px-4 py-8"
        >
            <ul class="flex flex-wrap gap-x-6 gap-y-2 text-base text-ink-2">
                @foreach ([
                    ['pricing', 'Pricing'],
                    ['features', 'What it does'],
                    ['compare', 'Compare'],
                    ['guarantee', 'Guarantee'],
                    ['faq', 'Questions'],
                    ['customers', 'Customers'],
                    ['affiliates', 'Affiliates'],
                    ['agencies', 'Agencies'],
                ] as [$routeName, $label])
                    <li>
                        <a
                            href="{{ route($routeName) }}"
                            class="underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                        >{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-8 text-sm text-ink-2 sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ config('app.name') }}</p>

            {{--
                Decision 261's rule, satisfied rather than bent: these link only
                to pages that exist. Both are also the addresses a 10DLC campaign
                registration files (2120), and a reviewer looks for the privacy
                policy in the footer before looking anywhere else.
            --}}
            <p class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <a
                    href="{{ route('sms-optin') }}"
                    class="underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >Text messages</a>
                <a
                    href="{{ route('sms-terms') }}"
                    class="underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >SMS terms</a>
                <a
                    href="{{ route('privacy') }}"
                    class="underline underline-offset-2 hover:text-ink focus-visible:ring-2 focus-visible:ring-ink focus-visible:outline-none"
                >Privacy</a>
            </p>

            {{--
                Outcome language (`22`), and a claim we can actually stand
                behind. `29` §2 forbids promising rankings; what this promises is
                what the audit literally does.

                ⛔ **THIS SAID `MarketingStringTest` ENFORCES IT AND NO FILE OF
                THAT NAME HAS EVER EXISTED IN THIS REPOSITORY — CORRECTED
                2026-08-25 (9660).** BUILD-PLAN §2.5.3 asks for a "marketing
                string linter" and that is the *prose* name of the thing; the
                file that implements it is `tests/Feature/MarketingPagesTest.php`.
                The rule underneath is `29` §2's *"Never claim guaranteed
                rankings"*, so the sentence that stopped the next reader looking
                for an instrument was standing over a compliance rule.

                ⚠️ **WHAT IS HELD, AND BY WHAT.** That this sentence is on every
                page of the signed-out funnel is
                `Architecture/MarketingTest`'s *"the honest-scope claim is on the
                home and its footer promise is on every page of the funnel"*,
                added with this correction — until then the footer could be
                emptied with every test green. That no page of that funnel
                promises a ranking is `MarketingPagesTest`'s *"no marketing page
                promises a ranking"*, a denylist of phrases over the rendered
                pages, which fails open on a phrasing nobody listed.

                ⛔ **AND THE HALF THIS COMMENT ORIGINALLY CLAIMED IS THE HALF NO
                LINT CAN HOLD.** *"What this promises is what the audit literally
                does"* is a correspondence between a sentence of English and a
                service's behaviour. Nothing tests it, nothing can, and it is
                owed by whoever next changes either one. It is written down here
                rather than left implied, because an unqualified "a test enforces
                this" is what stops anybody checking.
            --}}
            <p>We show you what Google shows your customers.</p>
        </div>
    </footer>
</body>
</html>

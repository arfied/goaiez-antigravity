@props([
    'businessName',
])

{{--
    The shell for a tenant's own public feedback page.

    DELIBERATELY NOT components/marketing/layout. That shell carries our
    navigation, our wordmark and a "Start free" call to action — on a business's
    feedback page that advertises us to their customers and offers them somewhere
    else to go. The same reasoning components/auth/layout already records for
    itself: a person mid-task is given nowhere else to be.

    THERE IS NO JAVASCRIPT ON THIS PAGE. Not "no framework" — none at all. Slice
    F's picker must render with JavaScript disabled (BUILD-PLAN §2.6.3), it lives
    on the post-submit screen, so the path to that screen has to work without it.
    @vite ships only the stylesheet here for that reason.

    NO THIRD-PARTY REQUESTS, which is FPR-01's own acceptance criterion and the
    reason this page cannot use Turnstile the way the public audit does. @fonts
    self-hosts at build time, so it is not a third party. A feature test asserts
    the rendered markup references no external host — check it before adding
    anything to this head.

    UNBRANDED, and that is a visible gap rather than a finished state. FPR-01
    asks for the tenant's logo and colours; no column in the schema holds either,
    and adding them now would repeat what slice B removed — dead schema nothing
    writes (decision 311). The brand kit belongs with the wizard and the
    microsite engine.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- The form posts to a session-backed web route, so the token is required. --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{--
        Never indexed. A feedback form has no search value, and a crawler that
        finds one shared link would publish a directory of which businesses use
        the platform — the enumeration the slug's random suffix already guards
        against from the other direction.
    --}}
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $businessName }}</title>

    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-paper font-sans text-base text-ink">
    <main class="mx-auto w-full max-w-xl px-4 py-10 sm:py-16">
        <header class="mb-8">
            <p class="font-display text-2xl font-semibold tracking-tight">{{ $businessName }}</p>
        </header>

        {{ $slot }}
    </main>
</body>
</html>

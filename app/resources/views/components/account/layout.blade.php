@props(['title' => 'Your account', 'heading' => null, 'nav' => true, 'maxWidth' => 'max-w-5xl'])

{{--
    The shell for the owner's own screens.

    Modelled on components/setup/layout, and separate from it for the reason
    that file gives for having no navigation: *"a wizard is one job rather than
    a site"*. This is not a wizard — it is where an owner comes back to, so the
    navigation lives here.

    ⚠️ THIS IS THE FILE `layouts/app.blade.php` DID NOT EXIST AS, AND EVERY
    /admin SCREEN 500'd FOR MONTHS BEHIND THAT GAP (decision 570). This slice
    changes the layout every owner screen renders in, so it is exactly that
    shape — and the only assertion that would have caught it is a real `GET`.
    `OwnerNavTest` drives one per owner route for that reason, not as a
    formality: `Livewire::test()` renders the component and never the layout.

    ⚠️ @fonts WAS MISSING AND THE BRAND TYPEFACES HAD NEVER LOADED ON A SIGNED-IN
    SCREEN. `resources/css/app.css` names Archivo, Public Sans and IBM Plex Mono
    — "deliberately not Inter" — and nothing declared an @font-face for any of
    them here, so every owner screen fell through to `ui-sans-serif, system-ui`
    while the marketing site rendered in the real faces. Verified by inspecting
    what the directive emits rather than by reading it. ⚠️ components/setup/
    layout and components/auth/layout have the identical gap and are deliberately
    untouched — fixing one shell of three is drift, and this slice owns one.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-paper font-sans text-ink">
    @if ($nav)
        {{-- Keyboard users reach the content without tabbing the whole nav (WCAG 2.2 AA). --}}
        <a
            href="#main"
            class="sr-only rounded-[--radius-control] bg-card px-4 py-2 focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:ring-2 focus:ring-ink"
        >Skip to content</a>

        <x-account.nav :max-width="$maxWidth" />
    @endif

    <main id="main" class="mx-auto w-full {{ $maxWidth }} px-4 py-10 sm:py-16">
        @if ($heading)
            <h1 class="sr-only">{{ $heading }}</h1>
        @endif
        {{ $slot }}
    </main>
    <x-toaster-hub />
    @livewireScripts
</body>
</html>

@props(['title' => 'Your account', 'heading' => null, 'nav' => true, 'maxWidth' => 'max-w-5xl'])

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
<body class="min-h-screen bg-paper font-sans text-ink lg:flex">
    @if ($nav)
        <a
            href="#main"
            class="sr-only rounded-[--radius-control] bg-card px-4 py-2 focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:ring-2 focus:ring-ink"
        >Skip to content</a>

        <x-account.nav />
    @endif

    <div class="flex-1 flex flex-col min-w-0">
        <main id="main" class="mx-auto w-full {{ $maxWidth }} px-4 py-10 sm:py-16">
            @if ($heading)
                <h1 class="sr-only">{{ $heading }}</h1>
            @endif
            {{ $slot }}
        </main>
    </div>
    
    <x-toaster-hub />
    @livewireScripts
</body>
</html>

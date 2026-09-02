@props(['title' => 'Set up your account'])

{{--
    The shell for the onboarding wizard — the first authenticated owner-facing
    surface in this application. Modelled on components/marketing/layout, with
    two differences: this page is behind auth (`noindex, nofollow` rather than
    the marketing shell's conditional robots tag), and it carries no site
    navigation, because a wizard is one job rather than a site.
--}}

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-paper text-ink">
    <main class="mx-auto w-full max-w-2xl px-4 py-10 sm:py-16">
        {{ $slot }}
    </main>
    @livewireScripts
</body>
</html>

{{--
    The shell for the two auth screens the framework needs by name.

    Deliberately minimal and deliberately not a component library. The design
    system in `22` owns the real thing; this exists so `route('login')` resolves
    and a person can actually complete each of the four sign-in methods.

    What is NOT placeholder, because retrofitting it is how accessibility debt
    accumulates: tokens from resources/css/app.css rather than raw hex, body text
    at 16px, a real <label> per input, focus-visible rings, one <h1>, and a
    layout that works at 320px.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Sign in' }} · {{ config('app.name') }}</title>

    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-dvh bg-paper font-sans text-base text-ink">
    <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-4 py-10">
        <div class="rounded-[--radius-card] border border-rule bg-card p-6 shadow-[--shadow-card]">
            {{ $slot }}
        </div>
    </main>
</body>
</html>

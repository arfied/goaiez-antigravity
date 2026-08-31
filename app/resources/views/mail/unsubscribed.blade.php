{{--
    The one answer the POST gives, whatever happened — T176 P21.

    ⛔ IT SAYS THE SAME THING FOR A TOKEN THAT SUPPRESSED AN ADDRESS AND FOR ONE
    THAT DID NOTHING. A distinguishable answer would make a token a way to ask
    whether an address is on a tenant's list, which is the one read this endpoint
    must not offer — the token authorises a write and no read at all.

    ⚠️ IT IS ALSO WHAT A MAIL CLIENT RECEIVES, and a mail client ignores the body
    entirely. There is no way to tell the two callers apart, so there is one page
    that has to work for both.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('You are unsubscribed') }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-paper font-sans text-base text-ink">
    <main class="mx-auto w-full max-w-lg px-4 py-16">
        <h1 class="font-display text-3xl font-semibold tracking-tight">
            You are unsubscribed
        </h1>

        <p class="mt-3 text-ink-2">
            We have stopped emailing this address. You can close this page.
        </p>
    </main>
</body>
</html>
